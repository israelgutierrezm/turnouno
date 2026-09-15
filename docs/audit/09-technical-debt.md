# Deuda técnica

## Lectura general

La deuda no está principalmente en estilo: Pint, PHPStan, ESLint y builds pasan. Se concentra en **semántica no modelada**, caminos privilegiados del MVP, duplicación frontend, falta de operación/telemetría y pruebas que validan el happy path sin desafiar invariantes.

## Registro priorizado

| ID | Sev. | Deuda | Interés que genera | Tratamiento |
|---|---:|---|---|---|
| TD-01 | P0 | `manual`/`simulada` mezcladas con pasarelas públicas | Fraude y reglas por canal imposibles de razonar | Separar registros/policies por canal y entorno |
| TD-02 | P0 | Lifecycle reserva–hold–asistencia incompleto | Saldos falsos, holds huérfanos y soporte manual | State machine + reconciliador + tests |
| TD-03 | P0 | Reembolso modelado como cambio local | Disputas financieras y doble contabilidad | Refund entity/orchestrator PSP |
| TD-04 | P0 | Datos demo en seeder principal | Despliegues inseguros | Seed canónico vs demo, guard de producción |
| TD-05 | P1 | Payment orchestration sin outbox/inbox | Timeouts ambiguos, dobles cobros, difícil recuperación | Estados/eventos/idempotencia/conciliación |
| TD-06 | P1 | Autorización repartida entre Gate y servicio no usado | Omisiones en cada endpoint nuevo | Policies contextuales y matriz de pruebas |
| TD-07 | P1 | Idempotencia ad hoc por tabla | Carreras y semántica inconsistente | Componente transversal con request hash |
| TD-08 | P1 | Relaciones tenant no reforzadas en DB | Riesgo latente de datos cruzados | FK compuestas por fases |
| TD-09 | P1 | Listados con `get()` | Coste lineal y bloqueos UX | Estándar de cursor pagination |
| TD-10 | P1 | Ledger consultado con N+1 | Latencia/costo crecientes | Query aggregates; proyección futura |
| TD-11 | P1 | Job de ciclos global y sin aislamiento | Ventana batch creciente y fallo total | Lotes/jobs/filtros/índice |
| TD-12 | P1 | Sin audit log | Investigación y cumplimiento manuales | Módulo transversal append-only |
| TD-13 | P1 | Sin observabilidad de negocio | Incidentes invisibles | Logs JSON, métricas, trazas, alertas |
| TD-14 | P2 | Vistas Vue grandes y lógica mezclada | Cambios lentos y pruebas escasas | Composables/services/components |
| TD-15 | P2 | Core web duplicado y versiones divergentes | Dos soluciones para auth/error/tenant | `packages/web-core` y armonizar versiones |
| TD-16 | P2 | Contratos API/types escritos a mano | Deriva silenciosa | OpenAPI CI + cliente/tipos generados |
| TD-17 | P2 | Móvil generado y health-only | Percepción falsa de avance | Rebaselinar como producto y backlog real |
| TD-18 | P2 | Documentación de estado atrasada | Decisiones basadas en información obsoleta | Status generado/revisado en cada release |
| TD-19 | P2 | Creaciones multi-escritura sin transacción | Agregados parciales | Unit of work en servicios de aplicación |
| TD-20 | P2 | Estados/valores solo validados en HTTP | Imports/jobs pueden romper invariantes | Value objects + DB checks |

## Hotspots de código

### Backend

- `Pagos/Application/CobrarOrden.php`: mezcla persistencia, lock, red, interpretación y fulfillment.
- `Pagos/Application/ReembolsarPago.php`: compensa múltiples dominios sin hablar con PSP ni bloquear todo el agregado.
- `Reservas/Application/CrearReserva.php`: correcta intención transaccional, pero acumula idempotencia, reglas, derecho, cupo y waitlist.
- `Reservas/Application/PromoverListaEspera.php`: política fija y elegibilidad incompleta.
- `Membresias/Application/GenerarCicloEntitlement.php`: loop de ciclos + agregados repetidos.
- `Console/Commands/GenerarCiclosEntitlement.php`: escaneo global y contexto manual.
- `Autorizacion/ControlDeAcceso.php`: abstracción válida que no llega a la capa HTTP.

### Frontend

- `admin-web/src/views/AgendaView.vue` (~601 líneas).
- `admin-web/src/views/MembresiasView.vue` (~538 líneas).
- `admin-web/src/views/CatalogoView.vue` (~309 líneas).
- `admin-web/src/App.vue`: navegación plana de muchos módulos.
- ambos `src/lib/api.ts` y `stores/auth.ts`: duplicación y ausencia de tenant activo.

## Pruebas: cantidad frente a cobertura de riesgo

### Estado verificado

- API: 127 pruebas, 609 aserciones, todas pasan.
- Admin: 3 archivos, 6 pruebas, pasan.
- Portal: 1 archivo, 2 pruebas, pasan.
- Móvil: 2 pruebas, pasan.
- Static/lint/build: pasan.

### Deuda de pruebas

Las pruebas API cubren verticales, tenancy, permisos, agenda, pagos y reservas, una fortaleza real. Pero algunas consolidan una regla equivocada: `PortalTest` espera que un miembro pague manualmente y reciba derecho; las pruebas de asistencia no verifican consumo del hold.

Faltan suites de:

- amenazas/negative authorization por sucursal y sesión asignada;
- concurrencia real e idempotency payload mismatch;
- PSP timeout/commit failure/webhook out-of-order/duplicate;
- reembolso externo y parcial;
- cierre/reconciliación de holds;
- integridad cross-tenant a nivel FK;
- query counts y contratos de paginación;
- componentes críticos, E2E, accesibilidad y responsive;
- backups/restore y runbooks.

No se observó umbral de cobertura en CI. Un porcentaje solo no resuelve estos huecos; definir tests por riesgo y mutation testing selectivo en pagos/reservas sería más valioso.

## Documentación

Los ADR y documentos de dominio son una fortaleza, pero hay deriva: README/plan todavía describen algunos slices como pendientes aunque ya existen módulos de organizaciones, catálogo, agenda, reservas, pagos y portal. Los comentarios de `CancelarSesion` dicen que la liberación se resolverá en Slice 7 aunque ese slice existe y la integración sigue ausente.

**Tratamiento:**

- una página `STATUS.md` derivada de capacidades verificadas;
- ADR actualizados cuando cambia la semántica financiera;
- diagramas y runbooks versionados;
- “docs as code” con link checker y revisión obligatoria en PR de contrato/flujo.

## Dependencias y toolchain

Composer/npm audit reportaron 0 vulnerabilidades conocidas el 2026-09-15. Flutter indicó 7 paquetes transitivos con versiones más nuevas incompatibles con constraints. No hay urgencia de actualización solo por número, pero sí falta un proceso:

- Renovate/Dependabot con lotes y ventanas;
- audit en CI;
- SBOM y licencia;
- pin de acciones GitHub por SHA para mayor supply-chain hardening;
- escaneo de secretos e imágenes;
- matriz de versiones soportadas y política de EOL.

Admin usa Pinia 4/Router 5 y portal Pinia 2/Router 4; armonizar evita comportamiento/API divergente y facilita core compartido.

## CI/CD y operación

CI está bien estructurado y ejecuta MySQL/Redis, lint, análisis, tests y builds. Deuda pendiente:

- no dependency/secret/SAST/DAST/container scan;
- no coverage/query budget;
- no E2E cross-app;
- no artefactos firmados/SBOM;
- no pipeline de deployment, ambiente efímero ni smoke tests visibles;
- no validación de migraciones expand-contract;
- no restauración automatizada;
- acciones referenciadas por tag mayor, no SHA inmutable.

## Plan de pago de deuda

### Inmediato

Congelar features financieras nuevas. Resolver TD-01 a TD-07 y añadir pruebas que fallen antes del fix.

### Próximas 4–8 semanas

Integridad tenant, paginación, agregados del ledger, ciclo batch, audit/outbox/observabilidad y selector tenant.

### Próximos 2–3 meses

Refactor frontend, contratos generados, E2E/a11y, lifecycle SaaS, storage privado compartido y runbooks.

### Regla de gobernanza

Reservar 25–35% de capacidad por sprint hasta cerrar P0/P1. Cada nueva función debe declarar invariantes tenant, idempotencia, auditoría, métricas, política de datos y estrategia de rollback.

## Indicadores de deuda

- P0/P1 abiertos y antigüedad.
- Cambios que tocan >3 módulos o vistas >300 líneas.
- Queries por endpoint y endpoints sin paginar.
- Flujos críticos sin test negativo/concurrencia.
- Incidentes sin audit/correlation suficiente.
- Dependencias/acciones fuera de política.
- Tiempo de restauración y porcentaje de runbooks ejercitados.
