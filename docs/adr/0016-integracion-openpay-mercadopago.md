# ADR 0016 — Integración real de OpenPay y Mercado Pago

Estado: Aceptado (Slice 12)

## Contexto

Replicar el patrón de Stripe (ADR-0015) a los otros dos proveedores del pedido,
respetando las particularidades de cada API.

## Decisiones

- **Mismo patrón, sin SDK**: cliente HTTP propio por proveedor
  (`ClienteOpenPay`, `ClienteMercadoPago`) usando las llaves del tenant;
  el adaptador crea el cobro y devuelve `pendiente`; el webhook confirma. Sin
  llaves configuradas, cae al intento simulado (demo). Todo testeable con
  `Http::fake`.
- **OpenPay**: auth HTTP Basic (private key). Monto en unidades mayores. Webhook
  `POST /webhooks/openpay` asegurado con **HTTP Basic** (usuario/contraseña
  configurados): se verifican contra la config del tenant. Evento
  `charge.succeeded` → fulfillment; `charge.failed/cancelled` → rechazo;
  `verification` → 200.
- **Mercado Pago**: auth Bearer (access token). Crea una **preferencia** de
  Checkout con `external_reference = pago.ulid` y `notification_url` **por tenant**
  (`/webhooks/mercadopago/{tenant}`). El webhook resuelve el tenant por la URL,
  **verifica la firma** `x-signature` (HMAC sobre
  `id:{data.id};request-id:{x-request-id};ts:{ts};`), **consulta el pago** en la
  API para leer su `external_reference` y estado, y confirma/rechaza.
- **Webhook genérico**: se generalizó la exclusión (`conWebhookFirmado`): un
  proveedor con webhook firmado **configurado con llaves** no se confirma por el
  endpoint genérico. Sin llaves (demo/sim) sí, para no romper el flujo de prueba.

## Consecuencias

- La prueba en vivo requiere llaves de sandbox reales del tenant (capturadas
  cifradas en la UI): OpenPay (merchant_id, private_key, webhook_user/password);
  Mercado Pago (access_token, webhook_secret) apuntando el webhook a la URL por
  tenant. Yo no ejecuto pagos reales.
- Los métodos específicos (tokenización de tarjeta OpenPay, `init_point`/redirect
  de Mercado Pago, SPEI) se afinan al integrar el frontend de checkout.
- El monto va en unidades mayores para ambos (`monto_minor / 100`); Stripe usa
  unidades menores.
