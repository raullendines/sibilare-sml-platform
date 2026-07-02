# Infrastructure

This folder is reserved for deployment, Docker, environment examples and operational scripts.

Initial target architecture:

- Laravel web service.
- Laravel queue workers.
- Laravel scheduler.
- React static frontend.
- Managed PostgreSQL.
- Managed Redis.
- Private storage bucket.

Required Laravel process types:

- `web`: Laravel API.
- `scheduler`: one replica running `php artisan schedule:work`, or infrastructure cron invoking `php artisan schedule:run` every minute.
- `worker`: Redis-backed workers for `extractions,default`; Laravel Horizon is recommended for production supervision.

The Apify webhook URL must be public HTTPS. Scheduler and workers share PostgreSQL and the same distributed cache so `onOneServer`, overlap locks and job claims remain effective.

Do not commit secrets or production `.env` files here.
