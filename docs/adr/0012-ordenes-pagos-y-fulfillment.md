# ADR 0012 — Órdenes, pagos proveedor-agnósticos y fulfillment

Estado: Aceptado (Slice 9a)

## Contexto

Hasta ahora vender un producto (`CrearAcuerdo`) concedía el derecho directamente,
sin cobro. El producto necesita comercio real: una **orden**, un **pago** a través
de un proveedor, y la concesión del entitlement **solo** cuando el pago se aprueba.
El MVP pide "manual/cash + una pasarela online por adaptador" y pagos
"provider-independent" (PRODUCT.md, MVP.md).

## Decisiones

- **Orden → Pago → Fulfillment**. `CrearOrden` arma una orden `pendiente` con
  líneas (precio congelado como snapshot; total = suma). `CobrarOrden` cobra vía
  una pasarela; si aprueba, marca la orden `pagada` y **concede los derechos**
  (un `CrearAcuerdo` por unidad de cada línea) **en la misma transacción**. Un pago
  rechazado deja la orden `pendiente` y no concede nada.
- **Comprador != participante**. Cada línea lleva `beneficiario_id`: el derecho se
  concede al beneficiario (o al comprador si es nulo). Así la madre paga y la hija
  recibe el pack.
- **Pasarela proveedor-agnóstica**. El dominio cobra a través de la interfaz
  `PasarelaDePago` (`cobrar(Pago): ResultadoPago`). Implementaciones MVP:
  `PasarelaManual` (efectivo/transferencia, aprueba al registrar) y
  `PasarelaSimulada` (adaptador online, determinista). Una pasarela real se conecta
  implementando la misma interfaz; `RegistroDePasarelas` la resuelve por nombre.
- **Idempotencia**. `idempotency_key` único en `pagos`; reintentar con la misma
  clave devuelve el pago existente. Cobrar una orden ya `pagada` devuelve su pago
  aprobado sin volver a conceder. `lockForUpdate` sobre la orden serializa cobros
  concurrentes.
- **Dinero**: `total_minor`/`monto_minor`/`precio_unitario_minor` BIGINT +
  `moneda CHAR(3)`. Nunca float. Una moneda por orden en el MVP.
- **Códigos estables**: `ORDER_NOT_PAYABLE` (orden cancelada); pasarela desconocida
  se corta en validación (`VALIDATION_FAILED`).

## Consecuencias

- La venta directa `POST /personas/{}/acuerdos` (sin cobro) sigue existiendo; el
  frontend migrará a orden+pago en 9b.
- Reembolso (revertir el entitlement), webhooks asíncronos con idempotencia por
  evento (outbox, ADR-0004) y notificaciones llegan en Slice 9b.
- El outbox aún no se usa: el fulfillment es síncrono dentro de la transacción del
  pago (consistencia fuerte comprador→pago→derecho). Los side-effects externos
  (recibo por correo, webhooks salientes) usarán outbox cuando se agreguen.
- Costo/impuestos, descuentos y multi-moneda quedan como trabajo futuro.
