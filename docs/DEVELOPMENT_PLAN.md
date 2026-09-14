# Development Plan

## Rule

Do not begin by creating all database tables.

Build complete vertical slices.

## Sprint 0 / Foundation Slice

1. Create monorepo folders.
2. Create Laravel API.
3. Configure MySQL + Redis.
4. Install Sanctum.
5. Install Laravel Boost for AI-assisted Laravel context.
6. Create Vue admin shell.
7. Create Vue portal shell.
8. Create Flutter shell.
9. Add linting/formatting/testing.
10. Add CI skeleton.
11. Add health endpoint.
12. Add correlation ID middleware.
13. Add architecture tests where useful.

## Slice 1 — Tenant + User

Goal:
An authenticated platform user can operate inside one tenant.

Implement:
- tenants
- users
- persons
- user-person link
- TenantContext
- tenant membership
- permissions foundation
- `/api/v1/me`

Tests:
- cross-tenant access forbidden
- tenant context in web request
- tenant context in queued job

## Slice 2 — Organization + Branch

Implement:
- organizations
- brands
- branches
- branch scopes
- staff assignment

Build admin screens:
- organization settings
- branch list
- staff access

## Slice 3 — People + Household

Implement:
- person
- member profile
- guardian relationship
- household
- dependent

Build:
- member list
- member detail
- family view

## Slice 4 — Catalog + Resources

Implement:
- program
- activity
- level
- offering
- facility
- resource
- availability basics

Build:
- activity admin
- resource tree

## Slice 5 — Membership + Entitlements

Implement:
- commercial product
- membership plan/version
- agreement
- entitlement definition/grant
- ledger
- cycle/reset
- add-on purchase

Tests must cover:
- unlimited
- limited
- activity-restricted
- branch-restricted
- calendar reset
- anniversary reset
- top-up

## Slice 6 — Scheduling

Implement:
- schedule template
- recurrence rule
- session
- session staff assignments
- instructor visibility

## Slice 7 — Booking

Implement:
- booking window
- eligibility resolver
- entitlement resolver
- capacity inventory
- transactional booking
- idempotency
- cancellation
- waitlist basic
- attendance

This slice is not complete until concurrent booking tests pass.

## Slice 8 — Pilot scenarios

Seed three demonstration tenants and automate acceptance tests against them.

Do not proceed to growth features until all three pilot scenarios pass.
