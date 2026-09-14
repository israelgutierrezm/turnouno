# Membership SaaS — Claude Code Project Instructions

## Mission

Build a commercial, multi-tenant SaaS for membership-, class-, booking-, resource- and activity-based businesses.

Initial pilot verticals:
- Pole studios
- Swimming schools
- Gyms

The product MUST use one configurable core. Do not create separate applications or duplicated domain logic per industry.

## Technology

Backend:
- Laravel 13
- PHP 8.3+
- MySQL
- Redis
- REST API
- Laravel Sanctum
- Queues / Jobs / Events / Listeners
- Scheduler
- Object storage compatible with S3

Admin web:
- Vue 3
- Composition API
- TypeScript
- Vite
- Tailwind CSS
- Pinia
- Vue Router

Mobile:
- Flutter
- Feature-first architecture
- MVVM-inspired presentation
- Repository + service data layer
- Optional use-case/domain layer where complexity justifies it

Infrastructure:
- Docker for development parity
- Redis for cache, queues, locks and rate limiting
- S3-compatible storage
- Horizontal-scaling friendly
- CI/CD ready

## Architectural Style

Use a modular monolith.

Do NOT:
- create microservices prematurely;
- put all models in app/Models;
- put all business logic in Controllers;
- put critical business logic in Vue or Flutter;
- add industry checks such as `if ($industry === 'swimming')`;
- add tenant-specific code;
- use EAV for core entities;
- use float for money or credits;
- maintain balances without ledgers;
- expose sequential database IDs in public APIs unless explicitly approved;
- perform heavy processing synchronously;
- trust frontend permission checks;
- create migrations before the domain for the requested module is understood.

Backend modules live under:
`apps/api/app/Modules/<ModuleName>/`

Each complex module may contain:
- Domain/
- Application/
- Infrastructure/
- Http/

Simple CRUD does not need ceremonial DDD.

## Mandatory Domain Principles

1. Tenant is the SaaS security and billing boundary.
2. Organization/Brand/Branch live inside a tenant.
3. A tenant may contain multiple branches.
4. Person != User != Member != Guardian != Instructor.
5. Purchaser != participant.
6. Memberships, packs, add-ons and makeups grant entitlements.
7. Booking validates eligibility, booking window, entitlements, capacity and resources.
8. Credits use an auditable ledger.
9. Reservations must be concurrency-safe.
10. Payments are provider-agnostic.
11. Domain events should support an outbox pattern for important asynchronous side effects.
12. Configuration, policies and commercial plans that affect historical behavior should be versioned where appropriate.
13. Feature flags, plan entitlements, tenant capabilities and industry profiles are distinct concepts.

## Initial Domain Modules

Foundation:
- Identity
- Tenancy
- Organizations
- Authorization
- Configuration
- Audit
- Media

Customer:
- People
- Households
- CRM

Commerce:
- Catalog
- Memberships
- Entitlements
- Credits
- Orders
- Payments

Operations:
- Resources
- Scheduling
- Bookings
- Attendance
- Access
- Workforce

Later:
- Documents
- Communications
- Automations
- Progress
- Reports
- Analytics
- Integrations
- PlatformBilling
- PlatformAdmin

## Public Identifiers

Default:
- internal PK: BIGINT UNSIGNED
- public ID: ULID

Never let public API consumers depend on internal auto-increment IDs unless approved.

## Money

Never use float.

Preferred representation:
- `amount_minor BIGINT`
- `currency CHAR(3)`

Example:
89900 MXN = MXN 899.00.

## Credits

Never store only `credits_available`.

Use ledger entries and, when reservation semantics require it, holds/reservations.

Support fractional business credits without floating point, using scaled integer units.

Example:
- 1000 units = 1 credit
- 500 units = 0.5 credit

## Authentication

First-party SPA:
- Sanctum cookie/session auth when architecture permits.

Mobile:
- Sanctum token authentication.

Third-party integrations:
- API keys initially.
- OAuth when delegated third-party access is actually required.

## Authorization

Use RBAC plus scope.

A permission is not enough:
- permission
- tenant
- scope (organization/branch/etc.)
must be considered.

Examples:
- `members.view`
- `members.create`
- `bookings.create`
- `attendance.mark`
- `payments.refund`

Do not authorize by role name in domain code when a permission/policy check is possible.

## Multi-tenancy

Initial strategy:
- shared application
- shared database
- shared schema
- explicit tenant_id on tenant-owned data

Defense in depth:
- TenantContext
- tenant-aware repositories/queries
- authorization policies
- tenant-aware unique constraints
- tenant-aware FK strategy where useful
- isolation tests
- audit logs

Do not implement dedicated databases in MVP, but do not make the architecture impossible to evolve.

## Testing Rules

Every critical domain change requires tests.

Critical suites:
- tenant isolation
- membership activation
- entitlement grants
- credit consumption
- booking capacity/concurrency
- cancellation/refund of entitlement
- waitlist promotion
- payment webhook idempotency
- authorization scopes
- attendance

Prefer feature/integration tests around real domain flows over excessive mocking.

## Workflow for Claude Code

Before coding a new domain module:

1. Read relevant docs under `/docs`.
2. Summarize the requested behavior.
3. Identify domain invariants.
4. List affected entities and boundaries.
5. Identify concurrency/idempotency/security risks.
6. Propose implementation plan.
7. Only then modify code.
8. Add/update tests.
9. Update docs/ADR when architecture changes.

For risky architectural decisions, stop and explain trade-offs before committing to a design.

Do not generate dozens of placeholder classes. Prefer small vertical slices that are fully implemented and tested.
