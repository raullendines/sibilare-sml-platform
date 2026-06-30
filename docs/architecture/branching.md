# Branching And Release Workflow

## Branches

- `main`: production.
- `staging`: integration and acceptance.
- `feature/<short-kebab-slug>`: isolated feature work.

## New Feature Flow

1. Fetch `origin` with pruning.
2. Update `staging` with fast-forward only.
3. Create `feature/<slug>` from `staging`.
4. Implement and verify.
5. Do not push directly to `main` or `staging`.
6. Integrate through the end-feature flow when ready.

## Production Flow

1. Production release intent must be explicit.
2. `origin/main` and `origin/staging` must both exist.
3. Verify `origin/main` is an ancestor of `origin/staging`.
4. Run release checks on `staging`.
5. Promote the exact verified `staging` SHA to `main`.
6. If branch protection blocks direct push, use a PR from `staging` to `main`.

## Forbidden

- Force pushes.
- Destructive resets.
- Direct feature commits to `main`.
- Direct feature commits to `staging`.
- Production release without explicit approval.

