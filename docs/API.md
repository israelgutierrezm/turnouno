# API

## Style

REST, versioned under `/api/v1`.

## Conventions

- JSON
- ULID public identifiers
- stable machine-readable error codes
- cursor pagination for large collections
- ISO-8601 timestamps
- explicit timezone handling
- idempotency keys for critical create operations
- OpenAPI documentation

## Authentication

Admin SPA:
- Sanctum session/cookie approach when same-site deployment permits.

Flutter:
- Sanctum tokens.

External integrations:
- scoped API keys initially.

## Initial resource groups

- auth
- me
- tenants/context
- branches
- people
- households
- members
- staff
- catalog
- resources
- membership-plans
- memberships
- entitlements
- sessions
- bookings
- waitlists
- attendance
- payments

## Example error

```json
{
  "code": "BOOKING_LEVEL_REQUIRED",
  "message": "The participant does not meet the required level.",
  "meta": {
    "required_level": "intermediate"
  }
}
```
