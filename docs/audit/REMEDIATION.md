# Remediación de la auditoría

Registro de los hallazgos de la auditoría (2026-09-15) que ya se corrigieron en
código, con su commit y prueba. No modifica los documentos de auditoría
originales; los complementa. Corte: 2026-09-15.

## Resueltos

| ID | Sev. | Qué se hizo | Commit |
|---|---:|---|---|
| F-01 / SEC-01 | P0 | El miembro ya no puede pagar con `manual`/`simulada`. Canal `publicasActivas()` (solo configurables activas) separado del canal staff `disponibles()`; `simulada` rechazada en producción. Portal valida contra el canal público y aborta 403 en defensa en profundidad. | `cfcac0c` |
| SEC-02 | P0 | Datos demo (owners con contraseña conocida) movidos a `DemoSeeder`, invocado solo fuera de producción; `PilotosSeeder` con guard propio; contraseña por `DEMO_PASSWORD`. El seeder de producción solo siembra permisos. | `cfcac0c` |
| F-02 | P0 | La asistencia liquida el hold exactamente una vez: `presente` consume, `ausente` pierde (no-show); idempotente; ilimitado no toca el ledger. | `3286004` |
| F-06 | P1 | Cancelar una sesión es una operación agregada transaccional: cancela sus reservas activas y libera los holds (el crédito vuelve). | `40a61a9` |
| SEC-03 | P1 | Rate limit de login/token por identidad+IP (`throttle:login`); expiración de token configurable con `SANCTUM_TOKEN_EXPIRATION_MINUTES`. | `9cd21ed` |
| F-18 | P1 | La orden rechaza mezcla de monedas (`MIXED_CURRENCY`) y limita items (50) y cantidad por item (100). | `a62dcaa` |
| F-17 | P1 | Eliminado el N+1 del ledger: `LibroMayor::proyeccion()` calcula saldo/disponible de N derechos en 2 consultas. Usado por Derecho y Perfil. | `9bc1b68` |
| F-09 / SEC-05 | P1 | El instructor solo ve el roster y marca asistencia de sus sesiones asignadas (`AccesoSesion`); el staff con alcance amplio opera cualquiera. | `ecb6bdf` |
| F-13 | P1 | La renovación de ciclos omite acuerdos no activos (cancelados/pausados no reciben crédito) y procesa por lotes (`chunkById`) cacheando tenants. | `bfaaaa2` |
| SEC-11 | P2 | El comprobante se guarda con nombre aleatorio y extensión derivada del MIME real (no del cliente, no enumerable por ulid). | `7442762` |

Estado de pruebas tras la remediación: **API 138 Pest verdes**, Pint y PHPStan
(nivel 6) limpios.

## Pendiente (esfuerzo mayor o infraestructura)

Estos hallazgos siguen abiertos; requieren integración externa, nueva
infraestructura o trabajo de plataforma que excede un cambio acotado de código:

- **F-03 (P0)** — Reembolso real contra el PSP + saga/conciliación. Hoy el
  reembolso revierte el ledger/acuerdos pero no llama al proveedor.
- **F-04 / F-05 (P1)** — Rehacer la frontera de pagos: no llamar red bajo
  transacción/lock, idempotencia extremo a extremo, orquestación de intentos.
- **F-10 / SEC-09 (P1)** — Idempotencia con alcance `(tenant, operation, key)` +
  hash de payload e insert-catch dentro de la transacción.
- **SEC-06 (P1)** — Inbox de eventos webhook con `provider_event_id` único,
  antirreplay y cotejo completo de monto/moneda/merchant.
- **SEC-07 (P1)** — Permisos financieros finos (`benefits.grant`,
  `credits.adjust`…) con motivo/actor y doble aprobación.
- **SEC-08 (P1)** — Middleware de lifecycle de tenant (suspended/read_only).
- **F-07 / SEC-04 (P1)** — Autorización por sucursal aplicada con policies.
- **F-08 (P1)** — Motor de disponibilidad (solapes de recurso/instructor,
  coherencia recurso-sucursal-capacidad).
- **F-11 / F-12 (P1)** — Lista de espera: recorrer candidatos y revalidar.
- **F-14 / SEC-14 (P1/P2)** — Audit trail append-only de operaciones sensibles.
- **F-16 (P1)** — Paginación de listados.
- **SEC-10 / DB-01 (P2)** — Composite FKs `(tenant_id, id)`.
- **SEC-12 / SEC-13 (P2)** — Endurecer health y cabeceras de seguridad.
- **F-19 / F-20 (P2)** — Portal familiar (tutor↔dependiente), selector
  multi-tenant y app móvil.
