<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\MetricDefinition;

class BuildWidgetBuilderCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $metrics = MetricDefinition::query()
            ->where('is_active', true)
            ->orderBy('source_domain')
            ->orderBy('name')
            ->get()
            ->groupBy('source_domain');

        return [
            'sources' => array_map(
                fn (array $meta, string $sourceCode): array => [
                    ...$meta,
                    'metrics' => ($metrics->get($sourceCode) ?? collect())
                        ->map(fn (MetricDefinition $metric): array => $this->metric($metric))
                        ->values()
                        ->all(),
                ],
                $this->sourceMeta(),
                array_keys($this->sourceMeta()),
            ),
            'visualizations' => $this->visualizations(),
            'filters' => $this->filters(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sourceMeta(): array
    {
        return [
            'posts' => [
                'code' => 'posts',
                'label' => 'Mentions',
                'table_name' => 'posts',
                'description' => 'Menciones normalizadas con marca, plataforma, fecha y relevancia.',
                'fields' => [
                    ['code' => 'brand_id', 'label' => 'Marca', 'description' => 'Marca propia o competidor detectado en la mencion.'],
                    ['code' => 'platform_id', 'label' => 'Plataforma', 'description' => 'Origen de la mencion: X, Instagram, TikTok, YouTube o News.'],
                    ['code' => 'posted_at', 'label' => 'Fecha', 'description' => 'Fecha de publicacion de la mencion original.'],
                    ['code' => 'is_relevant_candidate', 'label' => 'Relevancia', 'description' => 'Marca si la mencion se considera relevante para el analisis.'],
                    ['code' => 'content_text', 'label' => 'Texto', 'description' => 'Contenido del post o noticia asociado a la mencion.'],
                ],
            ],
            'brands' => [
                'code' => 'brands',
                'label' => 'Brands',
                'table_name' => 'brands',
                'description' => 'Catalogo de marcas propias, submarcas y competidores del cliente.',
                'fields' => [
                    ['code' => 'name', 'label' => 'Marca', 'description' => 'Nombre de la marca o competidor.'],
                    ['code' => 'brand_type', 'label' => 'Tipo de marca', 'description' => 'Marca propia, submarca, competidor o submarca competidora.'],
                    ['code' => 'is_active', 'label' => 'Activa', 'description' => 'Indica si la marca sigue activa en el seguimiento.'],
                ],
            ],
            'usage' => [
                'code' => 'usage',
                'label' => 'Operations',
                'table_name' => 'usage_ledger',
                'description' => 'Registro de costes y consumo para operaciones que generan gasto.',
                'fields' => [
                    ['code' => 'usage_type', 'label' => 'Tipo de uso', 'description' => 'Operacion facturable: scraping, AI, exportacion o chatbot.'],
                    ['code' => 'cost_amount', 'label' => 'Coste', 'description' => 'Importe registrado para la operacion.'],
                    ['code' => 'occurred_at', 'label' => 'Fecha', 'description' => 'Momento en que se registró el consumo.'],
                    ['code' => 'platform_id', 'label' => 'Plataforma', 'description' => 'Plataforma asociada al coste cuando aplica.'],
                ],
            ],
            'extractions' => [
                'code' => 'extractions',
                'label' => 'Scraping',
                'table_name' => 'extraction_runs',
                'description' => 'Ejecuciones de scraping y salud de las extracciones por plataforma y marca.',
                'fields' => [
                    ['code' => 'status', 'label' => 'Estado', 'description' => 'Estado final de la ejecucion de scraping.'],
                    ['code' => 'brand_id', 'label' => 'Marca', 'description' => 'Marca o competidor asociado a la extraccion.'],
                    ['code' => 'platform_id', 'label' => 'Plataforma', 'description' => 'Plataforma rastreada en la ejecucion.'],
                    ['code' => 'finished_at', 'label' => 'Fecha fin', 'description' => 'Momento en el que la extraccion termino.'],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filters(): array
    {
        return [
            [
                'field_code' => 'date_range',
                'label' => 'Periodo',
                'filter_type' => 'date_range',
                'description' => 'Acota el analisis a una ventana temporal concreta.',
                'source_domains' => ['posts', 'usage', 'extractions'],
            ],
            [
                'field_code' => 'brand_ids',
                'label' => 'Marcas',
                'filter_type' => 'multi_select',
                'description' => 'Filtra por una o varias marcas o competidores.',
                'source_domains' => ['posts', 'brands', 'usage', 'extractions'],
            ],
            [
                'field_code' => 'platform_ids',
                'label' => 'Plataformas',
                'filter_type' => 'multi_select',
                'description' => 'Filtra por plataformas concretas.',
                'source_domains' => ['posts', 'usage', 'extractions'],
            ],
            [
                'field_code' => 'brand_type',
                'label' => 'Tipo de marca',
                'filter_type' => 'single_select',
                'description' => 'Separa marca propia, submarcas y competidores.',
                'source_domains' => ['posts', 'brands'],
            ],
            [
                'field_code' => 'relevance',
                'label' => 'Relevancia',
                'filter_type' => 'boolean',
                'description' => 'Permite quedarte solo con menciones relevantes.',
                'source_domains' => ['posts'],
            ],
            [
                'field_code' => 'search',
                'label' => 'Busqueda',
                'filter_type' => 'search',
                'description' => 'Busca texto libre dentro del contenido o el nombre.',
                'source_domains' => ['posts', 'brands'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visualizations(): array
    {
        return [
            ['code' => 'kpi', 'label' => 'KPI', 'description' => 'Valor principal para una sola cifra.'],
            ['code' => 'line', 'label' => 'Linea', 'description' => 'Tendencia a lo largo del tiempo.'],
            ['code' => 'bar', 'label' => 'Barras', 'description' => 'Comparacion entre categorias o periodos.'],
            ['code' => 'pie', 'label' => 'Circular', 'description' => 'Reparto porcentual entre categorias.'],
            ['code' => 'table', 'label' => 'Tabla', 'description' => 'Detalle de filas o categorias.'],
            ['code' => 'mentions_feed', 'label' => 'Feed', 'description' => 'Listado de menciones recientes.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metric(MetricDefinition $metric): array
    {
        return [
            'code' => $metric->code,
            'name' => $metric->name,
            'description' => $metric->description,
            'source_domain' => $metric->source_domain,
            'result_kind' => $this->resultKind($metric),
            'value_type' => $metric->value_type,
            'default_aggregation' => $metric->default_aggregation,
            'default_visualization_type' => $metric->default_visualization_type,
            'config_schema' => $metric->config_schema,
            'supported_visualizations' => $this->supportedVisualizations($metric),
            'recommended_filters' => $this->recommendedFilters($metric),
        ];
    }

    /**
     * @return list<string>
     */
    private function supportedVisualizations(MetricDefinition $metric): array
    {
        return match ($metric->code) {
            'mentions.timeline' => ['line', 'bar', 'table'],
            'mentions.by_platform', 'mentions.by_brand' => ['bar', 'pie', 'table'],
            'mentions.latest' => ['mentions_feed', 'table'],
            default => ['kpi'],
        };
    }

    private function resultKind(MetricDefinition $metric): string
    {
        return match ($metric->code) {
            'mentions.timeline', 'mentions.by_platform', 'mentions.by_brand' => 'series',
            'mentions.latest' => 'list',
            default => 'scalar',
        };
    }

    /**
     * @return list<string>
     */
    private function recommendedFilters(MetricDefinition $metric): array
    {
        return match ($metric->source_domain) {
            'posts' => ['date_range', 'brand_ids', 'platform_ids', 'brand_type', 'relevance', 'search'],
            'brands' => ['brand_ids', 'brand_type', 'search'],
            'usage' => ['date_range', 'brand_ids', 'platform_ids'],
            'extractions' => ['date_range', 'brand_ids', 'platform_ids'],
            default => [],
        };
    }
}
