# ADR 0013 — Reembolso (bloquear si hubo uso) y webhooks idempotentes

Estado: Aceptado (Slice 9b)

## Contexto

Completa el comercio (ADR-0012) con la reversa de un pago y la confirmación
asíncrona por webhook. Reembolsar revierte un entitlement ya concedido, lo que
tiene aristas: ¿qué pasa si el derecho ya se consumió o tiene reservas activas?

## Decisiones

- **Traza del fulfillment**. Cada acuerdo concedido por una orden guarda
  `linea_orden_id`. El reembolso reencuentra por ahí los derechos a revertir.
  Nulo en ventas directas (sin orden).
- **Política de reembolso: bloquear si hubo uso** (decisión de producto para el
  MVP). Solo se reembolsa si TODOS los derechos de la orden están **intactos**:
  sin movimientos de `consumo` y sin retenciones `activa`. Si alguno ya se usó →
  `REFUND_BLOCKED_USED` (422); el staff resuelve el caso mixto manualmente.
  El reembolso parcial (prorrateo) queda como mejora futura.
- **Reversa**. Al reembolsar: por cada derecho intacto se asienta un `reverso` que
  deja el saldo en 0, se cancela el acuerdo, se marca el pago `reembolsado` y la
  orden `cancelada`. Solo se reembolsa un pago `aprobado`
  (`PAYMENT_NOT_REFUNDABLE`); es idempotente.
- **Webhook idempotente**. `POST /webhooks/pagos/{proveedor}` es público (el
  proveedor no tiene sesión ni tenant): ubica el pago por su `referencia_externa`
  (global), fija el TenantContext y confirma. Si el pago ya está `aprobado`, no
  vuelve a cumplir la orden — eventos duplicados o reintentos del proveedor NO
  conceden derechos dos veces. Referencia desconocida → 200 e ignora.

## Consecuencias

- **Seguridad**: el endpoint público dispara fulfillment, así que en producción
  DEBE verificarse la firma del proveedor antes de confiar en el evento; la
  `referencia_externa` aleatoria es defensa parcial, no suficiente. La
  `PasarelaSimulada` es solo para pruebas.
- El reembolso no maneja consumo parcial ni cancela reservas activas (las bloquea);
  prorrateo y cancelación en cascada de reservas quedan para después.
- El fulfillment sigue siendo síncrono; el outbox (ADR-0004) se usará para el
  recibo por correo y webhooks salientes cuando se agreguen.
