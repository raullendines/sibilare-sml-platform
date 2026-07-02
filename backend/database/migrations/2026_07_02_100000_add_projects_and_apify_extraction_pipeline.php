<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
create table public.projects (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    name text not null,
    slug text not null,
    description text,
    status text not null default 'active',
    default_data_frequency text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint projects_status_check check (status in ('active', 'archived')),
    constraint projects_frequency_check check (default_data_frequency is null or default_data_frequency in ('daily', 'weekly', 'monthly')),
    constraint projects_client_slug_unique unique (client_id, slug),
    constraint projects_id_client_unique unique (id, client_id)
);

create table public.project_brands (
    project_id uuid not null,
    brand_id uuid not null,
    client_id uuid not null references public.clients(id) on delete cascade,
    created_at timestamptz not null default now(),
    primary key (project_id, brand_id),
    constraint project_brands_project_client_fk foreign key (project_id, client_id)
        references public.projects(id, client_id) on delete cascade,
    constraint project_brands_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete cascade
);

alter table public.posts
    add constraint posts_id_client_unique unique (id, client_id);

create table public.project_posts (
    project_id uuid not null,
    post_id uuid not null,
    client_id uuid not null references public.clients(id) on delete cascade,
    extraction_run_id uuid,
    created_at timestamptz not null default now(),
    primary key (project_id, post_id),
    constraint project_posts_project_client_fk foreign key (project_id, client_id)
        references public.projects(id, client_id) on delete cascade,
    constraint project_posts_post_client_fk foreign key (post_id, client_id)
        references public.posts(id, client_id) on delete cascade,
    constraint project_posts_run_client_fk foreign key (extraction_run_id, client_id)
        references public.extraction_runs(id, client_id) on delete set null (extraction_run_id)
);

alter table public.dashboards
    add column project_id uuid;

alter table public.dashboards
    add constraint dashboards_project_client_fk foreign key (project_id, client_id)
        references public.projects(id, client_id) on delete set null (project_id);

alter table public.extraction_configs
    add column project_id uuid,
    add column query_fingerprint text;

alter table public.extraction_configs
    add constraint extraction_configs_project_client_fk foreign key (project_id, client_id)
        references public.projects(id, client_id) on delete cascade;

update public.extraction_configs
set retroactive_days = 3;

alter table public.extraction_configs
    alter column retroactive_days set default 3;

alter table public.extraction_jobs
    add column frequency_type text,
    add column overlap_days integer not null default 3,
    add column period_start timestamptz,
    add column period_end timestamptz,
    add column fetch_start timestamptz,
    add column fetch_end timestamptz,
    add column reserved_cost_usd numeric(12, 4) not null default 0,
    add column completed_at timestamptz;

update public.extraction_jobs set status = 'pending' where status = 'running';

alter table public.extraction_jobs drop constraint extraction_jobs_status_check;
alter table public.extraction_jobs
    add constraint extraction_jobs_status_check check (status in (
        'pending', 'locked', 'launching', 'waiting_provider', 'finalizing',
        'completed', 'failed', 'cancelled', 'skipped'
    )),
    add constraint extraction_jobs_frequency_check check (frequency_type is null or frequency_type in ('daily', 'weekly', 'monthly')),
    add constraint extraction_jobs_overlap_check check (overlap_days = 3),
    add constraint extraction_jobs_reserved_cost_check check (reserved_cost_usd >= 0),
    add constraint extraction_jobs_period_check check (
        period_start is null or period_end is null or (
            period_start < period_end and fetch_start <= period_start and fetch_end = period_end
        )
    );

create unique index extraction_jobs_config_period_unique
    on public.extraction_jobs (extraction_config_id, period_start, period_end)
    where period_start is not null and period_end is not null;

alter table public.extraction_runs
    add column external_run_id text,
    add column dataset_id text,
    add column attempt_number integer not null default 1,
    add column fetch_start timestamptz,
    add column fetch_end timestamptz,
    add column compute_units numeric(14, 6),
    add column usage_cost_usd numeric(12, 6),
    add column billed_cost_usd numeric(12, 6),
    add column charged_event_counts jsonb not null default '{}'::jsonb,
    add column pricing_snapshot jsonb not null default '{}'::jsonb,
    add column abort_reason text,
    add column guardrails_hit jsonb not null default '[]'::jsonb,
    add column webhook_received_at timestamptz,
    add column finalization_started_at timestamptz;

alter table public.extraction_runs alter column cost_amount type numeric(12, 6);
alter table public.usage_ledger alter column cost_amount type numeric(12, 6);

update public.extraction_runs set status = 'waiting_provider' where status = 'running';

alter table public.extraction_runs drop constraint extraction_runs_status_check;
alter table public.extraction_runs
    add constraint extraction_runs_status_check check (status in (
        'starting', 'waiting_provider', 'finalizing', 'success', 'failed',
        'partial', 'cancelled', 'aborted', 'timed_out'
    )),
    add constraint extraction_runs_attempt_positive check (attempt_number > 0),
    add constraint extraction_runs_costs_nonnegative check (
        (compute_units is null or compute_units >= 0)
        and (usage_cost_usd is null or usage_cost_usd >= 0)
        and (billed_cost_usd is null or billed_cost_usd >= 0)
    );

create unique index extraction_runs_external_unique
    on public.extraction_runs (external_run_id)
    where external_run_id is not null;

alter table public.apify_agents
    add column billing_model text not null default 'per_item',
    add column pricing_unit text,
    add column pricing_details jsonb not null default '{}'::jsonb,
    add column input_schema jsonb not null default '{}'::jsonb,
    add column output_schema jsonb not null default '{}'::jsonb,
    add column supports_webhook boolean not null default true,
    add column actor_options jsonb not null default '{}'::jsonb,
    add column max_items_limit integer;

alter table public.apify_agents
    add constraint apify_agents_billing_model_check check (billing_model in ('per_run', 'per_item', 'per_event', 'compute_unit')),
    add constraint apify_agents_max_items_positive check (max_items_limit is null or max_items_limit > 0);

create index projects_client_status_idx on public.projects (client_id, status);
create index project_brands_client_brand_idx on public.project_brands (client_id, brand_id);
create index project_posts_client_project_idx on public.project_posts (client_id, project_id);
create index extraction_configs_project_idx on public.extraction_configs (project_id) where project_id is not null;
create index extraction_jobs_dispatch_idx on public.extraction_jobs (status, next_retry_at, scheduled_for);
create index extraction_runs_waiting_idx on public.extraction_runs (status, started_at) where status in ('waiting_provider', 'finalizing');

create trigger projects_set_updated_at
    before update on public.projects
    for each row execute function public.set_updated_at();

alter table public.projects enable row level security;
alter table public.project_brands enable row level security;
alter table public.project_posts enable row level security;
SQL);
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
drop index if exists public.extraction_runs_waiting_idx;
drop index if exists public.extraction_runs_external_unique;
drop index if exists public.extraction_jobs_dispatch_idx;
drop index if exists public.extraction_jobs_config_period_unique;
drop index if exists public.extraction_configs_project_idx;

alter table public.apify_agents
    drop constraint if exists apify_agents_max_items_positive,
    drop constraint if exists apify_agents_billing_model_check,
    drop column if exists max_items_limit,
    drop column if exists actor_options,
    drop column if exists supports_webhook,
    drop column if exists output_schema,
    drop column if exists input_schema,
    drop column if exists pricing_details,
    drop column if exists pricing_unit,
    drop column if exists billing_model;

alter table public.extraction_runs
    drop constraint if exists extraction_runs_costs_nonnegative,
    drop constraint if exists extraction_runs_attempt_positive,
    drop constraint if exists extraction_runs_status_check,
    drop column if exists finalization_started_at,
    drop column if exists webhook_received_at,
    drop column if exists guardrails_hit,
    drop column if exists abort_reason,
    drop column if exists pricing_snapshot,
    drop column if exists charged_event_counts,
    drop column if exists billed_cost_usd,
    drop column if exists usage_cost_usd,
    drop column if exists compute_units,
    drop column if exists fetch_end,
    drop column if exists fetch_start,
    drop column if exists attempt_number,
    drop column if exists dataset_id,
    drop column if exists external_run_id;

alter table public.extraction_runs alter column cost_amount type numeric(12, 4);
alter table public.usage_ledger alter column cost_amount type numeric(12, 4);

update public.extraction_runs set status = 'running' where status in ('starting', 'waiting_provider', 'finalizing');
update public.extraction_runs set status = 'failed' where status in ('aborted', 'timed_out');

alter table public.extraction_runs
    add constraint extraction_runs_status_check check (status in ('running', 'success', 'failed', 'partial', 'cancelled'));

alter table public.extraction_jobs
    drop constraint if exists extraction_jobs_period_check,
    drop constraint if exists extraction_jobs_reserved_cost_check,
    drop constraint if exists extraction_jobs_overlap_check,
    drop constraint if exists extraction_jobs_frequency_check,
    drop constraint if exists extraction_jobs_status_check,
    drop column if exists completed_at,
    drop column if exists reserved_cost_usd,
    drop column if exists fetch_end,
    drop column if exists fetch_start,
    drop column if exists period_end,
    drop column if exists period_start,
    drop column if exists overlap_days,
    drop column if exists frequency_type;

update public.extraction_jobs set status = 'running' where status in ('launching', 'waiting_provider', 'finalizing');

alter table public.extraction_jobs
    add constraint extraction_jobs_status_check check (status in ('pending', 'locked', 'running', 'completed', 'failed', 'cancelled', 'skipped'));

alter table public.extraction_configs
    drop constraint if exists extraction_configs_project_client_fk,
    drop column if exists query_fingerprint,
    drop column if exists project_id;

alter table public.dashboards
    drop constraint if exists dashboards_project_client_fk,
    drop column if exists project_id;

drop table if exists public.project_posts;
alter table public.posts drop constraint if exists posts_id_client_unique;
drop table if exists public.project_brands;
drop table if exists public.projects;
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The Apify extraction pipeline migration requires PostgreSQL/Supabase.');
        }
    }
};
