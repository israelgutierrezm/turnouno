# ADR 0010 — Agenda: sesiones materializadas, UTC y generación idempotente

Estado: Aceptado (Slice 6a)

## Contexto

El catálogo define la cadena `Oferta → Plantilla de horario → Sesión`
(DOMAIN_MODEL.md). Booking (Slice 7) reserva contra **sesiones concretas**: a
ellas se cuelgan capacidad, reservas, asistencia y staff. Hace falta decidir cómo
se representa la recurrencia, cómo se guardan las horas y cómo se evita duplicar
sesiones al (re)generar un rango.

## Decisiones

- **Sesiones materializadas** (no calculadas al vuelo). Una `PlantillaHorario` con
  una o más `ReglaRecurrencia` (día ISO 1-7 + hora local) se expande en filas
  `Sesion` concretas mediante el caso de uso `GenerarSesiones`. Una clase
  Lun/Mié son dos reglas de una misma plantilla.
- **Horas en UTC**. `sesiones.inicia_en`/`termina_en` se guardan en UTC,
  calculadas desde la hora local de la regla + la `zona_horaria` de la sucursal
  (`CarbonImmutable::parse(fecha+hora, zona)->utc()`). Se conserva `zona_horaria`
  como snapshot en la sesión para mostrar la hora local. La app corre en UTC
  (`app.timezone=UTC`), así que las comparaciones con `now()` (p. ej. la ventana
  de reserva) son correctas sin conversiones frágiles.
- **Generación idempotente**. Índice único `(plantilla_horario_id, inicia_en)` +
  `firstOrCreate`: regenerar el mismo rango no duplica sesiones. `GenerarSesiones`
  devuelve cuántas creó y respeta la vigencia de la plantilla (intersección con el
  rango pedido).
- **Sesiones ad-hoc**. Una sesión única (clases privadas, eventos) se crea con
  `plantilla_horario_id` nulo (`CrearSesionUnica`), misma conversión a UTC.
- **Cancelación**. `CancelarSesion` marca `estado = cancelada`; Booking rechazará
  reservas sobre sesiones no `programada`. La liberación de reservas ya existentes
  se resuelve en Slice 7.
- **Visibilidad de instructor**. La asignación de staff (`AsignacionSesion`)
  habilita `GET /mis-sesiones`: las próximas sesiones donde el usuario actual, a
  través de sus personas en el tenant, está asignado.

## Consecuencias

- Cambiar una plantilla no reescribe sesiones ya materializadas; regenerar solo
  agrega las faltantes. Editar/repropagar cambios masivos (mover una clase) queda
  como trabajo futuro.
- El conflicto de recursos (dos sesiones en la misma sala/carril solapadas) NO se
  valida aquí; es inventario/capacidad de Booking (Slice 7). `recurso_id` en la
  sesión es la referencia sobre la que ese chequeo operará.
- La generación es una acción administrativa de baja concurrencia; el índice único
  la hace segura ante ejecuciones repetidas. Rollover de horarios y edición de
  series se abordarán si el producto lo requiere.
