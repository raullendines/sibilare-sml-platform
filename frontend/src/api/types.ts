export type DateTimeString = string
export type DecimalString = string

export type JsonValue =
  | boolean
  | number
  | string
  | null
  | JsonValue[]
  | { [key: string]: JsonValue }

export type JsonObject = { [key: string]: JsonValue }

export type ClientStatus = 'onboarding' | 'active' | 'paused' | 'churned'
export type BrandType =
  | 'own_brand'
  | 'own_subbrand'
  | 'competitor'
  | 'competitor_subbrand'
export type PlatformCode = 'x' | 'instagram' | 'tiktok' | 'youtube' | 'news'
export type ExtractionFrequency = 'daily' | 'weekly' | 'monthly'
export type SelectionStrategy =
  | 'most_relevant'
  | 'most_recent'
  | 'engagement_weighted'
export type PostMatchType =
  | 'brand'
  | 'alias'
  | 'keyword'
  | 'competitor'
  | 'manual'
export type UsageType =
  | 'apify_run'
  | 'post_classification'
  | 'chatbot_message'
  | 'report_generation'
  | 'export'
  | 'ai_insight'
export type UsageUnit = 'run' | 'post' | 'message' | 'token' | 'file' | 'euro'
export type DashboardStatus = 'draft' | 'published' | 'archived'
export type DashboardLayoutMode = 'freeform' | 'guided'
export type WidgetType =
  | 'kpi'
  | 'line'
  | 'bar'
  | 'pie'
  | 'table'
  | 'map'
  | 'mentions_feed'
  | 'text'
  | 'heading'
  | 'divider'
export type VisualizationType =
  | 'kpi'
  | 'line'
  | 'bar'
  | 'pie'
  | 'table'
  | 'map'
  | 'mentions_feed'
  | 'text'
export type DashboardFilterField =
  | 'date_range'
  | 'brand_ids'
  | 'platform_ids'
  | 'brand_type'
  | 'relevance'
  | 'search'
export type DashboardFilterType =
  | 'date_range'
  | 'multi_select'
  | 'single_select'
  | 'boolean'
  | 'search'

export interface Client {
  id: string
  name: string
  slug: string
  status: ClientStatus
  industry: string | null
  default_locale: string
  timezone: string
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface Brand {
  id: string
  client_id: string
  parent_brand_id: string | null
  name: string
  brand_type: BrandType
  logo_url: string | null
  color: string | null
  website_url: string | null
  is_active: boolean
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface Platform {
  id: string
  code: PlatformCode
  name: string
  is_active: boolean
}

export interface ExtractionConfig {
  id: string
  client_id: string
  brand_id: string
  platform_id: string
  search_query: string
  frequency: ExtractionFrequency | null
  retroactive_days: number
  max_posts_per_run: number
  selection_strategy: SelectionStrategy
  cost_limit_per_run: DecimalString | null
  is_active: boolean
  brand?: Brand
  platform?: Platform
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface PlatformPost {
  id: string
  platform_id: string
  external_id: string
  author_handle: string | null
  author_name: string | null
  content_text: string | null
  url: string | null
  posted_at: DateTimeString | null
  language_code: string | null
  media_urls: string[]
  metrics: JsonObject
  platform?: Platform
}

export interface Post {
  id: string
  client_id: string
  brand_id: string
  platform_post_id: string
  extraction_run_id: string | null
  matched_query: string | null
  match_type: PostMatchType
  is_relevant_candidate: boolean
  brand?: Brand
  platform_post?: PlatformPost
  created_at: DateTimeString | null
}

export interface UsageLedgerEntry {
  id: string
  client_id: string
  usage_type: UsageType
  source_table: string | null
  source_id: string | null
  brand_id: string | null
  platform_id: string | null
  quantity: DecimalString
  unit: UsageUnit
  cost_amount: DecimalString | null
  currency: string
  occurred_at: DateTimeString | null
  metadata: JsonObject
  brand?: Brand
  platform?: Platform
}

export interface ClientOverviewCounts {
  brands: number
  own_brands: number
  competitors: number
  extraction_configs: number
  posts: number
  usage_entries: number
}

export interface ClientOverview {
  client: Client
  counts: ClientOverviewCounts
  latest_posts: Post[]
}

export interface MetricDefinition {
  code: string
  name: string
  description: string | null
  source_domain: 'posts' | 'brands' | 'usage' | 'extractions'
  result_kind?: 'scalar' | 'series' | 'list'
  value_type:
    | 'number'
    | 'currency'
    | 'percentage'
    | 'duration'
    | 'text'
    | 'list'
  default_aggregation: 'count' | 'sum' | 'avg' | 'min' | 'max' | 'latest' | 'none'
  default_visualization_type: VisualizationType
  config_schema: JsonObject
}

export interface WidgetBuilderMetric extends MetricDefinition {
  supported_visualizations: VisualizationType[]
  recommended_filters: DashboardFilterField[]
}

export interface WidgetBuilderSourceField {
  code: string
  label: string
  description: string
}

export interface WidgetBuilderSource {
  code: MetricDefinition['source_domain']
  label: string
  table_name: string
  description: string
  fields: WidgetBuilderSourceField[]
  metrics: WidgetBuilderMetric[]
}

export interface WidgetBuilderVisualization {
  code: VisualizationType
  label: string
  description: string
}

export interface WidgetBuilderFilterDefinition {
  field_code: DashboardFilterField
  label: string
  filter_type: DashboardFilterType
  description: string
  source_domains: MetricDefinition['source_domain'][]
}

export interface WidgetBuilderCatalog {
  sources: WidgetBuilderSource[]
  visualizations: WidgetBuilderVisualization[]
  filters: WidgetBuilderFilterDefinition[]
}

export interface WidgetTemplate {
  id: string
  code: string
  name: string
  description: string | null
  category: string
  widget_type: WidgetType
  metric_code: string | null
  default_title: string
  default_visualization_type: VisualizationType
  default_config: JsonObject
  default_width: number
  default_height: number
  min_width: number
  min_height: number
  metric?: MetricDefinition
}

export interface WidgetFilter {
  field_code: DashboardFilterField
  operator: 'equals' | 'contains' | 'in' | 'not_in' | 'gt' | 'gte' | 'lt' | 'lte'
  value: JsonValue
}

export interface DashboardWidget {
  id: string
  client_id: string
  dashboard_id: string
  widget_template_id: string | null
  widget_type: WidgetType
  visualization_type: VisualizationType
  metric_code: string | null
  title: string
  description: string | null
  grid_x: number
  grid_y: number
  grid_width: number
  grid_height: number
  min_width: number
  min_height: number
  sort_order: number
  config: JsonObject
  filters: WidgetFilter[]
  is_visible: boolean
  template?: WidgetTemplate
  metric?: MetricDefinition
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface DashboardFilter {
  id: string
  client_id: string
  dashboard_id: string
  field_code: DashboardFilterField
  label: string
  filter_type: DashboardFilterType
  default_value: JsonValue
  config: JsonObject
  sort_order: number
  is_visible: boolean
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface Dashboard {
  id: string
  client_id: string
  name: string
  slug: string
  description: string | null
  status: DashboardStatus
  is_default: boolean
  grid_columns: number
  layout_mode: DashboardLayoutMode
  current_version_number: number
  widgets_count?: number
  widgets?: DashboardWidget[]
  filters?: DashboardFilter[]
  preferences_supported?: boolean
  viewer_preferences?: DashboardUserPreference | null
  published_at: DateTimeString | null
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface DashboardUserPreference {
  id: string
  dashboard_id: string
  client_user_id: string
  filter_values: Partial<Record<DashboardFilterField, JsonValue>>
  last_opened_at: DateTimeString | null
  created_at: DateTimeString | null
  updated_at: DateTimeString | null
}

export interface DashboardVersion {
  id: string
  dashboard_id: string
  version_number: number
  snapshot: JsonObject
  created_by_user_id: string | null
  created_at: DateTimeString | null
}

export interface MetricQueryInput {
  key: string
  metric_code: string
  date_range?: '7d' | '30d' | '90d'
  date_from?: string
  date_to?: string
  interval?: 'day' | 'week' | 'month'
  limit?: number
  brand_ids?: string[]
  platform_ids?: string[]
  brand_type?: BrandType
  relevance?: boolean
  search?: string
}

export interface MetricComparison {
  previous_value: number
  change_percent: number | null
}

export interface MetricPoint {
  key: string
  code?: string
  label: string
  value: number
  color?: string | null
}

export interface MetricResultBase {
  key: string
  metric_code: string
  meta: JsonObject
}

export interface ScalarMetricResult extends MetricResultBase {
  kind: 'scalar'
  value_type: 'number' | 'currency' | 'percentage'
  value: number
  comparison: MetricComparison | null
}

export interface SeriesMetricResult extends MetricResultBase {
  kind: 'series'
  value_type: 'number'
  points: MetricPoint[]
}

export interface ListMetricResult extends MetricResultBase {
  kind: 'list'
  items: Post[]
}

export type MetricQueryResult =
  | ScalarMetricResult
  | SeriesMetricResult
  | ListMetricResult

export interface QueryMetricsInput {
  queries: MetricQueryInput[]
}

export interface ResourceResponse<T> {
  data: T
}

export interface CollectionResponse<T> {
  data: T[]
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  links: PaginationLink[]
  path: string
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> extends CollectionResponse<T> {
  links: PaginationLinks
  meta: PaginationMeta
}

export interface PaginationParams {
  page?: number
}

export interface PostFilters extends PaginationParams {
  brandId?: string
  platformId?: string
  dateFrom?: string
  dateTo?: string
  search?: string
  brandType?: BrandType
  relevance?: boolean
  perPage?: number
}

export interface CreateClientInput {
  name: string
  slug?: string | null
  status?: ClientStatus | null
  industry?: string | null
  default_locale?: string | null
  timezone?: string | null
}

export type UpdateClientInput = Partial<CreateClientInput>

export interface CreateBrandInput {
  parent_brand_id?: string | null
  name: string
  brand_type: BrandType
  logo_url?: string | null
  color?: string | null
  website_url?: string | null
  is_active?: boolean
}

export type UpdateBrandInput = Partial<CreateBrandInput>

export interface CreateExtractionConfigInput {
  brand_id: string
  platform_id: string
  search_query: string
  frequency?: ExtractionFrequency | null
  retroactive_days?: number
  max_posts_per_run: number
  selection_strategy?: SelectionStrategy
  cost_limit_per_run?: number | DecimalString | null
  is_active?: boolean
}

export type UpdateExtractionConfigInput = Partial<CreateExtractionConfigInput>

export interface CreateDashboardInput {
  name: string
  slug?: string | null
  description?: string | null
  is_default?: boolean
  grid_columns?: number
  starter_template?: 'social-listening-overview' | 'blank'
}

export interface UpdateDashboardInput {
  name?: string
  slug?: string | null
  description?: string | null
  is_default?: boolean
  layout_mode?: DashboardLayoutMode
}

export interface SaveDashboardWidgetInput {
  id?: string | null
  widget_template_id?: string | null
  widget_type: WidgetType
  visualization_type: VisualizationType
  metric_code: string | null
  title: string
  description?: string | null
  grid_x: number
  grid_y: number
  grid_width: number
  grid_height: number
  min_width?: number
  min_height?: number
  sort_order: number
  config?: JsonObject
  filters?: WidgetFilter[]
  is_visible?: boolean
}

export interface SaveDashboardFilterInput {
  id?: string | null
  field_code: DashboardFilterField
  label: string
  filter_type: DashboardFilterType
  default_value?: JsonValue
  config?: JsonObject
  sort_order: number
  is_visible?: boolean
}

export interface SaveDashboardLayoutInput {
  widgets: SaveDashboardWidgetInput[]
  filters: SaveDashboardFilterInput[]
}

export interface SaveDashboardPreferencesInput {
  filter_values: Partial<Record<DashboardFilterField, JsonValue>>
}
