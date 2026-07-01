create table public.metric_definitions (
    code text primary key,
    name text not null,
    description text,
    source_domain text not null,
    value_type text not null,
    default_aggregation text not null,
    default_visualization_type text not null,
    config_schema jsonb not null default '{}'::jsonb,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    constraint metric_definitions_source_domain_check check (
        source_domain in ('posts', 'brands', 'usage', 'extractions')
    ),
    constraint metric_definitions_value_type_check check (
        value_type in ('number', 'currency', 'percentage', 'duration', 'text', 'list')
    ),
    constraint metric_definitions_aggregation_check check (
        default_aggregation in ('count', 'sum', 'avg', 'min', 'max', 'latest', 'none')
    ),
    constraint metric_definitions_visualization_check check (
        default_visualization_type in ('kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed')
    )
);

create table public.widget_templates (
    id uuid primary key default gen_random_uuid(),
    code text not null unique,
    name text not null,
    description text,
    category text not null,
    widget_type text not null,
    metric_code text references public.metric_definitions(code) on delete restrict,
    default_title text not null,
    default_visualization_type text not null,
    default_config jsonb not null default '{}'::jsonb,
    default_width integer not null,
    default_height integer not null,
    min_width integer not null default 2,
    min_height integer not null default 2,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint widget_templates_category_check check (
        category in ('overview', 'mentions', 'brands', 'competitors', 'engagement', 'operations', 'content')
    ),
    constraint widget_templates_type_check check (
        widget_type in ('kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text', 'heading', 'divider')
    ),
    constraint widget_templates_visualization_check check (
        default_visualization_type in ('kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text')
    ),
    constraint widget_templates_size_check check (
        default_width > 0 and default_height > 0 and min_width > 0 and min_height > 0
    )
);

create table public.dashboards (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    name text not null,
    slug text not null,
    description text,
    status text not null default 'draft',
    is_default boolean not null default false,
    grid_columns integer not null default 12,
    layout_mode text not null default 'freeform',
    current_version_number integer not null default 0,
    created_by_user_id uuid,
    updated_by_user_id uuid,
    published_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint dashboards_status_check check (status in ('draft', 'published', 'archived')),
    constraint dashboards_grid_columns_check check (grid_columns between 1 and 24),
    constraint dashboards_layout_mode_check check (layout_mode in ('freeform', 'guided')),
    constraint dashboards_version_nonnegative_check check (current_version_number >= 0),
    constraint dashboards_slug_unique unique (client_id, slug),
    constraint dashboards_id_client_unique unique (id, client_id),
    constraint dashboards_created_by_client_fk foreign key (created_by_user_id, client_id)
        references public.client_users(id, client_id) on delete set null (created_by_user_id),
    constraint dashboards_updated_by_client_fk foreign key (updated_by_user_id, client_id)
        references public.client_users(id, client_id) on delete set null (updated_by_user_id)
);

create unique index dashboards_one_default_per_client
    on public.dashboards (client_id)
    where is_default = true and status <> 'archived';

create table public.dashboard_widgets (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    dashboard_id uuid not null,
    widget_template_id uuid references public.widget_templates(id) on delete set null,
    widget_type text not null,
    visualization_type text not null,
    metric_code text references public.metric_definitions(code) on delete restrict,
    title text not null,
    description text,
    grid_x integer not null default 0,
    grid_y integer not null default 0,
    grid_width integer not null default 4,
    grid_height integer not null default 3,
    min_width integer not null default 2,
    min_height integer not null default 2,
    sort_order integer not null default 0,
    config jsonb not null default '{}'::jsonb,
    filters jsonb not null default '[]'::jsonb,
    is_visible boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint dashboard_widgets_dashboard_client_fk foreign key (dashboard_id, client_id)
        references public.dashboards(id, client_id) on delete cascade,
    constraint dashboard_widgets_type_check check (
        widget_type in ('kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text', 'heading', 'divider')
    ),
    constraint dashboard_widgets_visualization_check check (
        visualization_type in ('kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text')
    ),
    constraint dashboard_widgets_position_check check (grid_x >= 0 and grid_y >= 0),
    constraint dashboard_widgets_size_check check (
        grid_width > 0 and grid_height > 0 and min_width > 0 and min_height > 0
    ),
    constraint dashboard_widgets_sort_nonnegative_check check (sort_order >= 0),
    constraint dashboard_widgets_id_client_unique unique (id, client_id)
);

create table public.dashboard_filters (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    dashboard_id uuid not null,
    field_code text not null,
    label text not null,
    filter_type text not null,
    default_value jsonb,
    config jsonb not null default '{}'::jsonb,
    sort_order integer not null default 0,
    is_visible boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint dashboard_filters_dashboard_client_fk foreign key (dashboard_id, client_id)
        references public.dashboards(id, client_id) on delete cascade,
    constraint dashboard_filters_field_check check (
        field_code in ('date_range', 'brand_ids', 'platform_ids', 'brand_type', 'relevance', 'search')
    ),
    constraint dashboard_filters_type_check check (
        filter_type in ('date_range', 'multi_select', 'single_select', 'boolean', 'search')
    ),
    constraint dashboard_filters_sort_nonnegative_check check (sort_order >= 0),
    constraint dashboard_filters_dashboard_field_unique unique (dashboard_id, field_code),
    constraint dashboard_filters_id_client_unique unique (id, client_id)
);

create table public.dashboard_versions (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    dashboard_id uuid not null,
    version_number integer not null,
    snapshot jsonb not null,
    created_by_user_id uuid,
    created_at timestamptz not null default now(),
    constraint dashboard_versions_dashboard_client_fk foreign key (dashboard_id, client_id)
        references public.dashboards(id, client_id) on delete cascade,
    constraint dashboard_versions_created_by_client_fk foreign key (created_by_user_id, client_id)
        references public.client_users(id, client_id) on delete set null (created_by_user_id),
    constraint dashboard_versions_number_positive_check check (version_number > 0),
    constraint dashboard_versions_dashboard_number_unique unique (dashboard_id, version_number)
);

create table public.dashboard_user_preferences (
    id uuid primary key default gen_random_uuid(),
    client_id uuid not null references public.clients(id) on delete cascade,
    dashboard_id uuid not null references public.dashboards(id) on delete cascade,
    client_user_id uuid not null references public.client_users(id) on delete cascade,
    filter_values jsonb not null default '{}'::jsonb,
    last_opened_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint dashboard_user_preferences_dashboard_user_unique unique (dashboard_id, client_user_id)
);

create index dashboards_client_status_idx on public.dashboards (client_id, status);
create index dashboard_widgets_dashboard_sort_idx on public.dashboard_widgets (dashboard_id, sort_order);
create index dashboard_widgets_metric_idx on public.dashboard_widgets (metric_code);
create index dashboard_filters_dashboard_sort_idx on public.dashboard_filters (dashboard_id, sort_order);
create index dashboard_versions_dashboard_number_idx on public.dashboard_versions (dashboard_id, version_number desc);
create index dashboard_user_preferences_client_user_idx on public.dashboard_user_preferences (client_id, client_user_id);
create index widget_templates_category_active_idx on public.widget_templates (category, is_active);
create index metric_definitions_domain_active_idx on public.metric_definitions (source_domain, is_active);

create trigger widget_templates_set_updated_at
    before update on public.widget_templates
    for each row execute function public.set_updated_at();

create trigger dashboards_set_updated_at
    before update on public.dashboards
    for each row execute function public.set_updated_at();

create trigger dashboard_widgets_set_updated_at
    before update on public.dashboard_widgets
    for each row execute function public.set_updated_at();

create trigger dashboard_filters_set_updated_at
    before update on public.dashboard_filters
    for each row execute function public.set_updated_at();

insert into public.metric_definitions (
    code,
    name,
    description,
    source_domain,
    value_type,
    default_aggregation,
    default_visualization_type,
    config_schema
)
values
    ('mentions.total', 'Total mentions', 'Number of matched client posts in the selected period.', 'posts', 'number', 'count', 'kpi', '{"supports_comparison": true}'::jsonb),
    ('mentions.relevant', 'Relevant mentions', 'Mentions currently marked as relevant candidates.', 'posts', 'number', 'count', 'kpi', '{"supports_comparison": true}'::jsonb),
    ('mentions.timeline', 'Mentions over time', 'Mention volume grouped by time bucket.', 'posts', 'number', 'count', 'line', '{"allowed_intervals": ["day", "week", "month"]}'::jsonb),
    ('mentions.by_platform', 'Mentions by platform', 'Mention volume grouped by source platform.', 'posts', 'number', 'count', 'bar', '{}'::jsonb),
    ('mentions.by_brand', 'Mentions by brand', 'Mention volume grouped by own brand or competitor.', 'posts', 'number', 'count', 'pie', '{}'::jsonb),
    ('mentions.latest', 'Latest mentions', 'Most recent normalized mentions with brand and platform context.', 'posts', 'list', 'latest', 'mentions_feed', '{}'::jsonb),
    ('brands.total', 'Tracked brands', 'Number of active own and competitor brands.', 'brands', 'number', 'count', 'kpi', '{}'::jsonb),
    ('competitors.total', 'Tracked competitors', 'Number of active competitor brands.', 'brands', 'number', 'count', 'kpi', '{}'::jsonb),
    ('usage.cost', 'Usage cost', 'Cost recorded in the usage ledger for the selected period.', 'usage', 'currency', 'sum', 'kpi', '{"currency": "EUR"}'::jsonb),
    ('extractions.success_rate', 'Extraction success rate', 'Successful extraction runs divided by completed runs.', 'extractions', 'percentage', 'avg', 'kpi', '{}'::jsonb)
on conflict (code) do nothing;

insert into public.widget_templates (
    code,
    name,
    description,
    category,
    widget_type,
    metric_code,
    default_title,
    default_visualization_type,
    default_config,
    default_width,
    default_height,
    min_width,
    min_height
)
values
    ('kpi-total-mentions', 'Total mentions', 'Headline mention count with optional period comparison.', 'overview', 'kpi', 'mentions.total', 'Total mentions', 'kpi', '{"date_range": "30d", "show_comparison": true}'::jsonb, 3, 2, 2, 2),
    ('kpi-relevant-mentions', 'Relevant mentions', 'Relevant mention count for the current filters.', 'overview', 'kpi', 'mentions.relevant', 'Relevant mentions', 'kpi', '{"date_range": "30d", "show_comparison": true}'::jsonb, 3, 2, 2, 2),
    ('line-mentions-timeline', 'Mentions over time', 'Time series of mention volume.', 'mentions', 'line', 'mentions.timeline', 'Mentions over time', 'line', '{"date_range": "30d", "interval": "day", "show_legend": true}'::jsonb, 8, 4, 4, 3),
    ('bar-mentions-platform', 'Mentions by platform', 'Comparison of mention volume across platforms.', 'mentions', 'bar', 'mentions.by_platform', 'Mentions by platform', 'bar', '{"date_range": "30d", "orientation": "vertical"}'::jsonb, 4, 4, 3, 3),
    ('pie-mentions-brand', 'Mentions by brand', 'Share of voice across own brands and competitors.', 'competitors', 'pie', 'mentions.by_brand', 'Share of voice', 'pie', '{"date_range": "30d", "show_legend": true}'::jsonb, 4, 4, 3, 3),
    ('feed-latest-mentions', 'Latest mentions', 'Recent mention feed with source and relevance.', 'mentions', 'mentions_feed', 'mentions.latest', 'Latest mentions', 'mentions_feed', '{"date_range": "7d", "limit": 20}'::jsonb, 8, 5, 4, 3),
    ('kpi-usage-cost', 'Usage cost', 'Current period cost from the usage ledger.', 'operations', 'kpi', 'usage.cost', 'Usage cost', 'kpi', '{"date_range": "30d", "show_comparison": true}'::jsonb, 3, 2, 2, 2),
    ('text-section', 'Text block', 'Editable explanatory text or section introduction.', 'content', 'text', null, 'Text block', 'text', '{"content": ""}'::jsonb, 6, 2, 2, 1)
on conflict (code) do nothing;

alter table public.metric_definitions enable row level security;
alter table public.widget_templates enable row level security;
alter table public.dashboards enable row level security;
alter table public.dashboard_widgets enable row level security;
alter table public.dashboard_filters enable row level security;
alter table public.dashboard_versions enable row level security;
alter table public.dashboard_user_preferences enable row level security;
