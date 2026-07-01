<?php

namespace App\Domain\Posts\Actions;

use App\Models\Client;
use App\Models\PlatformPost;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

class FilterClientPosts
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Post>
     */
    public function handle(Client $client, array $filters): Builder
    {
        return Post::query()
            ->where('posts.client_id', $client->id)
            ->with(['brand', 'platformPost.platform'])
            ->when($filters['brand_id'] ?? null, fn (Builder $query, string $brandId) => $query->where('posts.brand_id', $brandId))
            ->when($filters['brand_ids'] ?? null, fn (Builder $query, array $brandIds) => $query->whereIn('posts.brand_id', $brandIds))
            ->when(array_key_exists('relevance', $filters), fn (Builder $query) => $query->where('posts.is_relevant_candidate', $filters['relevance']))
            ->when($filters['brand_type'] ?? null, fn (Builder $query, string $brandType) => $query->whereHas(
                'brand',
                fn (Builder $brandQuery) => $brandQuery->where('brand_type', $brandType),
            ))
            ->when($filters['platform_id'] ?? null, fn (Builder $query, string $platformId) => $query->whereHas(
                'platformPost',
                fn (Builder $postQuery) => $postQuery->where('platform_id', $platformId),
            ))
            ->when($filters['platform_ids'] ?? null, fn (Builder $query, array $platformIds) => $query->whereHas(
                'platformPost',
                fn (Builder $postQuery) => $postQuery->whereIn('platform_id', $platformIds),
            ))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereHas(
                'platformPost',
                fn (Builder $postQuery) => $postQuery->whereDate('posted_at', '>=', $dateFrom),
            ))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereHas(
                'platformPost',
                fn (Builder $postQuery) => $postQuery->whereDate('posted_at', '<=', $dateTo),
            ))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'platformPost',
                fn (Builder $postQuery) => $postQuery->whereLike('content_text', "%{$search}%", caseSensitive: false),
            ))
            ->orderByDesc(
                PlatformPost::query()
                    ->select('posted_at')
                    ->whereColumn('platform_posts.id', 'posts.platform_post_id')
                    ->limit(1),
            )
            ->latest('posts.created_at');
    }
}
