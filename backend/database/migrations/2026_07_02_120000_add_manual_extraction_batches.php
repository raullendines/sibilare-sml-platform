<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
create table public.extraction_batches (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    project_id uuid,
    requested_by_client_user_id uuid references public.client_users(id) on delete set null,
    status text not null default 'queued',
    total_jobs integer not null default 0,
    pending_jobs integer not null default 0,
    active_jobs integer not null default 0,
    completed_jobs integer not null default 0,
    failed_jobs integer not null default 0,
    skipped_jobs integer not null default 0,
    reserved_cost_usd numeric(12, 4) not null default 0,
    usage_cost_usd numeric(12, 6) not null default 0,
    billed_cost_usd numeric(12, 6) not null default 0,
    launched_at timestamptz not null default now(),
    finished_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint extraction_batches_status_check check (status in ('queued', 'running', 'completed', 'partial', 'failed')),
    constraint extraction_batches_project_client_fk foreign key (project_id, client_id)
        references public.projects(id, client_id) on delete set null (project_id)
);

create table public.extraction_batch_jobs (
    extraction_batch_id uuid not null references public.extraction_batches(id) on delete cascade,
    extraction_job_id uuid not null references public.extraction_jobs(id) on delete cascade,
    client_id uuid not null references public.clients(id) on delete cascade,
    created_at timestamptz not null default now(),
    primary key (extraction_batch_id, extraction_job_id)
);

create index extraction_batches_client_launched_idx on public.extraction_batches (client_id, launched_at desc);
create index extraction_batch_jobs_client_job_idx on public.extraction_batch_jobs (client_id, extraction_job_id);

create trigger extraction_batches_set_updated_at
    before update on public.extraction_batches
    for each row execute function public.set_updated_at();
SQL);
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
drop trigger if exists extraction_batches_set_updated_at on public.extraction_batches;
drop index if exists public.extraction_batch_jobs_client_job_idx;
drop index if exists public.extraction_batches_client_launched_idx;
drop table if exists public.extraction_batch_jobs;
drop table if exists public.extraction_batches;
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The manual extraction batch migration requires PostgreSQL.');
        }
    }
};
