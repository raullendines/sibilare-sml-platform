# Sibilare SML Platform

Sibilare SML Platform is a managed Social Media Listening platform for multiple clients. It is not intended to be a generic SaaS. Each client can have custom branding, dashboards, topics, subproducts, AI-generated text, reports, chatbot scope, competitors, and data extraction rules.

## Stack

- `backend/`: Laravel API, queues, scheduler, workers, business rules, integrations, reports, AI, Apify, usage and cost control.
- `frontend/`: React + TypeScript + Vite app for dashboards, benchmarking, configuration, exports, reports and chatbot UI.
- Database: PostgreSQL/Supabase.
- Queue/cache: Redis.
- Storage: S3-compatible storage or Supabase Storage.

## Repository Workflow

This repository uses a production-safe branch model:

- `main`: production branch.
- `staging`: integration and acceptance branch.
- `feature/<short-kebab-name>`: isolated feature branches created from `staging`.

Feature work must not be committed directly to `main` or `staging`. See [AGENTS.md](./AGENTS.md) and [docs/architecture/branching.md](./docs/architecture/branching.md).

## Local Setup

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

## Documentation Map

- Product context: [docs/product/vision.md](./docs/product/vision.md)
- Technical stack: [docs/architecture/stack.md](./docs/architecture/stack.md)
- Database model: [docs/architecture/database.md](./docs/architecture/database.md)
- Branch workflow: [docs/architecture/branching.md](./docs/architecture/branching.md)
- AI repo rules: [AGENTS.md](./AGENTS.md)
- Guardrails: [guardrails/review-checklist.md](./guardrails/review-checklist.md)

