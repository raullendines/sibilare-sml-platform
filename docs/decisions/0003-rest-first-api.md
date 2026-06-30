# ADR 0003: REST First API

## Status

Accepted for the initial platform.

## Context

The platform has many command-style operations: run extraction, generate report, export widget, send chatbot message, review classification and update configuration.

## Decision

Start with REST JSON under `/api/v1`. Consider GraphQL later only for dashboard or analytics use cases if REST becomes inefficient.

## Consequences

- Simpler permissions, caching and observability.
- Clear command endpoints.
- Lower initial complexity.

