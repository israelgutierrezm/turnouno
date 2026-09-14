# ADR 0011 — Booking: capacidad transaccional, resolver de entitlement y política por holds

Estado: Aceptado (Slice 7a)

## Contexto

El motor de reservas (BOOKING_ENGINE.md) debe responder "¿puede esta persona
reservar esta sesión ahora, con qué derecho y por qué?" sin sobrevender el cupo
ante reservas concurrentes, y descontar créditos de forma auditable. Ya existen
las piezas: sesiones con `capacidad` (Slice 6), derechos con saldo derivado del
ledger y holds concurrency-safe (Slice 5b).

## Decisiones

- **No-sobreventa por lock del agregado**. `CrearReserva` corre en una
  `DB::transaction` que hace `lockForUpdate` sobre la fila de la `Sesion` y cuenta
  las reservas confirmadas **bajo el lock** antes de insertar. Dos reservas
  concurrentes se serializan en esa fila; nunca se excede `capacidad`. (Mismo
  patrón que el hold de crédito sobre el `Derecho`.)
- **Resolver de entitlement**. Se elige un derecho vigente de la persona (acuerdo
  activo, dentro de `valido_desde/hasta`) con saldo suficiente. Se prefiere gastar
  un derecho **limitado** con saldo antes que uno **ilimitado**, para no
  desperdiciar packs comprados. Sin derecho → `ENTITLEMENT_REQUIRED`.
- **Reserva = hold, no consumo**. Al reservar se coloca una **retención**
  (`RetenerCreditos`) de 1 crédito (1000 unidades); el ledger NO se toca todavía.
  Un derecho ilimitado no genera retención (`unidades = 0`).
- **Política de cancelación por hold**. `CancelarReserva`: a más de `horasLimite`
  (por defecto 6h) del inicio, **libera** la retención y el crédito vuelve; dentro
  de la ventana, la **confirma** (asienta el consumo en el ledger) como
  penalización. Cancelar algo ya cancelado es idempotente.
- **Idempotencia**. `idempotency_key` único; un reintento con la misma clave
  devuelve la reserva existente sin volver a descontar.
- **Códigos estables**. `BOOKING_NOT_OPEN`, `SESSION_NOT_BOOKABLE`,
  `CAPACITY_FULL` (409), `ALREADY_BOOKED` (409), `ENTITLEMENT_REQUIRED`, más
  `SALDO_INSUFICIENTE` si la retención pierde una carrera bajo el lock del derecho.

## Consecuencias

- El costo por sesión es fijo (1 crédito) por ahora; hacerlo configurable por
  oferta/producto es trabajo futuro.
- La invariante de no-sobreventa se prueba con tests de frontera (llenar el cupo y
  rechazar el siguiente). Un test de **carga con procesos en paralelo** pertenece a
  CI: bajo `RefreshDatabase` (transacción por test) no se puede intercalar dos
  conexiones reales dentro de la suite; el mecanismo de corrección es el
  `lockForUpdate` + conteo bajo lock, no el test in-suite.
- Waitlist (`en_espera` + promoción al cancelar) y asistencia llegan en Slice 7b.
- La penalización consume el crédito pero no crea cargo monetario; políticas por
  tenant/sucursal/actividad (BOOKING_ENGINE.md) se versionarán cuando se necesiten.
