# ADR 0003 — Identifiers

Status: Accepted

Use:
- BIGINT UNSIGNED internal primary keys
- ULID public identifiers

Reasons:
- efficient MySQL indexing
- non-sequential public IDs
- easier future distributed workflows
