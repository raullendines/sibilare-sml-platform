# Technical Stack

## Recommended Stack

- Backend: Laravel.
- Frontend: React + TypeScript + Vite.
- Database: PostgreSQL, preferably managed through Supabase or another managed Postgres service.
- Queue/cache: Redis.
- Workers: Laravel queue workers with Horizon when added.
- Scheduler: Laravel Scheduler.
- Storage: S3-compatible storage or Supabase Storage.
- Charts: ECharts or Recharts.
- Tables: TanStack Table.
- Server state: TanStack Query.
- Backend tests: Pest or PHPUnit.
- Frontend tests: Vitest and React Testing Library.
- E2E: Playwright.
- Observability: Sentry plus structured logs.

## Core Principle

React presents. Laravel decides. PostgreSQL preserves. Workers process. Storage delivers.

## Why Not React Only

The platform needs backend-owned business logic:

- Apify jobs and fallback.
- Cost control.
- AI calls and prompt audit.
- Report generation.
- Chatbot limits.
- Permissions and audit.
- Snapshots.
- Notifications and escalations.

This should not live in the browser.

