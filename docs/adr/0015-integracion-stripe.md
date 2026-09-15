# ADR 0015 — Integración real de Stripe (HTTP, sin SDK)

Estado: Aceptado (Slice 11)

## Contexto

`PasarelaStripe` era un placeholder (intento simulado). Se necesita el cobro real:
crear un PaymentIntent con las llaves del tenant y confirmar por webhook de forma
segura.

## Decisiones

- **Sin SDK: cliente HTTP propio** (`ClienteStripe` sobre `Illuminate\Http`).
  Evita una dependencia nueva y es **testeable con `Http::fake`**. `PasarelaStripe`
  crea el PaymentIntent (`amount`, `currency`, `payment_method_types` según el
  método: `card` u `oxxo`) con la `secret_key` del tenant y devuelve el id del
  intent como referencia (queda `pendiente`).
- **Degradación**: sin `secret_key` configurada, `PasarelaStripe` cae al intento
  simulado (demo/pruebas siguen funcionando sin llaves).
- **Webhook firmado**: `POST /webhooks/stripe` (público). `ProcesarWebhookStripe`
  ubica el pago por el PaymentIntent, fija el tenant, y **verifica la firma**
  (`Stripe-Signature`: `HMAC-SHA256(t.'.'.cuerpo, webhook_secret)`) con el
  `webhook_secret` del tenant ANTES de confirmar. `succeeded` → fulfillment;
  `payment_failed` → rechazado. Idempotente.
- **Cierre del hueco**: el webhook genérico (`/webhooks/pagos/{proveedor}`, sin
  firma) ya **no** confirma pagos de `stripe` — obligaría a saltarse la firma. Los
  proveedores aún simulados (openpay/mercadopago) lo siguen usando hasta tener su
  integración real.

## Consecuencias

- La prueba en vivo requiere llaves de sandbox reales del tenant (se capturan en
  la UI de pasarelas, cifradas). En pruebas se usa `Http::fake` + firma calculada.
- OpenPay y Mercado Pago siguen el mismo patrón (cliente HTTP + webhook firmado)
  cuando se integren; hoy usan el intento simulado.
- SPEI en Stripe (customer balance / bank transfer) y el manejo de `next_action`
  (voucher OXXO, 3DS) del lado del cliente quedan como trabajo futuro.
- Amounts en unidades menores (`amount` = `monto_minor`), moneda en minúsculas.
