# Review Checklist

Use this checklist for human and AI reviews.

## Product Fit

- Does the change support the managed SML service model?
- Does it preserve client personalization?
- Does it avoid hard-coded client-specific exceptions?

## Security

- Are tenant boundaries enforced?
- Are permissions checked in backend?
- Are secrets excluded?
- Are external calls backend-owned?

## Operations

- Are long-running tasks queued?
- Are failures observable?
- Are retries and statuses handled?
- Are costs recorded where needed?

## Data

- Is `client_id` handled safely?
- Are snapshots used for reproducible reports?
- Are queryable fields stored as columns?
- Are raw payloads separated from normalized data?

## Verification

- Backend tests run or reason documented.
- Frontend build/typecheck run or reason documented.
- Migration risk reviewed.
- Runtime smoke test done when UI behavior changed.

