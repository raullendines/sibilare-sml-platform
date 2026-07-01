<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDashboardLayoutRequest extends FormRequest
{
    use AuthorizesClientRoles;

    public function authorize(): bool
    {
        return $this->clientUserHasRole(['owner', 'admin', 'editor']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;
        $widgetTypes = ['kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text', 'heading', 'divider'];
        $visualizationTypes = ['kpi', 'line', 'bar', 'pie', 'table', 'map', 'mentions_feed', 'text'];
        $filterFields = ['date_range', 'brand_ids', 'platform_ids', 'brand_type', 'relevance', 'search'];

        return [
            'widgets' => ['present', 'array', 'max:100'],
            'widgets.*.id' => ['sometimes', 'nullable', 'uuid'],
            'widgets.*.widget_template_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('widget_templates', 'id')->where('is_active', true)],
            'widgets.*.widget_type' => ['required', 'string', Rule::in($widgetTypes)],
            'widgets.*.visualization_type' => ['required', 'string', Rule::in($visualizationTypes)],
            'widgets.*.metric_code' => ['nullable', 'string', Rule::exists('metric_definitions', 'code')->where('is_active', true)],
            'widgets.*.title' => ['required', 'string', 'max:255'],
            'widgets.*.description' => ['nullable', 'string', 'max:2000'],
            'widgets.*.grid_x' => ['required', 'integer', 'min:0', 'max:23'],
            'widgets.*.grid_y' => ['required', 'integer', 'min:0', 'max:10000'],
            'widgets.*.grid_width' => ['required', 'integer', 'min:1', 'max:24'],
            'widgets.*.grid_height' => ['required', 'integer', 'min:1', 'max:100'],
            'widgets.*.min_width' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'widgets.*.min_height' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'widgets.*.sort_order' => ['required', 'integer', 'min:0'],
            'widgets.*.config' => ['sometimes', 'array'],
            'widgets.*.config.brand_ids' => ['sometimes', 'array'],
            'widgets.*.config.brand_ids.*' => ['uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'widgets.*.config.platform_ids' => ['sometimes', 'array'],
            'widgets.*.config.platform_ids.*' => ['uuid', Rule::exists('client_platforms', 'platform_id')->where('client_id', $clientId)->where('enabled', true)],
            'widgets.*.filters' => ['sometimes', 'array', 'max:20'],
            'widgets.*.filters.*.field_code' => ['required_with:widgets.*.filters', 'string', Rule::in($filterFields)],
            'widgets.*.filters.*.operator' => ['required_with:widgets.*.filters', 'string', Rule::in(['equals', 'contains', 'in', 'not_in', 'gt', 'gte', 'lt', 'lte'])],
            'widgets.*.filters.*.value' => ['present'],
            'widgets.*.is_visible' => ['sometimes', 'boolean'],

            'filters' => ['present', 'array', 'max:20'],
            'filters.*.id' => ['sometimes', 'nullable', 'uuid'],
            'filters.*.field_code' => ['required', 'string', 'distinct', Rule::in($filterFields)],
            'filters.*.label' => ['required', 'string', 'max:120'],
            'filters.*.filter_type' => ['required', 'string', Rule::in(['date_range', 'multi_select', 'single_select', 'boolean', 'search'])],
            'filters.*.default_value' => ['sometimes', 'nullable'],
            'filters.*.config' => ['sometimes', 'array'],
            'filters.*.sort_order' => ['required', 'integer', 'min:0'],
            'filters.*.is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
