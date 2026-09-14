# ADR 0002 — Shared Database Multi-Tenancy

Status: Accepted for MVP

Use shared database/shared schema with explicit tenant_id.

Tenant is the security boundary.
Branch is an operational unit inside a tenant.

Dedicated databases may be added for enterprise tenants later behind a data source resolver.
