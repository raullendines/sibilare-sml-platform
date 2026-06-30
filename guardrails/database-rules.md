# Database Rules

## Multi-Tenant Safety

- Every client-owned table must include `client_id` or inherit it from a validated parent.
- Cross-client references are forbidden.
- JSON config that references client resources must be validated by backend.
- RLS is defense in depth, not a replacement for backend authorization.

## Schema Design

- Queryable and billable fields belong in columns.
- Status fields should use controlled values.
- Store raw provider payloads separately from normalized product data.
- Reports and dashboards that need reproducibility must use snapshots.

## Migrations

- Review destructive changes explicitly.
- Provide rollback notes for risky migrations.
- Avoid long locks on large tables.

