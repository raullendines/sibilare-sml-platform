# Apify Ingestion Architecture

Apify is a controlled dependency. It must be observable, limited and replaceable.

## Required Concepts

- `apify_agents`: available actors by platform with priority.
- `extraction_configs`: what should be extracted.
- `extraction_jobs`: scheduled work.
- `extraction_runs`: actual executions.
- `usage_ledger`: cost record.
- `cost_budgets`: soft and hard limits.

## Flow

1. Scheduler creates jobs from active extraction configs.
2. Worker locks a job.
3. Worker checks client status, platform enablement and cost budget.
4. Worker runs primary Apify agent.
5. If needed, worker runs fallback agent by priority.
6. Worker normalizes posts.
7. Worker stores raw payloads and normalized records.
8. Worker records cost and usage.
9. Worker queues classification.

## Rules

- Never call Apify from frontend.
- Every run must have status, cost, duration and error details.
- Every fallback must record source agent and reason.
- Every run must enforce max posts and cost limit.

