alter table public.platform_posts
    alter column media_urls drop default;

alter table public.platform_posts
    alter column media_urls type jsonb
    using to_jsonb(coalesce(media_urls, array[]::text[]));

alter table public.platform_posts
    alter column media_urls set default '[]'::jsonb;
