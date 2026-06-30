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
