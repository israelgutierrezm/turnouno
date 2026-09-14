# ADR 0014 — Pasarelas de pago configurables por tenant

Estado: Aceptado (Slice 10a)

## Contexto

Cada tenant usa su propia cuenta de proveedor (Stripe, OpenPay, Mercado Pago) y
quiere **encender/apagar** pasarelas y **capturar sus llaves**. Los pagos en línea
son asíncronos (tarjeta, OXXO, SPEI): el resultado real llega por webhook.

## Decisiones

- **Config por tenant** (`configuraciones_pasarela`): una fila por
  `(tenant, proveedor)` con `activa`, `modo` (test/live) y `credenciales`.
  Las credenciales se guardan **cifradas** (`encrypted:array`, con `APP_KEY`) y
  **NUNCA** se devuelven por la API: la config solo lista los *nombres* de llave
  configurados. Solo `pagos.configurar` (propietario) administra esto.
- **Registro tenant-aware** (`RegistroDePasarelas`): `manual` y `simulada` son
  integrados (siempre disponibles: efectivo y pruebas); Stripe/OpenPay/MercadoPago
  solo se resuelven si el tenant las tiene `activa`. `disponibles()` alimenta la
  validación del cobro → apagar una pasarela la vuelve no elegible.
- **Adaptadores** implementan `PasarelaDePago`. Los en línea heredan de
  `PasarelaEnLinea`, que se construye con la config del tenant y devuelve
  `pendiente` con una referencia; el **webhook idempotente** (ADR-0013) confirma y
  dispara el fulfillment. La llamada real al SDK del proveedor va en
  `crearIntento()` (por proveedor) y necesita llaves de sandbox para probarse en
  vivo.
- **Métodos** (`pagos.metodo`): tarjeta, OXXO, SPEI, ventanilla — el método
  disponible depende del proveedor. La resolución método↔proveedor y los vouchers
  reales se detallan al integrar cada SDK.

## Consecuencias

- **Seguridad**: los secretos viven cifrados y son de solo-escritura por API; nunca
  se exponen. La edición hace *merge* (no borra llaves no reenviadas). El webhook
  público sigue requiriendo verificación de firma por proveedor antes de producción
  (ADR-0013).
- La integración *live* de Stripe/OpenPay/Mercado Pago (crear intents/vouchers,
  verificar firma) queda enchufable en `crearIntento()` + el handler de webhook,
  con las llaves del tenant; requiere pruebas en vivo con sandbox del cliente.
- Ventanilla (depósito con comprobante que el staff aprueba) se implementa en 10b;
  el frontend de administración de pasarelas en 10c.
