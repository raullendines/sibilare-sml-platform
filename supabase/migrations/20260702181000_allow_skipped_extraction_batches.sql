alter table public.extraction_batches
    drop constraint if exists extraction_batches_status_check;

alter table public.extraction_batches
    add constraint extraction_batches_status_check
    check (status in ('queued', 'running', 'completed', 'partial', 'failed', 'skipped'));
