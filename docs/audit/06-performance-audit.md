# Auditoría de rendimiento

## Conclusión

El rendimiento es suficiente para datos demo y pilotos pequeños, pero no está preparado para crecimiento sostenido. El mayor costo esperado viene de consultas cuya cardinalidad crece sin límite, agregados N+1 del ledger y trabajo global/sincrónico. No se ejecutó una prueba de carga; las conclusiones son estructurales y deben validarse con telemetría.

## Hallazgos prioritarios

### PERF-01 — P1 — Listados no paginados

Patrones `orderBy(...)->get()` aparecen en personas, organizaciones, sucursales, catálogo, recursos, productos, derechos, órdenes, sesiones, roster y comprobantes. Ejemplos:

- `SesionController.php:42-45` carga todas las sesiones del intervalo; sin fechas, todas.
- `CompraController.php:51-55` carga todo el historial con líneas/productos/pagos.
- `DerechoController.php:25-31` carga todos los derechos.

**Impacto:** memoria y latencia lineales, respuestas grandes, bloqueo del hilo PHP y render pesado en navegador.

**Acción:** cursor pagination con límite por defecto 25/50 y máximo 100; cursor compuesto estable cuando se ordena por fecha+id; filtros obligatorios para calendarios amplios; metadatos `next_cursor`.

### PERF-02 — P1 — N+1 de ledger

Por cada derecho se calcula `saldo`, luego `disponible`, que suma holds y vuelve a calcular `saldo` (`LibroMayor.php:25-39`). Un listado de 100 derechos puede generar alrededor de 301 consultas, además de relaciones.

**Acción:** `withSum`/subqueries condicionadas y DTO con ambos valores. Añadir un query-count test. Si el ledger crece a millones, incorporar una proyección `credit_balances` actualizada en la misma transacción y reconciliación periódica; el ledger sigue siendo fuente de verdad.

### PERF-03 — P1 — Red externa dentro de transacción

`CobrarOrden.php:42-89` mantiene una transacción y lock de orden mientras ejecuta `$pasarela->cobrar()` en `:67`. La latencia/timeout del PSP prolonga locks y ocupa conexiones DB/PHP.

**Acción:** transacciones cortas antes/después, outbox/worker para operación remota y clave idempotente. Definir `connectTimeout`, `timeout`, reintentos solo seguros con jitter y circuit breaker/alerta.

### PERF-04 — P1 — Renovación global en memoria

`GenerarCiclosEntitlement.php:25-41` usa `get()`, luego un lookup de tenant por derecho y procesamiento serial. Un tenant atrasado puede recorrer hasta 120 ciclos por derecho, cada uno con sumas y escrituras.

**Acción:** `chunkById`, jobs por tenant/lote, límite de concurrencia, índice por fecha, filtrado de acuerdos activos, checkpoint y métricas. Para atrasos extremos, calcular el resultado agregado con cuidado y registrar asientos resumidos si la política contable lo permite.

### PERF-05 — P2 — Health costoso y público

El endpoint prueba DB/cache/Redis activamente. Un monitor frecuente o atacante puede convertir un endpoint pequeño en amplificación contra dependencias.

**Acción:** separar liveness/readiness, cachear 5–15 s la respuesta de readiness, restringir detalles y rate-limit.

### PERF-06 — P2 — Fulfillment proporcional a cantidad dentro del request

La orden no limita items/cantidad y la aprobación crea acuerdos/derechos por unidad. Un request grande puede realizar cientos/miles de inserts antes de responder.

**Acción:** límites de negocio; fulfillment en lotes idempotentes cuando supere umbral; no retornar éxito de pago como “cumplido” hasta tener estado explícito `fulfillment_pending/completed`.

### PERF-07 — P2 — Frontend sin virtualización/paginación

Las SPA renderizan arrays completos recibidos. Esto multiplica el problema de API. Vistas administrativas grandes mezclan fetching, formularios y tablas, lo que aumenta rerenders y costo de mantenimiento.

**Acción:** paginación/infinite scroll según tarea, componentes de tabla virtualizada solo donde el volumen lo justifique, stores/composables por dominio y cancelación de requests obsoletos.

## Resultado de builds

Builds de producción ejecutados el 2026-09-15:

| Aplicación | JS inicial | Gzip inicial | CSS | Observación |
|---|---:|---:|---:|---|
| Admin | 217.24 kB | 79.03 kB | 15.35 kB | Rutas lazy; chunks de vista máximos ~14.44 kB |
| Portal | 210.05 kB | 76.60 kB | 12.16 kB | Rutas lazy; vista compra ~6.18 kB |

Los bundles no son hoy un bloqueo. Conviene vigilar presupuesto, pero la prioridad está en correctness/consultas. Definir presupuesto inicial: JS inicial gzip < 120 kB por SPA, chunk de ruta < 80 kB y LCP p75 < 2.5 s en móvil medio.

## Cache

Redis está configurado y probado por health, pero no se observó una estrategia explícita de cache de dominio. Recomendaciones:

- cachear catálogos públicos/estables por tenant con versión, no datos financieros ni capacidad sin invalidación sólida;
- namespaces `tenant:{id}:...` y TTL;
- evitar cache stampede con locks breves;
- no usar cache como fuente de verdad de cupo/saldo;
- medir hit rate, tamaño y evictions antes de ampliar.

## Colas

La infraestructura propaga tenant a jobs, pero casi no hay jobs de dominio. Mover a cola:

- envío de notificaciones;
- procesamiento/conciliación de pago que tolere asincronía;
- exportaciones/reportes;
- escaneo de comprobantes;
- renovación de ciclos por lotes;
- cierre/reconciliación de sesiones y holds.

Separar colas `critical-payments`, `booking-events`, `notifications`, `exports`, `maintenance`; aplicar timeout/retry diferentes, DLQ y métricas. No poner autorización interactiva o el lock de cupo en jobs.

## Consultas y concurrencia

- Mantener el lock de sesión en reserva: protege una invariante real.
- Establecer orden consistente de locks para `sesión → reserva → derecho/hold` y `pago → orden → derechos` o rediseñar para reducir cruces; documentarlo para evitar deadlocks.
- Añadir retry acotado ante deadlock para operaciones idempotentes.
- Usar índices de cobertura solo tras observar planes; índices extra penalizan las escrituras intensivas del ledger.
- Separar reporting de tablas OLTP con proyecciones/réplica cuando el volumen lo demande.

## Objetivos SLO sugeridos

| Operación | Objetivo inicial |
|---|---:|
| Login `/me` | p95 < 400 ms |
| Listado paginado | p95 < 500 ms, payload < 250 kB |
| Crear/cancelar reserva | p95 < 700 ms, excluyendo red externa |
| Crear intento de pago | p95 local < 500 ms; PSP medido aparte |
| Webhook ACK | p95 < 500 ms; trabajo durable asíncrono |
| Error rate API | < 0.5% excluyendo 4xx esperados |
| Job de ciclo | backlog < 30 min y 0 derechos omitidos |

Estos valores son punto de partida, no compromiso contractual.

## Plan de medición

1. Logs JSON con route, tenant pseudonimizado, status, duración, queries, tiempo DB/PSP y correlation ID.
2. APM/trazas de reserva, pago, webhook, fulfillment y reembolso.
3. Métricas RED por endpoint y USE para PHP/MySQL/Redis/workers.
4. Slow query log y muestreo de `EXPLAIN ANALYZE` con datos representativos.
5. Dataset sintético: 1 M personas, 10 M reservas, 50 M movimientos distribuidos en tenants.
6. Pruebas de carga enfocadas: pico de apertura de cupos, checkout, webhook burst y renovación mensual.
7. Pruebas de soak y chaos de PSP/Redis con timeouts.

## Orden de optimización

1. Corregir integridad funcional y transacciones PSP.
2. Paginar y limitar.
3. Eliminar N+1 del ledger.
4. Trocear jobs globales.
5. Añadir telemetría y objetivos.
6. Solo entonces introducir cache/proyecciones/replicas según evidencia.
