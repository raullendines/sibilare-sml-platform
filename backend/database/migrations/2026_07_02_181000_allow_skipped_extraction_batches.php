<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
alter table public.extraction_batches
    drop constraint if exists extraction_batches_status_check;

alter table public.extraction_batches
    add constraint extraction_batches_status_check
    check (status in ('queued', 'running', 'completed', 'partial', 'failed', 'skipped'));
SQL);
    }

    public function down(): void
    {
        $this->ensurePostgres();

        DB::unprepared(<<<'SQL'
alter table public.extraction_batches
    drop constraint if exists extraction_batches_status_check;

alter table public.extraction_batches
    add constraint extraction_batches_status_check
    check (status in ('queued', 'running', 'completed', 'partial', 'failed'));
SQL);
    }

    private function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('The extraction batch status migration requires PostgreSQL.');
        }
    }
};
