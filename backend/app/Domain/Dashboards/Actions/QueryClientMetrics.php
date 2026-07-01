<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Posts\Actions\FilterClientPosts;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ExtractionRun;
use App\Models\Post;
use App\Models\UsageLedger;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class QueryClientMetrics
{
    public const SUPPORTED_METRICS = [
        'mentions.total',
        'mentions.relevant',
        'mentions.timeline',
        'mentions.by_platform',
        'mentions.by_brand',
        'mentions.latest',
        'brands.total',
        'competitors.total',
        'usage.cost',
        'extractions.success_rate',
    ];

    public function __construct(
        private readonly FilterClientPosts $filterClientPosts,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return list<array<string, mixed>>
     */
    public function handle(Client $client, array $queries): array
    {
        return array_map(
            fn (array $query): array => $this->resolve($client, $this->normalizeWindow($client, $query)),
            $queries,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function resolve(Client $client, array $query): array
    {
        return match ($query['metric_code']) {
            'mentions.total' => $this->mentionsTotal($client, $query),
            'mentions.relevant' => $this->mentionsRelevant($client, $query),
            'mentions.timeline' => $this->mentionsTimeline($client, $query),
            'mentions.by_platform' => $this->mentionsByPlatform($client, $query),
            'mentions.by_brand' => $this->mentionsByBrand($client, $query),
            'mentions.latest' => $this->latestMentions($client, $query),
            'brands.total' => $this->brandCount($client, $query, false),
            'competitors.total' => $this->brandCount($client, $query, true),
            'usage.cost' => $this->usageCost($client, $query),
            'extractions.success_rate' => $this->extractionSuccessRate($client, $query),
            default => throw new LogicException("Unsupported metric [{$query['metric_code']}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mentionsTotal(Client $client, array $query): array
    {
        $value = $this->postCount($client, $query);

        return $this->scalar(
            $query,
            'number',
            $value,
            $this->comparison($value, $query, fn (array $previous) => $this->postCount($client, $previous)),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mentionsRelevant(Client $client, array $query): array
    {
        $query['relevance'] = true;
        $value = $this->postCount($client, $query);

        return $this->scalar(
            $query,
            'number',
            $value,
            $this->comparison($value, $query, fn (array $previous) => $this->postCount($client, $previous)),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mentionsTimeline(Client $client, array $query): array
    {
        $interval = $query['interval'] ?? 'day';
        $bucket = $this->dateBucket('platform_posts.posted_at', $interval);
        $rows = $this->postQuery($client, $query)
            ->reorder()
            ->join('platform_posts', 'platform_posts.id', '=', 'posts.platform_post_id')
            ->toBase()
            ->selectRaw("{$bucket} as bucket, count(*) as aggregate_value")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get();

        return $this->series($query, array_map(
            fn (object $row): array => [
                'key' => (string) $row->bucket,
                'label' => (string) $row->bucket,
                'value' => (int) $row->aggregate_value,
            ],
            $rows->all(),
        ), ['interval' => $interval]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mentionsByPlatform(Client $client, array $query): array
    {
        $rows = $this->postQuery($client, $query)
            ->reorder()
            ->join('platform_posts', 'platform_posts.id', '=', 'posts.platform_post_id')
            ->join('platforms', 'platforms.id', '=', 'platform_posts.platform_id')
            ->toBase()
            ->selectRaw('platforms.id, platforms.code, platforms.name, count(*) as aggregate_value')
            ->groupBy('platforms.id', 'platforms.code', 'platforms.name')
            ->orderByDesc('aggregate_value')
            ->get();

        return $this->series($query, array_map(
            fn (object $row): array => [
                'key' => (string) $row->id,
                'code' => (string) $row->code,
                'label' => (string) $row->name,
                'value' => (int) $row->aggregate_value,
            ],
            $rows->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function mentionsByBrand(Client $client, array $query): array
    {
        $rows = $this->postQuery($client, $query)
            ->reorder()
            ->join('brands', 'brands.id', '=', 'posts.brand_id')
            ->toBase()
            ->selectRaw('brands.id, brands.name, brands.color, count(*) as aggregate_value')
            ->groupBy('brands.id', 'brands.name', 'brands.color')
            ->orderByDesc('aggregate_value')
            ->get();

        return $this->series($query, array_map(
            fn (object $row): array => [
                'key' => (string) $row->id,
                'label' => (string) $row->name,
                'value' => (int) $row->aggregate_value,
                'color' => $row->color,
            ],
            $rows->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function latestMentions(Client $client, array $query): array
    {
        $limit = (int) ($query['limit'] ?? 20);

        return [
            ...$this->baseResult($query, 'list'),
            'items' => $this->postQuery($client, $query)->limit($limit)->get(),
            'meta' => [...$this->windowMeta($query), 'limit' => $limit],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function brandCount(Client $client, array $query, bool $competitorsOnly): array
    {
        $builder = Brand::query()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->when($query['brand_ids'] ?? null, fn (Builder $brandQuery, array $brandIds) => $brandQuery->whereIn('id', $brandIds))
            ->when($query['brand_type'] ?? null, fn (Builder $brandQuery, string $brandType) => $brandQuery->where('brand_type', $brandType));

        if ($competitorsOnly) {
            $builder->whereIn('brand_type', ['competitor', 'competitor_subbrand']);
        }

        return $this->scalar($query, 'number', $builder->count());
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function usageCost(Client $client, array $query): array
    {
        $value = $this->usageQuery($client, $query)->sum('cost_amount');
        $numericValue = round((float) $value, 4);

        return $this->scalar(
            $query,
            'currency',
            $numericValue,
            $this->comparison(
                $numericValue,
                $query,
                fn (array $previous) => round((float) $this->usageQuery($client, $previous)->sum('cost_amount'), 4),
            ),
            ['currency' => 'EUR'],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function extractionSuccessRate(Client $client, array $query): array
    {
        $value = $this->successRate($client, $query);

        return $this->scalar(
            $query,
            'percentage',
            $value,
            $this->comparison($value, $query, fn (array $previous) => $this->successRate($client, $previous)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function postCount(Client $client, array $filters): int
    {
        return $this->postQuery($client, $filters)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Post>
     */
    private function postQuery(Client $client, array $filters): Builder
    {
        return $this->filterClientPosts->handle($client, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<UsageLedger>
     */
    private function usageQuery(Client $client, array $filters): Builder
    {
        return UsageLedger::query()
            ->where('client_id', $client->id)
            ->whereDate('occurred_at', '>=', $filters['date_from'])
            ->whereDate('occurred_at', '<=', $filters['date_to'])
            ->when($filters['brand_ids'] ?? null, fn (Builder $query, array $brandIds) => $query->whereIn('brand_id', $brandIds))
            ->when($filters['platform_ids'] ?? null, fn (Builder $query, array $platformIds) => $query->whereIn('platform_id', $platformIds))
            ->when($filters['brand_type'] ?? null, fn (Builder $query, string $brandType) => $query->whereHas(
                'brand',
                fn (Builder $brandQuery) => $brandQuery->where('brand_type', $brandType),
            ));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function successRate(Client $client, array $filters): float
    {
        $query = ExtractionRun::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['success', 'failed', 'partial', 'cancelled'])
            ->whereDate('started_at', '>=', $filters['date_from'])
            ->whereDate('started_at', '<=', $filters['date_to'])
            ->when($filters['brand_ids'] ?? null, fn (Builder $runQuery, array $brandIds) => $runQuery->whereIn('brand_id', $brandIds))
            ->when($filters['platform_ids'] ?? null, fn (Builder $runQuery, array $platformIds) => $runQuery->whereIn('platform_id', $platformIds))
            ->when($filters['brand_type'] ?? null, fn (Builder $runQuery, string $brandType) => $runQuery->whereHas(
                'brand',
                fn (Builder $brandQuery) => $brandQuery->where('brand_type', $brandType),
            ));
        $completed = (clone $query)->count();

        if ($completed === 0) {
            return 0.0;
        }

        return round(((clone $query)->where('status', 'success')->count() / $completed) * 100, 1);
    }

    private function dateBucket(string $column, string $interval): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "date_trunc('{$interval}', {$column})";
        }

        return match ($interval) {
            'month' => "strftime('%Y-%m-01', {$column})",
            'week' => "strftime('%Y-W%W', {$column})",
            default => "strftime('%Y-%m-%d', {$column})",
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function normalizeWindow(Client $client, array $query): array
    {
        $days = match ($query['date_range'] ?? '30d') {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $timezone = $client->timezone ?: 'UTC';
        $dateTo = isset($query['date_to'])
            ? Carbon::createFromFormat('Y-m-d', $query['date_to'], $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();
        $dateFrom = isset($query['date_from'])
            ? Carbon::createFromFormat('Y-m-d', $query['date_from'], $timezone)->startOfDay()
            : $dateTo->copy()->subDays($days - 1);

        $query['date_from'] = $dateFrom->toDateString();
        $query['date_to'] = $dateTo->toDateString();

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function scalar(
        array $query,
        string $valueType,
        int|float $value,
        ?array $comparison = null,
        array $meta = [],
    ): array {
        return [
            ...$this->baseResult($query, 'scalar'),
            'value_type' => $valueType,
            'value' => $value,
            'comparison' => $comparison,
            'meta' => [...$this->windowMeta($query), ...$meta],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<array<string, mixed>>  $points
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function series(array $query, array $points, array $meta = []): array
    {
        return [
            ...$this->baseResult($query, 'series'),
            'value_type' => 'number',
            'points' => $points,
            'meta' => [...$this->windowMeta($query), ...$meta],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function baseResult(array $query, string $kind): array
    {
        return [
            'key' => $query['key'],
            'metric_code' => $query['metric_code'],
            'kind' => $kind,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function windowMeta(array $query): array
    {
        return [
            'date_from' => $query['date_from'],
            'date_to' => $query['date_to'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  Closure(array<string, mixed>): (int|float)  $resolver
     * @return array<string, int|float|null>
     */
    private function comparison(int|float $current, array $query, Closure $resolver): array
    {
        $dateFrom = Carbon::createFromFormat('Y-m-d', $query['date_from'])->startOfDay();
        $dateTo = Carbon::createFromFormat('Y-m-d', $query['date_to'])->startOfDay();
        $days = (int) $dateFrom->diffInDays($dateTo) + 1;
        $previous = [
            ...$query,
            'date_from' => $dateFrom->copy()->subDays($days)->toDateString(),
            'date_to' => $dateFrom->copy()->subDay()->toDateString(),
        ];
        $previousValue = $resolver($previous);
        $changePercent = $previousValue == 0
            ? null
            : round((($current - $previousValue) / abs($previousValue)) * 100, 1);

        return [
            'previous_value' => $previousValue,
            'change_percent' => $changePercent,
        ];
    }
}
