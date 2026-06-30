# ADR 0002: Main, Staging And Feature Branches

## Status

Accepted.

## Context

The repository will be edited by humans and AI agents. Production must not be changed accidentally.

## Decision

- `main` is production.
- `staging` is integration.
- Feature work starts from `staging` in `feature/*` branches.
- Promotion to production happens only from verified `staging`.

## Consequences

- Feature work is isolated.
- Production releases require explicit approval.
- AI agents have a repeatable branch protocol.

