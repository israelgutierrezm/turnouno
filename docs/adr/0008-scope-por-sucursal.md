# ADR 0008 — Autorización con scope por sucursal

Estado: Aceptado (Slice 2)

## Contexto

La autorización es RBAC + scope (tenant / organización / sucursal) + frontera de
tenant. spatie con `teams = tenant` (ADR-0006) resuelve el nivel tenant, pero no
el scope por sucursal (p. ej. `recepcionista` solo en la sucursal Roma).

## Decisión

Capa **aditiva** sobre spatie, sin reescribir su integración con el Gate:

- spatie mantiene el catálogo rol → permiso y las asignaciones **tenant-wide**
  (Slice 1).
- El scope por sucursal se modela con `asignaciones_personal`
  (`sucursal_id`, `user_id`, `role_id`).
- `ControlDeAcceso::permiteEnSucursal($user, $permiso, $sucursal)` concede si:
  1. el permiso es tenant-wide (`$user->can($permiso)`), o
  2. existe una asignación de personal en esa sucursal cuyo rol otorga el permiso.

## Alternativas consideradas

- **Tabla única de asignaciones** con `scope_type`/`scope_id` que reemplace
  `model_has_roles`: exigiría un resolutor de permisos propio y perder la
  integración madura de spatie con el Gate.

## Consecuencias

- Cuando existan recursos por sucursal (Slice 3+), su autorización usará
  `permiteEnSucursal`.
- Cubierto por pruebas: un rol tenant-wide aplica en cualquier sucursal; un rol
  con scope de sucursal aplica solo en la suya.
