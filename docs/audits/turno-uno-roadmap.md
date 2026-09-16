# TurnoUno — Roadmap de evolución (P0–P3)

Deriva de `turno-uno-competitive-audit.md`. Regla rectora del documento de producto:
**no implementar todo simultáneamente; entregas pequeñas y verificables.** Cada ítem crítico incluye prueba de regresión.

**Principio de secuencia:** primero **cerrar las regresiones del plano tenant** (defectos de integridad/seguridad que ya cambian dinero o créditos), luego **consolidar los motores núcleo** (unificar legacy↔tenant y darles la forma que pide el documento), después operación, crecimiento e inteligencia. **No se inicia una fase si sus regresiones de integridad siguen abiertas.**

---

## P0 — CRÍTICO (integridad, dinero, seguridad, motores núcleo)

### P0.A — Correcciones de integridad (regresiones tenant) — *primero, son pequeñas*

| Ítem | Acción | Archivos | Verificación |
|---|---|---|---|
| Cancelar sesión deja holds colgados | Portar el cascade legacy (`CancelarSesion`→`CancelarReservasDeSesion`) a `AgendaTenantController::cancelar`: cancelar reservas activas + liberar/ perder holds en transacción | `Tenancy/Http/Controllers/AgendaTenantController.php:111-117`, reusar patrón `Agenda/Application/CancelarSesion.php` | Test: cancelar sesión con reservas confirmadas ⇒ reservas canceladas y créditos liberados |
| Webhook tenant forjable | Exigir firma por proveedor (portar verificadores legacy OpenPay/MP); Stripe: rechazar sin `webhook_secret` en prod; considerar quitar la ruta genérica por `referencia` | `Tenancy/Http/Controllers/WebhookTenantController.php:36-52` | Test: webhook sin firma/ firma inválida ⇒ 400, sin fulfillment |
| Promoción de waitlist consume de más | `promover()` debe usar el costo original de la reserva, no `1000` fijo | `Tenancy/Application/ReservasTenant.php:192-193` | Test: promover con costo≠1000 ⇒ consumo correcto |
| Carrera de idempotencia | Capturar unique-violation en `crear()` y releer por `idempotency_key`; propagar clave en autoservicio | `ReservasTenant.php:42-47,105-113`, `MiTenantController.php:97` | Test de carrera (misma clave concurrente) ⇒ 1 reserva, sin 500 |
| Defensa en profundidad | Unique parcial `(sesion_id, persona_id)` sobre estados activos | `migrations/tenant/…000010` (nueva migración) | Test: doble reserva ⇒ rechazada por BD |
| Throttle autenticado | Aplicar `throttle` a los grupos `estudio.auth` | `routes/api.php:123-244` | Test: ráfaga ⇒ 429 |

### P0.B — Motores núcleo (dan la forma que pide el documento)

| Ítem | Acción | Prio dentro de P0 | Estado |
|---|---|---|---|
| **Booking Policy Engine (R1)** | Extraer un motor único (legacy+tenant) que devuelva `Decision{allowed,reason_code,message,rules_evaluated,credit_cost,entitlement_used,warnings}`; modo `preview`; sumar reglas nivel/edad/conflicto/no-show/deuda/prioridad. Base ya documentada en `docs/BOOKING_ENGINE.md`. | Alta | ✅ `977adea` (base: decisión + preview; reglas nivel/edad/etc. quedan como extensiones progresivas) |
| **Renovación de ciclos en tenant (R10)** | Hacer que `GenerarCicloEntitlement` opere sobre `DerechoTenant` (hoy solo legacy): RENEWAL/EXPIRATION/ROLLOVER reales en el plano activo. | Alta | ✅ `b9ac1a8` |
| **Credit ledger auditable (R2)** | Añadir a `movimientos_credito`: `persona_id, source, reason(tipado), balance_after, reserva_id/retencion_id, actor_id, metadata`; ampliar enum de tipos. | Alta | ✅ `e9176df` (origen/actor/saldo_posterior/referencia/metadata + endpoint historial; enum ampliado con `OrigenMovimiento`) |
| **Audit log (R38)** | Módulo `Audit` append-only (actor/tenant/sucursal/action/entity/before/after/motivo/ip/correlation) cableado a crédito/refund/override/permiso/precio/documento. | Alta | ✅ `22f8b9d` (base + cableado al top-up; más cableados al avanzar refund/override) |
| **RBAC scope + overrides auditables (R19)** | Portar branch-scope de `ControlDeAcceso` al tenant; exigir `motivo`+actor en concesiones/consumos/overrides. | Media | ✅ Branch-scope portado: `AsignacionPersonalTenant` + `ResolverAccesoTenant::permiteEnSucursal` (tenant-wide O rol asignado en la sucursal) + CRUD de asignaciones + integracion en `AccesoSesionTenant`. Overrides auditables ya cubiertos: top-up (bitacora+actor), refund (motivo+actor obligatorios), y todo el ledger lleva actor/origen (R2). Falta: `puede:` middleware branch-aware para todas las rutas acotadas a sucursal. |
| **Refunds tenant (R11)** | `ReembolsarPagoTenant` con parcial/proporcional, entidad Refund enlazada a Pago, devolución real por pasarela + conciliación. | Media | ✅ `ReembolsarPagoTenant` + `ReembolsoTenant` (total revierte entitlement con bloqueo-si-usado; parcial monetaria/proporcional; suma acotada al monto; auditada) + contrato `PasarelaReembolsable` para la devolución en línea (falta implementarla en Stripe con llaves reales, como el cobro en vivo). |
| **Cancellation/no-show configurable (R8)** | Política por tenant/actividad/plan (deadline/crédito/penalización/strike/tolerancia) + snapshot en la reserva. | Media | ✅ `PoliticaCancelacionTenant` (global + override por actividad) con deadline + penaliza_tarde + penaliza_no_show; snapshot congelado en la reserva y aplicado en cancelar/asistencia. Falta: strikes/tolerancia (columna lista, lógica difierida) y override por plan. |

*(Tenant isolation ya está EXISTE Y CORRECTO — no requiere trabajo P0, solo mantener los tests.)*

---

## P1 — OPERACIÓN

**Habilitador primero:** **Events + Outbox (R39)** — desbloquea comunicaciones, webhooks salientes, analítica y automatización. ✅ **HECHO**: outbox transaccional tenant (`eventos_outbox` + `RegistrarEventoTenant`, se escribe en la misma transacción que el cambio de estado), relay `turnouno:despachar-outbox` (at-least-once, reintentos, agendado cada minuto `withoutOverlapping`) que dispara `EventoDeDominioTenant` (costura de desacople para los consumidores). Primeros eventos cableados: `reserva.creada` y `pago.reembolsado`. Pendiente al construir cada consumidor: sumar más tipos (venta, asistencia, ciclo…) y una tabla `inbox`/dedupe por consumidor.

- **Scheduling/recurrence en tenant (R5):** portar plantillas/reglas + `serie_id` + excepciones/feriados + override por instancia.
- **Resource Engine en tenant (R3):** portar `Instalacion/Recurso` + `recurso_id`/pool en sesión; enum `TipoRecurso`; modo de uso de la oferta (CAPACITY/ASSIGNED/POOL/OPEN/APPOINTMENT/COURT/GROUP).
- **Access engine (R12):** entidad `Acceso` con `metodo` (QR/PIN/NFC/…) + evaluador de política de acceso + OPEN_ACCESS por membresía sin reserva.
- **Waitlist robusta (R7):** estados OFFERED/ACCEPTED/EXPIRED, config y notificación.
- **Front Desk (R13):** vista día×sucursal con métricas + acciones rápidas + walk-in + overrides auditados.
- **Staff multi + sustitución + Payroll (R17):** portar multi-staff/roles; scheduled/actual/substitution; módulo Payroll (esquemas + cálculo).
- **Grupos/Enrollment (R25):** para natación/danza/academias (asistencia auto por occurrence).
- **Familias en tenant (R26):** portar Hogar/Tutela + política guardián→dependiente.
- **Waivers (R27):** versionado + `aceptaciones_documento` (accepted_at/ip/hash) + re-aceptación.
- **Industry/business profiles (R35):** `perfil_negocio` en Estudio + defaults/terminología/feature-flags (sin forks).
- **Comunicaciones (R28):** módulo con outbox + estados + templates + colas.
- **Dunning (R10):** reintentos/delays/grace/suspensión + política de reservas ante fallo.
- **Webhooks salientes + API keys (R40):** subsistema firmado sobre outbox; API keys con scopes. ✅ **Webhooks salientes HECHOS**: `webhooks_salientes` (endpoints con secreto HMAC cifrado, suscripción por tipo o todos) + `entregas_webhook` (registro por intento); listener `EnviarWebhooksSalientes` sobre `EventoDeDominioTenant` firma (HMAC-SHA256, cabecera `X-TurnoUno-Signature`) y entrega; `turnouno:reintentar-webhooks` reintenta las fallidas (agendado c/5 min). CRUD `/webhooks-salientes` (permiso `integraciones.configurar`, secreto devuelto solo al crear). **Pendiente**: API keys con scopes (subsistema aparte).
- **Multi-sucursal (R18):** Brand/Region + `sucursal_id` home + moneda/impuestos por sucursal + reporting consolidado.
- **Importación CSV (R37):** clientes/membresías/créditos/instructores/productos con preview/errores/rollback.
- **Tax/fiscal:** integración local (CFDI/facturación) donde el mercado lo exija.

---

## P2 — CRECIMIENTO

- **CRM (R15):** pipeline configurable + lead_source + timeline 360.
- **Automation engine (R16):** trigger→condition→delay→action sobre eventos/colas.
- **Marketplace/aggregator capacity (R20):** `ChannelCapacityRule` + booking_source/revenue_source + liberación progresiva.
- **Promociones (R22)** y **Referidos (R23).**
- **First-timer experience (R14).**
- **Reportes de negocio (R29):** MRR/churn/occupancy/no-show/ARPU/booking-source desde proyecciones.
- **POS e inventario (R21):** stock por sucursal, separado de membresías.
- **Visual resource map (R4).**
- **Onboarding (R36):** pasos industria/recursos/import + quick-start.
- **Transferir/regalar reserva (R9).**

---

## P3 — INTELIGENCIA

- **Loyalty/gamification (R24)** — opcional, desactivable.
- **Class profitability (R30)** — requiere payroll + revenue por sesión.
- **Demand analytics (R31)** y **Smart fill (R32)** — reglas explicables primero.
- **Churn/risk engine (R33)** — score rule-based explicable.
- **AI Front Desk (R34)** — tool/service layer autorizada/auditada sobre Application Services; nunca acceso directo del modelo a BD.

---

## Primer bloque de implementación recomendado (2–3 días, verificable)

> **✅ COMPLETADO** — commit `be94bc4` (`RegresionesIntegridadTenantTest`, 7 tests; suite ControlPlane 113 verde). Ítems 1–4 y 6 hechos; el ítem 5 (unique `(sesion_id, persona_id)`) se difirió a P1 por su forma portable SQLite/MySQL (el lock ya previene el duplicado).

**"Cierre de regresiones de integridad del plano tenant" (P0.A).** Es pequeño, aislado, de alto valor y totalmente cubrible con tests:

1. `AgendaTenantController::cancelar` → cancelar reservas + liberar holds (reusar patrón legacy) **+ test**.
2. Firma obligatoria en `WebhookTenantController` (OpenPay/MP) y Stripe-sin-secret rechazado en prod **+ tests**.
3. `ReservasTenant::promover()` usa el costo real **+ test**.
4. Carrera de idempotencia: capturar unique-violation y releer **+ test de carrera**.
5. Unique parcial `(sesion_id, persona_id)` **+ migración tenant + test**.
6. `throttle` en grupos `estudio.auth` **+ test**.

**Criterio de terminado del bloque:** suite verde (incluye los 6 tests nuevos), Pint + PHPStan L6 limpios, y ningún crédito puede quedar "colgado" ni ningún pago confirmarse sin firma. Este bloque **no añade features**: restaura integridad, que es requisito para todo lo demás.

**Siguiente bloque sugerido (P0.B, 1–2 semanas):** Booking Policy Engine con `Decision` estructurada + `preview` (unificando legacy/tenant), sobre el diseño ya escrito en `docs/BOOKING_ENGINE.md`.
