# Coding Rules

## General

- Keep changes focused.
- Do not introduce unrelated refactors.
- Prefer explicit domain concepts over generic catch-all helpers.
- Avoid secrets, tokens and `.env` files in commits.
- Add tests for risky or shared behavior.

## Backend

- Controllers should be thin.
- Put business workflows in actions/services.
- Use jobs for long-running tasks.
- Use policies for authorization.
- Use typed request validation.
- Log important operations with client and correlation context.

## Frontend

- Use TypeScript.
- Keep API calls centralized.
- Use TanStack Query for server state when added.
- Do not duplicate backend authorization logic as source of truth.
- Do not call third-party providers directly from browser code.

