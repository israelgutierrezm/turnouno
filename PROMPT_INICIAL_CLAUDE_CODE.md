# Initial Claude Code Prompt

You are the principal implementation agent for a commercial SaaS named provisionally Membership SaaS.

Before writing code, read:
- CLAUDE.md
- README.md
- docs/PRODUCT.md
- docs/ARCHITECTURE.md
- docs/DOMAIN_MODEL.md
- docs/TENANCY.md
- docs/MEMBERSHIP_ENGINE.md
- docs/BOOKING_ENGINE.md
- docs/RESOURCE_ENGINE.md
- docs/AUTHORIZATION.md
- docs/API.md
- docs/SECURITY.md
- docs/MOBILE.md
- docs/MVP.md
- docs/ROADMAP.md
- docs/PILOTS.md
- docs/DEVELOPMENT_PLAN.md
- docs/adr/*

We are building:
- Laravel 13 API backend
- Vue 3 + TypeScript + Vite + Tailwind admin web
- Vue 3 + TypeScript + Vite + Tailwind customer portal
- MySQL
- Redis
- Flutter mobile app

The architecture is a modular monolith.

The same core MUST operate:
1. a pole studio;
2. a swimming school;
3. a gym.

Do not create industry-specific forks.

Important rules:
- Person != User != Member != Guardian != Instructor.
- Tenant is the SaaS security boundary.
- Branches live inside tenants.
- Commercial products grant entitlements.
- Credits use ledger entries.
- Booking priority windows must support memberships booking earlier than packs/drop-ins.
- Membership allowances may be limited or unlimited.
- Add-on/top-up classes are separate grants, not edits to the plan.
- Entitlement cycles must support calendar-month and anniversary behavior.
- A session may have multiple instructors/assistants and configurable public visibility.
- Booking must be concurrency safe and idempotent.
- Money and credits must never use floating point.
- Backend owns critical business logic.
- Frontend/mobile consume explainable API errors.
- RBAC must support branch-scoped access.
- No tenant-specific code.

FIRST TASK ONLY:

Do not implement the entire product.

1. Inspect the repository and documentation.
2. Propose the exact bootstrap plan for Sprint 0 and Slice 1.
3. Show the intended folder structure.
4. List packages you recommend and justify each one.
5. Identify which packages should NOT be added yet.
6. Confirm the Laravel/PHP/Vue/Node/Flutter versions available in the environment.
7. Create or adjust the monorepo skeleton only after reviewing the plan.
8. Bootstrap:
   - Laravel API
   - MySQL/Redis configuration
   - Sanctum
   - Laravel Boost in development
   - Vue admin
   - Vue portal
   - Flutter app
9. Add baseline linting, formatting and tests.
10. Add a `/api/v1/health` endpoint.
11. Add correlation ID middleware.
12. Implement no business-domain tables beyond the minimum needed for the foundation unless explicitly justified.
13. Run all available tests/builds and report results.

Before making an architectural decision not covered by the documentation, explain the trade-off and prefer the smallest reversible choice.

Do not generate placeholder classes for every future domain.
We will build the application vertical slice by vertical slice.
