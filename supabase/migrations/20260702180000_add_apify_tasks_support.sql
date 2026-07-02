alter table public.apify_agents
    add column if not exists task_id text,
    add column if not exists task_name text;

create unique index if not exists apify_agents_task_id_unique
    on public.apify_agents (task_id)
    where task_id is not null;
