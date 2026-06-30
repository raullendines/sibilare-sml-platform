# Agent Instructions

These instructions are mandatory for Codex, Claude Code, Cursor, human contributors, and any other AI agent working in this repository.

## Product Intent

Sibilare SML Platform is a managed multi-client Social Media Listening platform. It is not a generic SaaS template. The platform must support client-specific dashboards, branding, topics, subproducts, competitors, AI texts, reports, chatbot limits, Apify extraction rules, costs, and operational controls.

## Required Branch Model

This repository follows a production-safe branch model:

- `main` is production.
- `staging` is the shared integration and acceptance branch.
- All new work must start from `staging`.
- All feature branches must be named `feature/<short-kebab-slug>`.
- Feature branches merge into `staging` only after review and verification.
- `staging` promotes to `main` only when production release is explicitly approved.

Agents must never commit, push, or implement feature work directly on `main` or `staging`.

## Required Workflow For New Work

When asked to start a new feature or make implementation changes:

1. Read this file and relevant docs under `docs/` and `guardrails/`.
2. Treat `main` as production and `staging` as integration.
3. Fetch remote state with pruning.
4. Ensure `origin/staging` exists. If it does not, create it from `origin/main` before feature work.
5. Update local `staging` with fast-forward only.
6. Create `feature/<short-kebab-slug>` from `staging`.
7. Implement only the requested scope.
8. Run the strongest practical checks for touched areas.
9. Leave integration into `staging` to the repository's end-feature flow unless explicitly requested.

This mirrors the local `$new-feature` skill in `/Users/raullendines/.codex/skills/new-feature/SKILL.md`.

## Required Workflow For Production

Production releases must follow the local `$add-to-production` skill in `/Users/raullendines/.codex/skills/add-to-production/SKILL.md`.

Rules:

- Never infer production approval from "feature done".
- Never release untested local changes.
- Never force-push, rewrite release history, bypass hooks, or use destructive git commands.
- Require both `origin/main` and `origin/staging`.
- Verify `origin/main` is an ancestor of `origin/staging`.
- Run release checks from `staging`.
- Promote only the verified `staging` SHA to `main`.
- If branch protection blocks direct push, prepare a PR from `staging` to `main`.

## Architecture Rules

- React presents. Laravel decides. PostgreSQL preserves. Workers process. Storage delivers.
- React must not call Apify, OpenAI, LLM providers, or storage signing endpoints directly.
- Business rules, tenant checks, usage limits, cost control, and audit decisions live in Laravel.
- Every client-owned record must be scoped by `client_id` directly or through a strictly validated parent.
- RLS can be used as defense in depth, but backend authorization is still mandatory.
- Long-running work must run through queues/workers, not web requests.
- Reports must be generated from snapshots, not mutable live data only.
- AI output must be traceable through prompt version, model, input, output, cost, and review state.

## Backend Rules

- Keep controllers thin.
- Put business logic in domain actions/services.
- Use Form Requests for validation.
- Use Policies or authorization services for permissions.
- Use Jobs for Apify, AI, reports, exports, notifications, and cost checks.
- Record usage in `usage_ledger` for cost-bearing operations.
- Do not expose provider secrets or service-role credentials.

## Frontend Rules

- Use React + TypeScript.
- Use typed API clients and avoid duplicating backend business logic.
- UI permission checks are for experience only; backend must enforce all permissions.
- Dashboard widgets must consume backend metrics, snapshots, or aggregate endpoints.
- Avoid direct SQL, direct Supabase table access, direct Apify calls, and direct LLM calls from the browser.

## Database Rules

- What is filtered, aggregated, billed, permissioned, or queried often belongs in real columns.
- Flexible visual configuration can use JSON, but references inside JSON must be validated.
- Raw third-party payloads can be stored for traceability, but normalized tables are the product source of truth.
- Never add cross-client references without explicit constraints or validation.

## Verification Expectations

Before handing off code, run relevant checks:

- Backend: `php artisan test`, `vendor/bin/pint --test` when applicable.
- Frontend: `npm run build`, lint/typecheck scripts when available.
- Database: migration review and rollback notes for risky changes.
- E2E/browser checks for UI behavior when practical.

If a check cannot be run, state why.

