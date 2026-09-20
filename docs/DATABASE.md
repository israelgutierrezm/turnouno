# Base de Datos y Glosario

Fuente canónica del **esquema** y del **glosario de dominio**. Complementa
`DOMAIN_MODEL.md` (que describe los conceptos en inglés) mapeándolos a los
términos en español que usamos en el código y la base de datos.

## Convención de nombres

- **Dominio en español**, framework/estándares técnicos en inglés (ver la
  convención completa del proyecto). Sin acentos ni `ñ` en código ni en BD.
- Identificadores: **PK `BIGINT UNSIGNED`** interno + **`ulid`** público (ADR-0003).
- Dinero (futuro): `monto_minor BIGINT` + `moneda CHAR(3)`, nunca `float`.
- Motor **InnoDB** (transacciones y locks); charset `utf8mb4`.

## Glosario (concepto → término ES → tabla)

| Concepto (DOMAIN_MODEL, EN) | Término ES | Clase | Tabla |
|---|---|---|---|
| Tenant *(se conserva)* | Tenant | `Tenant` | `tenants` |
| User *(se conserva)* | Usuario (negocio) | `User` | `users` |
| Person | Persona | `Persona` | `personas` |
| Organization | Organización | `Organizacion` | `organizaciones` |
| Brand | Marca | `Marca` | `marcas` |
| Branch | Sucursal | `Sucursal` | `sucursales` |
| Staff assignment | Asignación de personal | `AsignacionPersonal` | `asignaciones_personal` |
| Member (perfil) | Miembro | `Miembro` | `miembros` |
| Membership (comercial) | Membresía | `Membresia` | `membresias` |
| Booking | Reserva | `Reserva` | `reservas` |
| Schedule template | Plantilla de horario | `PlantillaHorario` | `plantillas_horario` |
| Recurrence rule | Regla de recurrencia | `ReglaRecurrencia` | `reglas_recurrencia` |
| Session | Sesión | `Sesion` | `sesiones` |
| Session staff assignment | Asignación de sesión | `AsignacionSesion` | `asignaciones_sesion` |
| Resource | Recurso | `Recurso` | `recursos` |
| Attendance | Asistencia | `Asistencia` | `asistencias` |
| Order | Orden | `Orden` | `ordenes` |
| Order line | Línea de orden | `LineaOrden` | `lineas_orden` |
| Payment | Pago | `Pago` | `pagos` |

> `Membresia` (comercial) es distinta del vínculo **usuario ↔ tenant** (pivote
> `tenant_user`): este último NO es "membresía". Términos distintos, conceptos
> distintos.

## Esquema actual (hasta Slice 6)

### Plataforma / identidad *(nombres en inglés, conservados)*
- `tenants` (`id`, `ulid`, `name`, `slug`, `status`)
- `users` (`id`, `ulid`, `name`, `email`, `password`, …) — modelo de auth Laravel/Sanctum
- `tenant_user` (pivote: `tenant_id`, `user_id`, `status`) — vínculo usuario ↔ tenant
- spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
  `role_has_permissions` — con `tenant_id` como `team_foreign_key` (ADR-0006)

### Dominio *(nombres en español)*
- `personas` (`id`, `ulid`, `tenant_id`, `user_id?`, `nombre`, `apellidos?`, `email?`, `fecha_nacimiento?`)
- `perfiles` (`id`, `ulid`, `tenant_id`, `persona_id`, `tipo`) — roles de una persona (miembro, instructor, …); `unique(persona_id, tipo)`
- `organizaciones` (`id`, `ulid`, `tenant_id`, `nombre`, `slug`)
- `marcas` (`id`, `ulid`, `tenant_id`, `organizacion_id`, `nombre`, `slug`)
- `sucursales` (`id`, `ulid`, `tenant_id`, `marca_id`, `nombre`, `slug`, `zona_horaria?`, `estado`)
- `asignaciones_personal` (`id`, `ulid`, `tenant_id`, `sucursal_id`, `user_id`, `role_id`)
- `programas` (`id`, `ulid`, `tenant_id`, `nombre`, `slug`)
- `actividades` (`id`, `ulid`, `tenant_id`, `programa_id`, `nombre`, `slug`)
- `niveles` (`id`, `ulid`, `tenant_id`, `actividad_id`, `nombre`, `orden`)
- `ofertas` (`id`, `ulid`, `tenant_id`, `actividad_id`, `nombre`, `modalidad`, `capacidad?`)
- `instalaciones` (`id`, `ulid`, `tenant_id`, `sucursal_id`, `nombre`)
- `recursos` (`id`, `ulid`, `tenant_id`, `instalacion_id`, `recurso_padre_id?`, `nombre`, `tipo?`, `modo`, `capacidad`, `estado`)
- `productos_comerciales` (`id`, `ulid`, `tenant_id`, `nombre`, `tipo`, `precio_minor`, `moneda`, `ilimitado`, `creditos_incluidos?`, `actividad_id?`, `sucursal_id?`, `politica_reset`, `unidades_por_ciclo?`, `politica_rollover`, `rollover_max?`) — plantilla de ciclo/rollover/restricciones (ADR-0017)
- `acuerdos` (`id`, `ulid`, `tenant_id`, `persona_id`, `producto_comercial_id`, `linea_orden_id?`, `fecha_inicio`, `estado`) — `linea_orden_id` traza el origen comercial (nulo en venta directa; permite revertir el fulfillment al reembolsar)
- `derechos` (`id`, `ulid`, `tenant_id`, `acuerdo_id`, `ambito`, `actividad_id?`, `sucursal_id?`, `ilimitado`, `politica_reset`, `unidades_por_ciclo?`, `politica_rollover`, `rollover_max?`, `ciclo_inicio?`, `ciclo_fin?`, `valido_desde?`, `valido_hasta?`) — entitlement con ciclo/rollover/restricciones (ADR-0017)
- `movimientos_credito` (`id`, `ulid`, `tenant_id`, `derecho_id`, `tipo`, `unidades`, `descripcion?`) — ledger
- `retenciones_credito` (`id`, `ulid`, `tenant_id`, `derecho_id`, `unidades`, `estado`, `descripcion?`) — holds (reservas de crédito concurrency-safe)
- `plantillas_horario` (`id`, `ulid`, `tenant_id`, `oferta_id`, `sucursal_id`, `recurso_id?`, `nombre?`, `duracion_minutos`, `capacidad?`, `vigente_desde`, `vigente_hasta?`, `activa`) — definición recurrente de una clase
- `reglas_recurrencia` (`id`, `ulid`, `tenant_id`, `plantilla_horario_id`, `dia_semana`, `hora_inicio`) — repetición semanal (día ISO + hora local); `unique(plantilla, dia_semana, hora_inicio)`
- `sesiones` (`id`, `ulid`, `tenant_id`, `plantilla_horario_id?`, `oferta_id`, `sucursal_id`, `recurso_id?`, `inicia_en`, `termina_en`, `zona_horaria`, `capacidad?`, `estado`) — instancia fechada; horas en UTC; `unique(plantilla, inicia_en)` (materialización idempotente, ADR-0010)
- `asignaciones_sesion` (`id`, `ulid`, `tenant_id`, `sesion_id`, `persona_id`, `rol`) — staff (instructor/asistente) de una sesión; `unique(sesion_id, persona_id)`
- `reservas` (`id`, `ulid`, `tenant_id`, `sesion_id`, `persona_id`, `derecho_id`, `retencion_id?`, `estado`, `unidades`, `idempotency_key?`) — booking; `idempotency_key` único; `index(sesion_id, estado)`
- `asistencias` (`id`, `ulid`, `tenant_id`, `reserva_id`, `estado`, `registrada_en`) — check-in de una reserva confirmada; `unique(reserva_id)`
- `ordenes` (`id`, `ulid`, `tenant_id`, `persona_id`, `estado`, `total_minor`, `moneda`) — orden de compra (comprador = `persona_id`)
- `lineas_orden` (`id`, `ulid`, `tenant_id`, `orden_id`, `producto_comercial_id`, `beneficiario_id?`, `cantidad`, `precio_unitario_minor`, `subtotal_minor`) — `beneficiario` = participante (puede diferir del comprador)
- `pagos` (`id`, `ulid`, `tenant_id`, `orden_id`, `proveedor`, `metodo?`, `estado`, `monto_minor`, `moneda`, `referencia_externa?`, `idempotency_key?`, `comprobante_ruta?`, `comprobante_subido_en?`) — intento de cobro; `idempotency_key` único; `comprobante_*` = depósito en ventanilla
- `configuraciones_pasarela` (`id`, `ulid`, `tenant_id`, `proveedor`, `activa`, `modo`, `credenciales?`) — config de pasarela por tenant; `credenciales` **cifradas**; `unique(tenant_id, proveedor)` (ADR-0014)

> `TipoPerfil` (enum): `miembro`, `instructor`, `personal`, `lead`, `cliente`.
> `ModoRecurso` (enum): `unidad`, `pool`. `ModalidadOferta` (enum): `grupal`, `privada`.
> `TipoProducto`: `membresia`, `paquete`, `pase_dia`, `sesion_individual`, `add_on`, `taller`.
> `TipoMovimiento`: `concesion`, `consumo`, `ajuste`, `add_on`, `reverso`, `expiracion`.
> `PoliticaReset`: `ninguno`, `calendario`, `aniversario`. `PoliticaRollover`: `ninguno`, `completo`, `limitado`.
> `EstadoRetencion`: `activa`, `consumida`, `liberada`, `perdida`.
> `EstadoSesion`: `programada`, `cancelada`, `finalizada`. `RolSesion`: `instructor`, `asistente`.
> `EstadoReserva`: `confirmada`, `en_espera` (waitlist), `cancelada`.
> `EstadoAsistencia`: `presente`, `ausente`.
> `EstadoOrden`: `pendiente`, `pagada`, `cancelada`. `EstadoPago`: `pendiente`, `aprobado`, `rechazado`, `reembolsado`.
> `ProveedorPasarela`: `manual`, `simulada` (integrados), `stripe`, `openpay`, `mercadopago`, `ventanilla` (configurables). `MetodoPago`: `tarjeta`, `oxxo`, `spei`, `efectivo`, `ventanilla`.
> **Comercio**: una orden se paga vía una **pasarela** (proveedor-agnóstica); el pago
> aprobado hace el *fulfillment* (concede los derechos) atómicamente (ADR-0012).
> `DiaSemana` (int ISO-8601): `1`=lunes … `7`=domingo.
> **Agenda**: `sesiones.inicia_en`/`termina_en` se guardan en UTC (calculadas desde la
> hora local de la plantilla + `zona_horaria` de la sucursal); se conserva la zona
> como snapshot para mostrar. Las sesiones se **materializan** desde las plantillas
> (ADR-0010).
> **Dinero**: `precio_minor BIGINT` + `moneda`. **Créditos**: enteros escalados (1 crédito
> = 1000 unidades). El saldo de un derecho se DERIVA del ledger, nunca se guarda (ADR-0009).
> **Disponible** = `saldo` (suma del ledger) − suma de retenciones `activa`. Un hold reserva
> cupo sin mover el ledger; al confirmarlo se registra el `consumo` y el hold pasa a `consumida`.

Todas las tablas de dominio llevan `tenant_id` y son *tenant-scoped* (ADR-0007).
