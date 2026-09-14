# Architecture

## Style

Modular monolith.

Bounded contexts/modules are separated in code but deployed initially as one Laravel application.

## Backend layers

For complex modules:

- Domain
  - Entities
  - ValueObjects
  - Policies
  - Events
  - Contracts
- Application
  - Commands
  - Queries
  - UseCases
- Infrastructure
  - Persistence
  - ExternalProviders
  - Queue
- Http
  - Controllers
  - Requests
  - Resources

Use pragmatism. Simple reference/configuration modules do not need all layers.

## Eventing

Important side effects should follow:

DB transaction
→ domain/application event
→ transactional outbox
→ worker
→ notifications / analytics / automations / webhooks

Do not introduce Kafka initially.

## Storage

- MySQL: source of truth for transactional data
- Redis: queue, locks, cache, rate limits
- S3-compatible object storage: media/documents
- Search abstraction: MySQL initially, optional Meilisearch/Elasticsearch later

## Frontend

Admin and portal use Vue 3 + TypeScript.

Business rules remain in API/domain. Frontend owns:
- presentation;
- user interaction;
- local UI state;
- optimistic UX only when safe.

## Mobile

Flutter consumes the same versioned API as web clients.
Mobile must not have a parallel implementation of critical business rules.

## Observability

Every request/job should support a correlation ID.

Plan for:
- structured logs
- failed job visibility
- slow query monitoring
- error tracking
- metrics
- audit trail
