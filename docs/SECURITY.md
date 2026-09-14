# Security

## Critical threats

- tenant data leakage
- IDOR / broken object-level authorization
- privilege escalation
- insecure uploads
- webhook replay
- payment-state spoofing
- leaked secrets
- unsafe impersonation
- mass assignment

## Controls

- TenantContext + scoped authorization
- MFA-ready staff authentication
- strict request validation
- Laravel mass-assignment discipline
- rate limiting
- CSRF protection for SPA session flows
- HMAC webhooks with timestamp/replay protection
- signed URLs for private media
- encrypted secrets
- audit trails
- least privilege
- secure password/session policies
- security headers
- dependency scanning

## Minors

Only store information needed for operational, booking and sports-progress workflows.

Guardian relationships and document acceptance must be auditable.
