# ADR 0004 — Transactional Outbox

Status: Accepted for critical asynchronous events

Critical domain changes that require asynchronous side effects should persist an outbox event in the same transaction.

Workers dispatch:
- notifications
- analytics updates
- automations
- external webhooks

This avoids coupling transaction success to Redis/provider availability.
