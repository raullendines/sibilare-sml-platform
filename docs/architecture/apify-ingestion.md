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

1. Laravel Scheduler creates one idempotent job per active extraction config and calendar period.
2. Queue launchers claim pending jobs with `FOR UPDATE SKIP LOCKED`.
3. Laravel checks client status, project-brand ownership, platform enablement and USD cost reservations.
4. The launcher starts the primary Apify actor with an authenticated ad-hoc webhook and immediately releases the worker.
5. The webhook queues finalization; the watchdog polls runs whose webhook is delayed or missing.
6. Finalization reads terminal billing data and downloads the dataset.
7. Laravel normalizes and deduplicates posts, then records project visibility.
8. Every actor attempt records billed cost and diagnostic usage independently.
9. A terminal failure requeues the extraction with the next active actor by priority.

## Calendar Windows

- Frequencies are `daily`, `weekly` and `monthly`.
- Precedence is extraction config, owning project, then client subscription.
- Jobs run at 03:00 in the client timezone.
- The contractual period is calendar-aligned and the provider fetch window starts three days earlier.
- `period_start`/`period_end` remain separate from `fetch_start`/`fetch_end` so overlap never changes reporting periods.

## Project Visibility

- A config without `project_id` is shared and maps matching posts to every project containing the brand.
- A config with `project_id` is project-specific and only creates visibility for that project.
- `platform_posts` are provider-level records and are never duplicated per client or project.

## Runtime Processes

- One scheduler process invokes `php artisan schedule:run` every minute.
- Queue workers consume the `extractions` queue; Redis/Horizon is the production target.
- `extractions:schedule`, `extractions:dispatch` and `extractions:watchdog` are owned by Laravel Scheduler.
- Running more than one scheduler replica requires Laravel's shared cache lock; all schedules use `onOneServer` and `withoutOverlapping`.

## Rules

- Never call Apify from frontend.
- Every run must have status, cost, duration and error details.
- Every fallback must record source agent and reason.
- Every run must enforce max posts and cost limit.
- `billed_cost_usd` is calculated from `chargedEventCounts`; `usage_cost_usd` remains diagnostic.
- TikTok stays inactive until a bounded canary validates its actor contract.
