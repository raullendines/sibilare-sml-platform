<?php

namespace App\Http\Requests;

use App\Domain\Dashboards\Actions\QueryClientMetrics;
use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QueryClientMetricsRequest extends FormRequest
{
    use AuthorizesClientRoles;

    public function authorize(): bool
    {
        return $this->clientUserHasRole(['owner', 'admin', 'editor', 'viewer']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'queries' => ['required', 'array', 'min:1', 'max:50'],
            'queries.*.key' => ['required', 'string', 'max:120', 'distinct'],
            'queries.*.metric_code' => [
                'required',
                'string',
                Rule::in(QueryClientMetrics::SUPPORTED_METRICS),
                Rule::exists('metric_definitions', 'code')->where('is_active', true),
            ],
            'queries.*.date_range' => ['sometimes', 'string', Rule::in(['7d', '30d', '90d'])],
            'queries.*.date_from' => ['sometimes', 'date_format:Y-m-d'],
            'queries.*.date_to' => ['sometimes', 'date_format:Y-m-d'],
            'queries.*.interval' => ['sometimes', 'string', Rule::in(['day', 'week', 'month'])],
            'queries.*.limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'queries.*.brand_ids' => ['sometimes', 'array', 'max:50'],
            'queries.*.brand_ids.*' => ['uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'queries.*.platform_ids' => ['sometimes', 'array', 'max:20'],
            'queries.*.platform_ids.*' => ['uuid', Rule::exists('client_platforms', 'platform_id')->where('client_id', $clientId)->where('enabled', true)],
            'queries.*.brand_type' => ['sometimes', 'string', Rule::in(['own_brand', 'own_subbrand', 'competitor', 'competitor_subbrand'])],
            'queries.*.relevance' => ['sometimes', 'boolean'],
            'queries.*.search' => ['sometimes', 'string', 'max:200'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('queries', []) as $index => $query) {
                $dateFrom = $query['date_from'] ?? null;
                $dateTo = $query['date_to'] ?? null;

                if (is_string($dateFrom) && is_string($dateTo) && $dateTo < $dateFrom) {
                    $validator->errors()->add(
                        "queries.{$index}.date_to",
                        'The end date must be after or equal to the start date.',
                    );
                }
            }
        }];
    }
}
