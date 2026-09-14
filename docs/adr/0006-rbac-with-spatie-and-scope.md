# ADR 0006 — RBAC con spatie/laravel-permission + scope

Estado: Aceptado (implementado en Slice 1 y Slice 2)

## Contexto

La autorización debe ser RBAC + scope (tenant / organización / sucursal) + frontera de
tenant (ver docs/AUTHORIZATION.md). El código de dominio verifica **permisos**, nunca
nombres de rol. Las verificaciones del frontend son cosméticas; el backend hace enforce.

## Decisión

Usar `spatie/laravel-permission` para el catálogo rol/permiso y la integración con el
Gate de Laravel, con su feature `teams` mapeando **team = tenant** para roles
tenant-scoped. La dimensión de **scope** por sucursal se añade como capa aditiva
(ver ADR-0008). `TenantContext` se resuelve temprano en el ciclo del request y nunca
se confía en la entrada del cliente como autoridad.

## Alternativas consideradas

- **RBAC totalmente propio** — control máximo del scope, pero reimplementa el caché, los
  gates y el catálogo de permisos que spatie ya provee de forma madura.

## Consecuencias

- Adoptamos las tablas y el caché de permisos de spatie; la capa de scope es nuestra y se
  cubre con pruebas de autorización.
- Las claves de caché y las consultas de rol/permiso deben permanecer tenant-aware.
