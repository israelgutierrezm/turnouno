# Auditoría de base de datos

## Modelo y estrategia

La API usa MySQL con una estrategia **shared-schema multi-tenant**. Casi todas las entidades de dominio incluyen `tenant_id`, `ulid`, timestamps y una FK simple. Eloquent agrega el scope tenant por defecto. Es una base adecuada para el volumen inicial y evita la complejidad de bases por tenant.

Agrupación lógica:

| Área | Tablas principales |
|---|---|
| Identidad/tenant | `users`, `personal_access_tokens`, `tenants`, `tenant_user`, tablas Spatie |
| Organización | `organizaciones`, `marcas`, `sucursales`, `asignaciones_personal` |
| Personas/familia | `personas`, `perfiles`, `hogares`, `tutelas` |
| Catálogo/recursos | `programas`, `actividades`, `niveles`, `ofertas`, `instalaciones`, `recursos` |
| Agenda | `plantillas_horario`, `reglas_recurrencia`, `sesiones`, `asignaciones_sesion` |
| Comercial | `productos_comerciales`, `ordenes`, `lineas_orden`, `pagos`, `configuraciones_pasarela` |
| Beneficios | `acuerdos`, `derechos`, `movimientos_credito`, `retenciones_credito` |
| Operación | `reservas`, `asistencias`, jobs/cache/session framework |

## Fortalezas

- FKs abundantes y nombres de tablas/columnas consistentes.
- ULID público separado de PK numérica interna.
- Uniques de negocio para slugs por tenant y configuración de pasarela.
- Snapshots de precio/subtotal en líneas de orden.
- Ledger de créditos en vez de un saldo mutable.
- Unique de asistencia por reserva.
- Índice `(sucursal_id, inicia_en)` para agenda y `(sesion_id, estado)` para cupo.
- Migraciones pequeñas, ordenadas y reproducibles.

## Hallazgos de integridad

### DB-01 — P1 — Las FKs no garantizan el mismo tenant

Ejemplo: `reservas` tiene FKs independientes a tenant, sesión, persona y derecho (`2026_09_14_130001_create_reservas_table.php:20-23`). Cada ID puede existir, pero la base no garantiza que todos pertenezcan al mismo tenant. El mismo patrón aparece en sesiones, líneas, pagos, acuerdos y asignaciones.

**Riesgo:** un bug/import/script puede unir datos de tenants distintos. El global scope puede esconder parte de la relación y producir autorizaciones, sumas o borrados inesperados.

**Propuesta priorizada:**

1. Añadir unique `(tenant_id,id)` a padres sensibles.
2. Añadir FK compuestas `(tenant_id,parent_id)` en:
   - `pagos → ordenes`;
   - `lineas_orden → ordenes/productos/personas`;
   - `reservas → sesiones/personas/derechos/retenciones`;
   - `sesiones → sucursales/ofertas/recursos/plantillas`;
   - `derechos → acuerdos` y `movimientos/retenciones → derechos`;
   - `asistencias → reservas`.
3. Mantener validaciones de aplicación; DB es la última barrera, no la única.

Hacerlo por fases: detectar/limpiar incoherencias, crear índices, añadir constraints, desplegar validación y monitorear.

### DB-02 — P1 — Idempotency keys globales y sin payload

`reservas.idempotency_key` y `pagos.idempotency_key` son unique globales (`...130001...:27`, `...150003...:26`). No modelan actor, operación, endpoint ni hash del request.

**Propuesta:** una tabla `idempotency_records` con:

```text
tenant_id, actor_id, operation, key, request_hash,
status, resource_type, resource_id, response_code, response_body,
locked_until, expires_at, created_at
UNIQUE (tenant_id, actor_id, operation, key)
```

Para PSP, guardar también la clave enviada y la referencia externa. Si se conserva la columna local, al menos unique `(tenant_id,idempotency_key)` y validación de payload/objeto.

### DB-03 — P1 — Historia transaccional expuesta a cascade delete

Órdenes, líneas, pagos, acuerdos, derechos, movimientos y reservas usan `cascadeOnDelete` ampliamente. Si en el futuro se añade borrado de persona/producto/tenant, se puede borrar historia financiera y operativa.

**Propuesta:**

- `RESTRICT` en raíces financieras y de ledger;
- `soft delete`/estado `archived` en maestros visibles;
- anonimización de PII separada de eliminación de transacciones;
- política de retención y *legal hold*;
- borrar físicamente solo mediante job controlado y auditado.

### DB-04 — P1 — Faltan restricciones de dominio

No se observaron `CHECK` para:

- montos, cantidad, subtotal y total no negativos/coherentes;
- capacidad/duración/unidades positivas;
- `termina_en > inicia_en`;
- rangos de día/hora/ciclos;
- estados/enums válidos;
- monedas ISO soportadas;
- derecho ilimitado frente a unidades/ciclos incompatibles.

Laravel valida HTTP, pero otros caminos (seeders, comandos, jobs, importaciones) pueden omitirlo.

**Propuesta:** checks simples y estables en MySQL; invariantes cruzadas complejas en servicios transaccionales y pruebas. No usar triggers para lógica de negocio extensa.

### DB-05 — P1 — Ledger sin referencia estructurada/idempotente

`movimientos_credito` registra tipo, unidades y descripción, pero no un origen único (`reservation_id`, `order_line_id`, `cycle_id`, `adjustment_id`). La descripción humana no permite reconciliar ni prevenir doble asiento.

**Propuesta:** `source_type`, `source_id`, `operation` y `UNIQUE(tenant_id,source_type,source_id,operation)`, actor/motivo para ajustes, y timestamps de negocio. Mantener movimientos append-only; una corrección debe ser otro asiento.

### DB-06 — P2 — Ausencia de entidad de evento de pago/reembolso

`pagos` representa intento y estado final, pero no cada evento del proveedor, historial de transición ni devolución parcial.

**Propuesta:**

- `payment_attempts`/pago actual;
- `payment_events` inbox append-only con unique `(provider,event_id)`;
- `refunds` con monto, moneda, reason, provider_ref y estado;
- `payment_state_transitions` o audit log;
- proyección reconciliable de `order.amount_paid/refunded`, sin perder eventos.

### DB-07 — P2 — Falta archivado y metadata de auditoría

La mayoría de maestros solo tiene timestamps. Faltan `created_by`, `updated_by`, `archived_at/by`, motivo y versión optimista donde hay edición concurrente.

**Propuesta:** usar audit log transversal y añadir columnas solo donde tengan semántica operativa. Evitar duplicar snapshots grandes en cada tabla.

## Índices y patrones de consulta

### Índices existentes útiles

- `sesiones (sucursal_id,inicia_en)` sirve al calendario.
- `reservas (sesion_id,estado)` sirve al conteo de cupo/roster.
- `ordenes (persona_id,estado)` sirve a historial por comprador.
- `pagos (orden_id,estado)` sirve a aprobación/idempotencia por orden.
- uniques tenant+slug/configuración sirven a lookups naturales.

### Índices candidatos, sujetos a `EXPLAIN ANALYZE`

| Consulta | Índice candidato | Motivo |
|---|---|---|
| Ciclos vencidos | `derechos (politica_reset,ciclo_fin,id)` o parcial equivalente no disponible en MySQL | El job filtra reset y fecha; hoy no hay índice alineado y usa `whereDate` |
| Comprobantes pendientes | `pagos (tenant_id,proveedor,estado,id)` | Cola de ventanilla por tenant/estado |
| Órdenes recientes tenant | `ordenes (tenant_id,id)` | Paginación cursor descendente de backoffice |
| Reservas de persona próximas | `reservas (persona_id,estado,sesion_id)` + acceso a fecha por sesión | Portal y agenda del miembro; validar plan real |
| Staff por sucursal | `asignaciones_personal (tenant_id,user_id,sucursal_id)` | Policy contextual frecuente |
| Eventos webhook | `payment_events (provider,event_id)` unique y `(tenant_id,created_at)` | Antirreplay y soporte |
| Auditoría | `audit_events (tenant_id,created_at,id)`, `(actor_id,created_at)` | Investigación y exportación |

No conviene añadir índices indiscriminadamente. Capturar slow query log y planes. Los `foreignId()->constrained()` necesitan índice; las llamadas explícitas posteriores como `lineas_orden.orden_id`, `movimientos_credito.derecho_id`, `retenciones_credito.derecho_id` y plantillas pueden ser redundantes según el DDL final. Verificar con `SHOW INDEX` antes de retirar alguno.

## Rendimiento de consultas

### N+1 de saldo

`DerechoController.php:25-36` carga derechos y por cada uno llama `saldo()` y `disponible()`. `disponible()` vuelve a invocar `saldo()` (`LibroMayor.php:25-39`): aproximadamente tres agregados por derecho.

**Propuesta inmediata:** subqueries `withSum` para movimientos y holds activos en una sola consulta; no materializar saldo hasta demostrar necesidad. A mayor escala, proyección de saldo actual actualizada atómicamente y reconciliada contra ledger.

### Listados sin límite

La mayoría de `index` termina en `get()`. La API documenta cursor pagination, pero no la implementa consistentemente. Todos los listados deben tener límite por defecto/máximo, cursor estable y filtros indexables.

### Job de ciclos

`GenerarCiclosEntitlement.php:25-41` carga todos los derechos, busca tenant uno por uno y procesa serialmente. `whereDate(ciclo_fin,...)` puede impedir un range scan directo.

**Propuesta:** `where('ciclo_fin','<',$today)`, `chunkById`, eager/batch tenant, job por tenant/lote, reintento idempotente y aislamiento de fallos. Filtrar acuerdos/tenants activos.

## Privacidad, respaldo y operación

Faltan artefactos verificables de:

- clasificación de PII y periodos de retención;
- exportación/corrección/borrado de sujeto;
- cifrado de backups y control de acceso;
- PITR y pruebas de restauración;
- RPO/RTO por entorno;
- réplica/reporting para no cargar OLTP;
- enmascaramiento de datos en staging.

Objetivo inicial sugerido: backups automáticos cifrados, PITR, RPO ≤ 15 min, RTO ≤ 4 h para piloto pagado, y simulacro trimestral documentado. Ajustar con negocio/contratos.

## Plan seguro de migración

1. Medir y listar inconsistencias tenant, duplicados y valores fuera de dominio.
2. Corregir datos con script idempotente y respaldo.
3. Desplegar validación de aplicación compatible hacia atrás.
4. Crear índices online cuando el motor/volumen lo requiera.
5. Añadir FK/checks en una ventana controlada.
6. Verificar planes, tiempos de lock y réplica.
7. Activar alertas de constraint/slow query.
8. Retirar columnas/índices viejos en un despliegue posterior, nunca en el mismo paso.
