<?php

namespace App\Domain\Extraction;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApifyHttpClient
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function startRun(string $actorId, array $input, bool $withWebhook = true): array
    {
        $path = '/acts/'.str_replace('/', '~', $actorId).'/runs';
        $query = [];

        if ($withWebhook) {
            $query['webhooks'] = $this->encodedWebhookDefinition();
        }

        $response = $this->request()->post($this->url($path, $query), $input)->throw()->json('data');

        if (! is_array($response) || ! isset($response['id'])) {
            throw new RuntimeException('Apify did not return a valid actor run identifier.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRun(string $externalRunId): array
    {
        $response = $this->request()->get($this->url("/actor-runs/{$externalRunId}"))->throw()->json('data');

        if (! is_array($response)) {
            throw new RuntimeException('Apify returned an invalid actor run payload.');
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDatasetItems(string $datasetId, int $limit): array
    {
        $items = $this->request()
            ->get($this->url("/datasets/{$datasetId}/items", [
                'clean' => 'true',
                'format' => 'json',
                'limit' => (string) $limit,
            ]))
            ->throw()
            ->json();

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    public function abortRun(string $externalRunId): void
    {
        $this->request()->post($this->url("/actor-runs/{$externalRunId}/abort"))->throw();
    }

    private function request(): PendingRequest
    {
        $token = config('services.apify.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('APIFY_TOKEN is not configured.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('services.apify.timeout_seconds', 30))
            ->retry(3, 500);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function url(string $path, array $query = []): string
    {
        $baseUrl = rtrim((string) config('services.apify.base_url', 'https://api.apify.com/v2'), '/');

        return $baseUrl.$path.($query === [] ? '' : '?'.http_build_query($query));
    }

    private function encodedWebhookDefinition(): string
    {
        $secret = config('services.apify.webhook_secret');
        $webhookUrl = config('services.apify.webhook_url');

        if (! is_string($secret) || $secret === '' || ! is_string($webhookUrl) || $webhookUrl === '') {
            throw new RuntimeException('Apify webhook URL and secret must be configured for asynchronous extraction.');
        }

        return base64_encode(json_encode([[
            'eventTypes' => [
                'ACTOR.RUN.SUCCEEDED',
                'ACTOR.RUN.FAILED',
                'ACTOR.RUN.ABORTED',
                'ACTOR.RUN.TIMED_OUT',
            ],
            'requestUrl' => $webhookUrl,
            'headersTemplate' => json_encode(['X-Apify-Webhook-Secret' => $secret], JSON_THROW_ON_ERROR),
        ]], JSON_THROW_ON_ERROR));
    }
}
