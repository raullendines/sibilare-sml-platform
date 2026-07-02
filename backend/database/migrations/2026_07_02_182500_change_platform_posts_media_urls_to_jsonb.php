<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
alter table public.platform_posts
    alter column media_urls drop default;

alter table public.platform_posts
    alter column media_urls type jsonb
    using to_jsonb(coalesce(media_urls, array[]::text[]));

alter table public.platform_posts
    alter column media_urls set default '[]'::jsonb;
SQL);
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
alter table public.platform_posts
    alter column media_urls drop default;

alter table public.platform_posts
    alter column media_urls type text[]
    using coalesce(array(select jsonb_array_elements_text(media_urls)), array[]::text[]);

alter table public.platform_posts
    alter column media_urls set default array[]::text[];
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The platform_posts media_urls migration requires PostgreSQL.');
        }
    }
};
