create extension if not exists pgcrypto;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

create table public.clients (
    id uuid primary key default gen_random_uuid(),
    name text not null,
    slug text not null unique,
    status text not null default 'onboarding',
    industry text,
    default_locale text not null default 'es-ES',
    timezone text not null default 'Europe/Madrid',
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint clients_status_check check (status in ('onboarding', 'active', 'paused', 'churned'))
);

create table public.client_branding (
    client_id uuid primary key references public.clients(id) on delete cascade,
    logo_url text,
    logo_dark_url text,
    favicon_url text,
    color_primary text,
    color_secondary text,
    color_accent text,
    font_family text,
    custom_css text,
    updated_at timestamptz not null default now()
);

create table public.client_users (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    auth_user_id uuid not null references auth.users(id) on delete cascade,
    role text not null default 'viewer',
    invited_at timestamptz,
    accepted_at timestamptz,
    disabled_at timestamptz,
    created_at timestamptz not null default now(),
    constraint client_users_role_check check (role in ('owner', 'admin', 'editor', 'analyst', 'viewer')),
    constraint client_users_client_auth_unique unique (client_id, auth_user_id),
    constraint client_users_id_client_unique unique (id, client_id)
);

create table public.client_permissions (
    id uuid primary key default gen_random_uuid(),
    client_user_id uuid not null references public.client_users(id) on delete cascade,
    permission_code text not null,
    enabled boolean not null default true,
    created_at timestamptz not null default now(),
    constraint client_permissions_code_unique unique (client_user_id, permission_code)
);

create table public.client_configurations (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    version_number integer not null,
    status text not null default 'draft',
    name text not null,
    notes text,
    created_by_user_id uuid references public.client_users(id) on delete set null,
    activated_by_user_id uuid references public.client_users(id) on delete set null,
    created_at timestamptz not null default now(),
    activated_at timestamptz,
    archived_at timestamptz,
    constraint client_configurations_status_check check (status in ('draft', 'active', 'archived')),
    constraint client_configurations_version_unique unique (client_id, version_number),
    constraint client_configurations_id_client_unique unique (id, client_id)
);

create unique index client_configurations_one_active_per_client
    on public.client_configurations (client_id)
    where status = 'active';

create table public.client_configuration_items (
    id uuid primary key default gen_random_uuid(),
    client_configuration_id uuid not null references public.client_configurations(id) on delete cascade,
    item_type text not null,
    item_id uuid not null,
    change_type text not null,
    notes text,
    constraint client_configuration_items_type_check check (
        item_type in ('brand', 'theme', 'subproduct', 'dashboard', 'prompt', 'report_template', 'extraction_config')
    ),
    constraint client_configuration_items_change_check check (
        change_type in ('created', 'updated', 'removed', 'unchanged')
    )
);

create table public.onboarding_projects (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    status text not null default 'not_started',
    owner_user_id uuid references public.client_users(id) on delete set null,
    started_at timestamptz,
    completed_at timestamptz,
    notes text,
    constraint onboarding_projects_status_check check (status in ('not_started', 'in_progress', 'blocked', 'completed'))
);

create table public.onboarding_tasks (
    id uuid primary key default gen_random_uuid(),
    onboarding_project_id uuid not null references public.onboarding_projects(id) on delete cascade,
    task_code text not null,
    title text not null,
    status text not null default 'pending',
    assigned_to_user_id uuid references public.client_users(id) on delete set null,
    due_at timestamptz,
    completed_at timestamptz,
    notes text,
    constraint onboarding_tasks_status_check check (status in ('pending', 'in_progress', 'blocked', 'done')),
    constraint onboarding_tasks_project_code_unique unique (onboarding_project_id, task_code)
);

create table public.client_subscriptions (
    client_id uuid primary key references public.clients(id) on delete cascade,
    default_data_frequency text not null default 'weekly',
    default_retroactive_days integer not null default 3,
    default_max_posts_per_period integer not null,
    competitor_analysis_enabled boolean not null default false,
    ai_chatbot_enabled boolean not null default false,
    ai_pattern_detection_enabled boolean not null default false,
    client_presentations_enabled boolean not null default false,
    monthly_message_limit integer,
    monthly_price numeric(12, 2),
    price_basis text,
    billing_cycle text not null default 'monthly',
    contract_start date,
    contract_end date,
    updated_at timestamptz not null default now(),
    constraint client_subscriptions_frequency_check check (default_data_frequency in ('daily', 'weekly', 'monthly')),
    constraint client_subscriptions_billing_cycle_check check (billing_cycle in ('monthly', 'quarterly', 'annual')),
    constraint client_subscriptions_retroactive_nonnegative check (default_retroactive_days >= 0),
    constraint client_subscriptions_max_posts_positive check (default_max_posts_per_period > 0)
);

create table public.client_plan_items (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    item_type text not null,
    item_name text not null,
    quantity numeric(12, 2) not null default 1,
    unit_price numeric(12, 2),
    multiplier numeric(12, 4) not null default 1,
    monthly_price numeric(12, 2),
    notes text,
    created_at timestamptz not null default now(),
    constraint client_plan_items_type_check check (
        item_type in ('platform', 'competitor', 'own_brand', 'chatbot', 'reports', 'presentations', 'ai_patterns', 'custom_service')
    )
);

create table public.platforms (
    id uuid primary key default gen_random_uuid(),
    code text not null unique,
    name text not null,
    is_active boolean not null default true,
    constraint platforms_code_check check (code in ('x', 'instagram', 'tiktok', 'youtube', 'news'))
);

create table public.client_platforms (
    client_id uuid not null references public.clients(id) on delete cascade,
    platform_id uuid not null references public.platforms(id) on delete restrict,
    enabled boolean not null default true,
    enabled_at timestamptz,
    disabled_at timestamptz,
    primary key (client_id, platform_id)
);

create table public.brands (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    parent_brand_id uuid,
    name text not null,
    brand_type text not null,
    logo_url text,
    color text,
    website_url text,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint brands_type_check check (
        brand_type in ('own_brand', 'own_subbrand', 'competitor', 'competitor_subbrand')
    ),
    constraint brands_id_client_unique unique (id, client_id),
    constraint brands_parent_same_client foreign key (parent_brand_id, client_id)
        references public.brands(id, client_id) on delete restrict
);

create table public.brand_aliases (
    id uuid primary key default gen_random_uuid(),
    brand_id uuid not null references public.brands(id) on delete cascade,
    platform_id uuid references public.platforms(id) on delete restrict,
    alias_type text not null,
    value text not null,
    is_primary boolean not null default false,
    created_at timestamptz not null default now(),
    constraint brand_aliases_type_check check (alias_type in ('handle', 'keyword', 'hashtag', 'domain', 'query_term')),
    constraint brand_aliases_unique unique (brand_id, platform_id, alias_type, value)
);

create table public.brand_platform_volume_estimates (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    brand_id uuid not null,
    platform_id uuid not null references public.platforms(id) on delete restrict,
    estimated_monthly_mentions integer not null,
    source text not null default 'manual',
    confidence numeric(4, 3),
    suggested_tier text,
    suggested_multiplier numeric(12, 4),
    notes text,
    created_at timestamptz not null default now(),
    constraint brand_platform_volume_estimates_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete cascade,
    constraint brand_platform_volume_estimates_source_check check (source in ('meltwater', 'manual', 'historical', 'other')),
    constraint brand_platform_volume_estimates_confidence_check check (confidence is null or (confidence >= 0 and confidence <= 1)),
    constraint brand_platform_volume_estimates_mentions_nonnegative check (estimated_monthly_mentions >= 0)
);

create table public.themes (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    client_configuration_id uuid not null,
    name text not null,
    description text,
    color text,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    constraint themes_configuration_client_fk foreign key (client_configuration_id, client_id)
        references public.client_configurations(id, client_id) on delete restrict,
    constraint themes_id_client_unique unique (id, client_id)
);

create table public.subproducts (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    client_configuration_id uuid not null,
    theme_id uuid,
    name text not null,
    description text,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    constraint subproducts_configuration_client_fk foreign key (client_configuration_id, client_id)
        references public.client_configurations(id, client_id) on delete restrict,
    constraint subproducts_theme_client_fk foreign key (theme_id, client_id)
        references public.themes(id, client_id) on delete restrict
);

create table public.apify_agents (
    id uuid primary key default gen_random_uuid(),
    platform_id uuid not null references public.platforms(id) on delete restrict,
    name text not null,
    actor_id text not null,
    is_primary boolean not null default false,
    priority integer not null default 100,
    cost_per_run_estimate numeric(12, 4),
    cost_per_item_estimate numeric(12, 6),
    is_active boolean not null default true,
    last_used_at timestamptz,
    created_at timestamptz not null default now(),
    constraint apify_agents_actor_unique unique (platform_id, actor_id)
);

create table public.extraction_configs (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    brand_id uuid not null,
    platform_id uuid not null references public.platforms(id) on delete restrict,
    search_query text not null,
    frequency text,
    retroactive_days integer not null default 3,
    max_posts_per_run integer not null,
    selection_strategy text not null default 'most_relevant',
    cost_limit_per_run numeric(12, 4),
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint extraction_configs_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete cascade,
    constraint extraction_configs_client_platform_fk foreign key (client_id, platform_id)
        references public.client_platforms(client_id, platform_id) on delete restrict,
    constraint extraction_configs_frequency_check check (frequency is null or frequency in ('daily', 'weekly', 'monthly')),
    constraint extraction_configs_strategy_check check (selection_strategy in ('most_relevant', 'most_recent', 'engagement_weighted')),
    constraint extraction_configs_retroactive_nonnegative check (retroactive_days >= 0),
    constraint extraction_configs_max_posts_positive check (max_posts_per_run > 0),
    constraint extraction_configs_id_client_unique unique (id, client_id)
);

create table public.extraction_jobs (
    id uuid primary key default gen_random_uuid(),
    extraction_config_id uuid not null,
    client_id uuid not null references public.clients(id) on delete cascade,
    scheduled_for timestamptz not null,
    status text not null default 'pending',
    locked_at timestamptz,
    locked_by text,
    retry_count integer not null default 0,
    max_retries integer not null default 3,
    next_retry_at timestamptz,
    created_at timestamptz not null default now(),
    constraint extraction_jobs_config_client_fk foreign key (extraction_config_id, client_id)
        references public.extraction_configs(id, client_id) on delete cascade,
    constraint extraction_jobs_status_check check (status in ('pending', 'locked', 'running', 'completed', 'failed', 'cancelled', 'skipped')),
    constraint extraction_jobs_retry_nonnegative check (retry_count >= 0 and max_retries >= 0),
    constraint extraction_jobs_id_client_unique unique (id, client_id)
);

create table public.extraction_runs (
    id uuid primary key default gen_random_uuid(),
    extraction_job_id uuid,
    extraction_config_id uuid not null,
    client_id uuid not null references public.clients(id) on delete cascade,
    brand_id uuid not null,
    platform_id uuid not null references public.platforms(id) on delete restrict,
    agent_id uuid references public.apify_agents(id) on delete set null,
    fallback_from_agent_id uuid references public.apify_agents(id) on delete set null,
    fallback_reason text,
    frequency_type text,
    period_start timestamptz,
    period_end timestamptz,
    status text not null default 'running',
    input_payload jsonb not null default '{}'::jsonb,
    result_summary jsonb not null default '{}'::jsonb,
    posts_requested integer,
    posts_fetched integer,
    posts_stored integer,
    posts_discarded integer,
    cost_amount numeric(12, 4),
    currency text not null default 'EUR',
    error_code text,
    error_message text,
    started_at timestamptz,
    finished_at timestamptz,
    created_at timestamptz not null default now(),
    constraint extraction_runs_job_client_fk foreign key (extraction_job_id, client_id)
        references public.extraction_jobs(id, client_id) on delete restrict,
    constraint extraction_runs_config_client_fk foreign key (extraction_config_id, client_id)
        references public.extraction_configs(id, client_id) on delete restrict,
    constraint extraction_runs_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete restrict,
    constraint extraction_runs_frequency_check check (frequency_type is null or frequency_type in ('daily', 'weekly', 'monthly')),
    constraint extraction_runs_status_check check (status in ('running', 'success', 'failed', 'partial', 'cancelled')),
    constraint extraction_runs_counts_nonnegative check (
        (posts_requested is null or posts_requested >= 0)
        and (posts_fetched is null or posts_fetched >= 0)
        and (posts_stored is null or posts_stored >= 0)
        and (posts_discarded is null or posts_discarded >= 0)
    ),
    constraint extraction_runs_id_client_unique unique (id, client_id)
);

create table public.cost_budgets (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    scope_type text not null,
    brand_id uuid,
    platform_id uuid references public.platforms(id) on delete restrict,
    feature_code text,
    period text not null,
    soft_limit_amount numeric(12, 4),
    hard_limit_amount numeric(12, 4),
    currency text not null default 'EUR',
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    constraint cost_budgets_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete cascade,
    constraint cost_budgets_scope_check check (scope_type in ('client', 'brand', 'platform', 'brand_platform', 'feature')),
    constraint cost_budgets_feature_check check (feature_code is null or feature_code in ('apify', 'chatbot', 'reports', 'ai_classification')),
    constraint cost_budgets_period_check check (period in ('monthly', 'weekly', 'daily'))
);

create table public.usage_ledger (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    usage_type text not null,
    source_table text,
    source_id uuid,
    brand_id uuid,
    platform_id uuid references public.platforms(id) on delete restrict,
    quantity numeric(14, 4) not null default 1,
    unit text not null,
    cost_amount numeric(12, 4),
    currency text not null default 'EUR',
    occurred_at timestamptz not null default now(),
    metadata jsonb not null default '{}'::jsonb,
    constraint usage_ledger_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete restrict,
    constraint usage_ledger_type_check check (
        usage_type in ('apify_run', 'post_classification', 'chatbot_message', 'report_generation', 'export', 'ai_insight')
    ),
    constraint usage_ledger_unit_check check (unit in ('run', 'post', 'message', 'token', 'file', 'euro')),
    constraint usage_ledger_quantity_nonnegative check (quantity >= 0)
);

create table public.client_data_availability (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    brand_id uuid not null,
    platform_id uuid not null references public.platforms(id) on delete restrict,
    data_starts_at timestamptz,
    historical_backfill_available boolean not null default false,
    coverage_note text,
    created_at timestamptz not null default now(),
    constraint client_data_availability_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete cascade,
    constraint client_data_availability_unique unique (client_id, brand_id, platform_id)
);

create table public.platform_posts (
    id uuid primary key default gen_random_uuid(),
    platform_id uuid not null references public.platforms(id) on delete restrict,
    external_id text not null,
    author_handle text,
    author_name text,
    content_text text,
    url text,
    posted_at timestamptz,
    language_code text,
    media_urls text[] not null default array[]::text[],
    metrics jsonb not null default '{}'::jsonb,
    raw_payload jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint platform_posts_platform_external_unique unique (platform_id, external_id)
);

create table public.posts (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    brand_id uuid not null,
    platform_post_id uuid not null references public.platform_posts(id) on delete cascade,
    extraction_run_id uuid,
    matched_query text,
    match_type text not null,
    is_relevant_candidate boolean not null default true,
    created_at timestamptz not null default now(),
    constraint posts_brand_client_fk foreign key (brand_id, client_id)
        references public.brands(id, client_id) on delete restrict,
    constraint posts_extraction_run_client_fk foreign key (extraction_run_id, client_id)
        references public.extraction_runs(id, client_id) on delete restrict,
    constraint posts_match_type_check check (match_type in ('brand', 'alias', 'keyword', 'competitor', 'manual')),
    constraint posts_client_platform_post_unique unique (client_id, platform_post_id, brand_id)
);

create index clients_status_idx on public.clients (status);
create index client_users_auth_user_idx on public.client_users (auth_user_id);
create index client_permissions_code_idx on public.client_permissions (permission_code);
create index onboarding_projects_client_status_idx on public.onboarding_projects (client_id, status);
create index onboarding_tasks_project_status_idx on public.onboarding_tasks (onboarding_project_id, status);
create index client_plan_items_client_idx on public.client_plan_items (client_id);
create index cost_budgets_client_active_idx on public.cost_budgets (client_id, is_active);
create index usage_ledger_client_occurred_idx on public.usage_ledger (client_id, occurred_at desc);
create index usage_ledger_type_idx on public.usage_ledger (usage_type);
create index client_platforms_enabled_idx on public.client_platforms (client_id, enabled);
create index brands_client_type_idx on public.brands (client_id, brand_type);
create index brands_parent_idx on public.brands (parent_brand_id);
create index brand_aliases_value_idx on public.brand_aliases (value);
create index brand_volume_client_brand_platform_idx on public.brand_platform_volume_estimates (client_id, brand_id, platform_id);
create index themes_client_config_idx on public.themes (client_id, client_configuration_id);
create index subproducts_client_theme_idx on public.subproducts (client_id, theme_id);
create index apify_agents_platform_priority_idx on public.apify_agents (platform_id, priority);
create index extraction_configs_client_active_idx on public.extraction_configs (client_id, is_active);
create index extraction_configs_brand_platform_idx on public.extraction_configs (brand_id, platform_id);
create index extraction_jobs_status_scheduled_idx on public.extraction_jobs (status, scheduled_for);
create index extraction_jobs_client_status_idx on public.extraction_jobs (client_id, status);
create index extraction_runs_client_started_idx on public.extraction_runs (client_id, started_at desc);
create index extraction_runs_status_idx on public.extraction_runs (status);
create index data_availability_client_idx on public.client_data_availability (client_id, brand_id, platform_id);
create index platform_posts_posted_idx on public.platform_posts (platform_id, posted_at desc);
create index posts_client_created_idx on public.posts (client_id, created_at desc);
create index posts_brand_idx on public.posts (brand_id);
create index posts_extraction_run_idx on public.posts (extraction_run_id);

create trigger clients_set_updated_at
    before update on public.clients
    for each row execute function public.set_updated_at();

create trigger client_branding_set_updated_at
    before update on public.client_branding
    for each row execute function public.set_updated_at();

create trigger client_subscriptions_set_updated_at
    before update on public.client_subscriptions
    for each row execute function public.set_updated_at();

create trigger brands_set_updated_at
    before update on public.brands
    for each row execute function public.set_updated_at();

create trigger extraction_configs_set_updated_at
    before update on public.extraction_configs
    for each row execute function public.set_updated_at();

create trigger platform_posts_set_updated_at
    before update on public.platform_posts
    for each row execute function public.set_updated_at();

insert into public.platforms (code, name, is_active)
values
    ('x', 'X', true),
    ('instagram', 'Instagram', true),
    ('tiktok', 'TikTok', true),
    ('youtube', 'YouTube', true),
    ('news', 'News', true)
on conflict (code) do nothing;

alter table public.clients enable row level security;
alter table public.client_branding enable row level security;
alter table public.client_users enable row level security;
alter table public.client_permissions enable row level security;
alter table public.client_configurations enable row level security;
alter table public.client_configuration_items enable row level security;
alter table public.onboarding_projects enable row level security;
alter table public.onboarding_tasks enable row level security;
alter table public.client_subscriptions enable row level security;
alter table public.client_plan_items enable row level security;
alter table public.cost_budgets enable row level security;
alter table public.usage_ledger enable row level security;
alter table public.platforms enable row level security;
alter table public.client_platforms enable row level security;
alter table public.brands enable row level security;
alter table public.brand_aliases enable row level security;
alter table public.brand_platform_volume_estimates enable row level security;
alter table public.themes enable row level security;
alter table public.subproducts enable row level security;
alter table public.apify_agents enable row level security;
alter table public.extraction_configs enable row level security;
alter table public.extraction_jobs enable row level security;
alter table public.extraction_runs enable row level security;
alter table public.client_data_availability enable row level security;
alter table public.platform_posts enable row level security;
alter table public.posts enable row level security;
