# Editable Dashboard Architecture

Dashboards combine a Meltwater-style social listening catalog with a
Metabase-style editable canvas and a compact Plinth/Notion-inspired interface.

## Ownership

- React edits layout and presentation configuration.
- Laravel validates templates, metrics, filters, permissions and tenant scope.
- PostgreSQL stores drafts and immutable published snapshots.
- `POST /metrics/query` resolves approved metrics in batches; React never submits SQL.

## Data Model

- `metric_definitions`: stable backend-owned metrics.
- `widget_templates`: reusable starting configurations.
- `dashboards`: client-owned dashboard identity and publication state.
- `dashboard_widgets`: layout, visualization and validated configuration.
- `dashboard_filters`: global filter controls.
- `dashboard_versions`: immutable publication snapshots.

## Safety Rules

- Every dashboard record carries `client_id`.
- Layout saves reject widget and filter identifiers from another dashboard.
- Brand and platform references in JSON are validated against the client.
- Data widgets require an active metric definition.
- Metric queries accept only Laravel-owned codes and client-scoped brand and platform IDs.
- Grid positions must fit the dashboard column count.
- Publishing creates a versioned snapshot before client delivery.

## API

- `GET /api/v1/widget-templates`
- `GET /api/v1/clients/{client}/platforms`
- `POST /api/v1/clients/{client}/metrics/query`
- `GET|POST /api/v1/clients/{client}/dashboards`
- `GET|PATCH /api/v1/clients/{client}/dashboards/{dashboard}`
- `PUT /api/v1/clients/{client}/dashboards/{dashboard}/layout`
- `POST /api/v1/clients/{client}/dashboards/{dashboard}/publish`
- `GET /api/v1/clients/{client}/dashboards/{dashboard}/versions`
