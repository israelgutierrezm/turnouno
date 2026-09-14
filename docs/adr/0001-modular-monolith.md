# ADR 0001 — Modular Monolith

Status: Accepted

We will begin with a modular monolith in Laravel.

Reasons:
- domain is complex but operational scale is not yet proven;
- transaction consistency matters for bookings and payments;
- simpler deployment and debugging;
- lower operational overhead;
- bounded contexts remain extractable later.

Rejected:
- microservices from day one.
