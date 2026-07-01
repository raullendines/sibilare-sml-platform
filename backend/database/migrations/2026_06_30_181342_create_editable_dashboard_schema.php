<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        if (Schema::hasTable('dashboards')) {
            if (! Schema::hasTable('dashboard_widgets')) {
                throw new RuntimeException('The editable dashboard schema looks partially applied. Resolve the database state before continuing.');
            }

            return;
        }

        DB::unprepared(file_get_contents(database_path('sql/editable_dashboard_schema.sql')));
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
drop table if exists public.dashboard_versions cascade;
drop table if exists public.dashboard_filters cascade;
drop table if exists public.dashboard_widgets cascade;
drop table if exists public.dashboards cascade;
drop table if exists public.widget_templates cascade;
drop table if exists public.metric_definitions cascade;
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The editable dashboard migration requires PostgreSQL/Supabase.');
        }
    }
};
