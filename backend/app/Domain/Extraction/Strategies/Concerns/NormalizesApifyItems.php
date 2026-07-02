<?php

namespace App\Domain\Extraction\Strategies\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

trait NormalizesApifyItems
{
    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    protected function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($item, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    protected function firstDate(array $item, array $keys): ?CarbonImmutable
    {
        $value = $this->firstString($item, $keys);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, list<string>>  $mapping
     * @return array<string, int|float|string|null>
     */
    protected function metrics(array $item, array $mapping): array
    {
        $metrics = [];

        foreach ($mapping as $name => $keys) {
            foreach ($keys as $key) {
                $value = data_get($item, $key);

                if (is_numeric($value)) {
                    $metrics[$name] = $value + 0;
                    break;
                }
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function mediaUrls(array $item, array $keys): array
    {
        $urls = [];

        foreach ($keys as $key) {
            $value = data_get($item, $key);

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $urls[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $candidate) {
                    $url = is_array($candidate) ? ($candidate['url'] ?? null) : $candidate;

                    if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function withActorOptions(array $base, array $overrides): array
    {
        return array_replace_recursive($base, $overrides);
    }
}
