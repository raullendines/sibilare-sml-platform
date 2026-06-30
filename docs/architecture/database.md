# Database Architecture

The database is PostgreSQL-first and multi-tenant.

## Stable Domains

- Clients, users, permissions and branding.
- Client configurations and onboarding.
- Plans, pricing, budgets and usage ledger.
- Platforms and extraction configuration.
- Brands, subbrands, competitors and aliases.
- Topics and subproducts.
- Platform posts and client post matches.
- Classification taxonomy and post classifications.
- AI prompt templates and AI generation runs.
- Dashboards, widget templates, metrics and snapshots.
- Exports, report templates, generated reports and presentations.
- Escalations, notifications and audit log.

## Multi-Tenant Integrity

The main risk is cross-client data leakage. Every client-owned record must either include `client_id` or inherit it from a parent that is strictly validated.

Rules:

- `brand_id` must belong to the same `client_id`.
- `theme_id` must belong to the same `client_id`.
- `subproduct_id` must belong to the same `client_id`.
- Dashboard widget config must not reference resources from another client.
- Report snapshots must store the client configuration version used.

## JSON Usage

Use JSON for flexible configuration and raw payloads. Do not hide important queryable concepts in JSON.

Columns should be used for:

- Billing.
- Filters.
- Permissions.
- Aggregations.
- Scores.
- Status.
- Dates.

