# SML Platform Backend

Laravel backend for Sibilare SML Platform.

## Responsibilities

- API for the React frontend.
- Authentication and authorization.
- Multi-tenant business rules.
- Apify extraction orchestration.
- AI calls and prompt audit.
- Report and export generation.
- Queue jobs and scheduled tasks.
- Usage ledger and cost control.
- Audit log and notifications.

## Local Commands

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Tests

```bash
php artisan test
vendor/bin/pint --test
```

## Extraction Runtime

Laravel owns extraction scheduling and orchestration:

```bash
php artisan schedule:work
php artisan horizon
```

Production should run the web API, one scheduler replica and Redis-backed queue workers as separate processes. Configure `APIFY_TOKEN`, `APIFY_WEBHOOK_SECRET` and a public HTTPS `APIFY_WEBHOOK_URL`. TikTok is intentionally inactive until its actor passes a bounded canary.

## Domain Structure

Domain folders live under `app/Domain`. Keep controllers thin and move use-case logic into domain actions or services.

Initial domains:

- `Clients`
- `Users`
- `Brands`
- `Platforms`
- `Extraction`
- `Posts`
- `Classification`
- `AI`
- `Chatbot`
- `Dashboards`
- `Reports`
- `Exports`
- `Billing`
- `Usage`
- `Notifications`
- `Audit`
