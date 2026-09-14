# Multi-Tenancy

## Definition

Tenant = independent SaaS customer boundary.

Branches are not tenants.

Example:

Platform
├── Tenant: Pole House
│   ├── Branch Roma
│   ├── Branch Condesa
│   └── Branch Polanco
└── Tenant: AquaKids
    ├── Branch Coyoacán
    └── Branch Del Valle

## MVP persistence

Shared database, shared schema, tenant_id on tenant-owned rows.

## Required isolation controls

- Resolve TenantContext early in request lifecycle.
- Never accept tenant_id from normal client payload as authority.
- Repositories/queries are tenant aware.
- Authorization also validates scope.
- Use tenant-aware uniqueness.
- Add explicit cross-tenant isolation tests.
- Audit security-sensitive operations.
- Background jobs must carry tenant context explicitly.
- Cache keys must be tenant namespaced.
- Object-storage paths must be tenant namespaced.

## Future enterprise evolution

Allow a future TenantDataSourceResolver:
- shared
- dedicated

Do not implement dedicated databases for MVP.
