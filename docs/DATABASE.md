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
| Household | Hogar | `Hogar` | `hogares` |
| Guardian | Tutor | `Tutor` | `tutores` |
| Membership (comercial) | Membresía | `Membresia` | `membresias` |
| Booking | Reserva | `Reserva` | `reservas` |
| Session | Sesión | `Sesion` | `sesiones` |
| Resource | Recurso | `Recurso` | `recursos` |
| Attendance | Asistencia | `Asistencia` | `asistencias` |

> `Membresia` (comercial) es distinta del vínculo **usuario ↔ tenant** (pivote
> `tenant_user`): este último NO es "membresía". Términos distintos, conceptos
> distintos.

## Esquema actual (Slice 1 + Slice 2)

### Plataforma / identidad *(nombres en inglés, conservados)*
- `tenants` (`id`, `ulid`, `name`, `slug`, `status`)
- `users` (`id`, `ulid`, `name`, `email`, `password`, …) — modelo de auth Laravel/Sanctum
- `tenant_user` (pivote: `tenant_id`, `user_id`, `status`) — vínculo usuario ↔ tenant
- spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
  `role_has_permissions` — con `tenant_id` como `team_foreign_key` (ADR-0006)

### Dominio *(nombres en español)*
- `personas` (`id`, `ulid`, `tenant_id`, `user_id?`, `hogar_id?`, `nombre`, `apellidos?`, `email?`, `fecha_nacimiento?`)
- `perfiles` (`id`, `ulid`, `tenant_id`, `persona_id`, `tipo`) — roles de una persona (miembro, tutor, …); `unique(persona_id, tipo)`
- `hogares` (`id`, `ulid`, `tenant_id`, `nombre`)
- `tutelas` (`id`, `ulid`, `tenant_id`, `tutor_id`, `dependiente_id`, `parentesco?`) — tutor → dependiente
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
- `productos_comerciales` (`id`, `ulid`, `tenant_id`, `nombre`, `tipo`, `precio_minor`, `moneda`, `ilimitado`, `creditos_incluidos?`)
- `acuerdos` (`id`, `ulid`, `tenant_id`, `persona_id`, `producto_comercial_id`, `fecha_inicio`, `estado`)
- `derechos` (`id`, `ulid`, `tenant_id`, `acuerdo_id`, `ambito`, `ilimitado`, `valido_desde?`, `valido_hasta?`) — entitlement
- `movimientos_credito` (`id`, `ulid`, `tenant_id`, `derecho_id`, `tipo`, `unidades`, `descripcion?`) — ledger
- `retenciones_credito` (`id`, `ulid`, `tenant_id`, `derecho_id`, `unidades`, `estado`, `descripcion?`) — holds (reservas de crédito concurrency-safe)

> `TipoPerfil` (enum): `miembro`, `tutor`, `instructor`, `personal`, `lead`, `cliente`.
> Un menor (dependiente) puede no tener `user_id` (sin cuenta de acceso).
> `ModoRecurso` (enum): `unidad`, `pool`. `ModalidadOferta` (enum): `grupal`, `privada`.
> `TipoProducto`: `membresia`, `paquete`, `pase_dia`, `sesion_individual`, `add_on`, `taller`.
> `TipoMovimiento`: `concesion`, `consumo`, `ajuste`, `add_on`, `reverso`.
> `EstadoRetencion`: `activa`, `consumida`, `liberada`, `perdida`.
> **Dinero**: `precio_minor BIGINT` + `moneda`. **Créditos**: enteros escalados (1 crédito
> = 1000 unidades). El saldo de un derecho se DERIVA del ledger, nunca se guarda (ADR-0009).
> **Disponible** = `saldo` (suma del ledger) − suma de retenciones `activa`. Un hold reserva
> cupo sin mover el ledger; al confirmarlo se registra el `consumo` y el hold pasa a `consumida`.

Todas las tablas de dominio llevan `tenant_id` y son *tenant-scoped* (ADR-0007).
