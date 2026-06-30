<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        if (Schema::hasTable('clients')) {
            if (! Schema::hasTable('posts')) {
                throw new RuntimeException('The SML core schema looks partially applied. Resolve the database state before continuing.');
            }

            return;
        }

        DB::unprepared(file_get_contents(database_path('sql/sml_core_schema.sql')));
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
drop table if exists public.posts cascade;
drop table if exists public.platform_posts cascade;
drop table if exists public.client_data_availability cascade;
drop table if exists public.usage_ledger cascade;
drop table if exists public.cost_budgets cascade;
drop table if exists public.extraction_runs cascade;
drop table if exists public.extraction_jobs cascade;
drop table if exists public.extraction_configs cascade;
drop table if exists public.apify_agents cascade;
drop table if exists public.subproducts cascade;
drop table if exists public.themes cascade;
drop table if exists public.brand_platform_volume_estimates cascade;
drop table if exists public.brand_aliases cascade;
drop table if exists public.brands cascade;
drop table if exists public.client_platforms cascade;
drop table if exists public.platforms cascade;
drop table if exists public.client_plan_items cascade;
drop table if exists public.client_subscriptions cascade;
drop table if exists public.onboarding_tasks cascade;
drop table if exists public.onboarding_projects cascade;
drop table if exists public.client_configuration_items cascade;
drop table if exists public.client_configurations cascade;
drop table if exists public.client_permissions cascade;
drop table if exists public.client_users cascade;
drop table if exists public.client_branding cascade;
drop table if exists public.clients cascade;
drop function if exists public.set_updated_at() cascade;
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The SML core schema migration requires PostgreSQL/Supabase.');
        }
    }
};
