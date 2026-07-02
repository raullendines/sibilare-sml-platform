<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
alter table public.apify_agents
    add column if not exists task_id text,
    add column if not exists task_name text;

create unique index if not exists apify_agents_task_id_unique
    on public.apify_agents (task_id)
    where task_id is not null;
SQL);
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
drop index if exists public.apify_agents_task_id_unique;

alter table public.apify_agents
    drop column if exists task_name,
    drop column if exists task_id;
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The SML extraction schema migrations require PostgreSQL.');
        }
    }
};
