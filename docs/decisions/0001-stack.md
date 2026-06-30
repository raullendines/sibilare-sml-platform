# ADR 0001: Use Laravel Backend And React Frontend

## Status

Accepted.

## Context

The platform needs dashboards, Apify ingestion, AI, reports, workers, cost control, permissions, snapshots and audit. This is more than a frontend-only application.

## Decision

Use a Laravel backend and a separate React + TypeScript frontend in a monorepo.

## Consequences

- Laravel owns business logic, workers, jobs, integrations, reports and authorization.
- React owns user experience and interactive UI.
- The system has more initial structure, but better long-term maintainability.

