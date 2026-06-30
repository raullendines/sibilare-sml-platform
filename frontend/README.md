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

## Structure

- `src/app`: app shell and providers.
- `src/api`: API client and endpoint modules.
- `src/features`: product features.
- `src/components`: shared UI, charts, tables and layout.
- `src/hooks`: shared hooks.
- `src/lib`: framework-independent helpers.
- `src/types`: shared frontend types.

Business rules, provider calls, cost control and authorization decisions belong in the Laravel backend.

