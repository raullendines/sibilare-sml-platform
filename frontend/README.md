# SML Platform Frontend

React + TypeScript frontend for Sibilare SML Platform.

## Responsibilities

- Client dashboards.
- Benchmarking UI.
- Post exploration.
- Report and export workflows.
- Chatbot interface.
- Onboarding and configuration screens.
- Branding-aware client experience.

## Local Commands

```bash
npm install
npm run dev
npm run build
npm run lint
```

## Environment

Sibilare uses its own Supabase project for identity across the complete
scraping-to-dashboard product. The frontend and Laravel must point to that same
project. Plinth is only a visual interaction reference and is not an auth or
runtime dependency.

```dotenv
# frontend/.env
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_SUPABASE_URL=https://your-project.supabase.co
VITE_SUPABASE_PUBLISHABLE_KEY=your-publishable-key

# backend/.env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_PUBLISHABLE_KEY=your-publishable-key
```

The publishable key is safe for browser initialization. User access tokens are
sent only to Laravel, which validates the Supabase session and enforces client
roles.

## Editable Dashboards

The dashboard builder follows Plinth's compact workspace interaction:

- A 12-column draggable and resizable canvas.
- A Laravel-owned catalog of allowed metrics and widget templates.
- Contextual widget properties and global filters.
- Draft saving, client preview and immutable published versions.

Widgets query real normalized data through Laravel's batched metrics endpoint.
Global date, brand and platform filters are validated per client; the browser
does not execute SQL or query Supabase tables directly.

## Structure

- `src/app`: app shell and providers.
- `src/api`: API client and endpoint modules.
- `src/features`: product features.
- `src/components`: shared UI, charts, tables and layout.
- `src/hooks`: shared hooks.
- `src/lib`: framework-independent helpers.
- `src/types`: shared frontend types.

Business rules, provider calls, cost control and authorization decisions belong in the Laravel backend.
