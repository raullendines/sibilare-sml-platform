import type {
  Brand,
  Client,
  ClientOverview,
  CollectionResponse,
  CreateBrandInput,
  CreateExtractionBatchInput,
  CreateClientInput,
  CreateDashboardInput,
  CreateExtractionConfigInput,
  CreateProjectInput,
  Dashboard,
  DashboardUserPreference,
  DashboardVersion,
  ExtractionBatch,
  ExtractionConfig,
  ExtractionWorkspacePayload,
  PaginatedResponse,
  PaginationParams,
  Platform,
  Post,
  PostFilters,
  Project,
  MetricQueryResult,
  QueryMetricsInput,
  ResourceResponse,
  SaveDashboardLayoutInput,
  SaveDashboardPreferencesInput,
  UpdateBrandInput,
  UpdateClientInput,
  UpdateDashboardInput,
  UpdateExtractionConfigInput,
  UpdateProjectInput,
  UsageLedgerEntry,
  WidgetBuilderCatalog,
  WidgetTemplate,
} from './types.ts'

export type AccessTokenProvider = () =>
  | Promise<string | null>
  | string
  | null

export interface SmlApiClientOptions {
  getAccessToken: AccessTokenProvider
  baseUrl?: string
  fetcher?: typeof fetch
}

export interface RequestOptions {
  signal?: AbortSignal
}

export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
  [key: string]: unknown
}

export class ApiError extends Error {
  readonly status: number
  readonly payload: ApiErrorPayload | null

  constructor(status: number, message: string, payload: ApiErrorPayload | null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.payload = payload
  }
}

const defaultBaseUrl =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '')
}

function resourcePath(resource: string, id: string): string {
  return `/${resource}/${encodeURIComponent(id)}`
}

function clientResourcePath(
  clientId: string,
  resource: string,
  id?: string,
): string {
  const path = `/clients/${encodeURIComponent(clientId)}/${resource}`

  return id === undefined ? path : `${path}/${encodeURIComponent(id)}`
}

function withQuery(
  path: string,
  params: Record<string, boolean | number | string | undefined>,
): string {
  const query = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) {
      query.set(key, String(value))
    }
  }

  const queryString = query.toString()

  return queryString === '' ? path : `${path}?${queryString}`
}

async function parseResponseBody(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return null
  }

  const contentType = response.headers.get('content-type')

  if (contentType?.includes('application/json')) {
    return response.json()
  }

  const text = await response.text()

  return text === '' ? null : text
}

function errorPayload(body: unknown): ApiErrorPayload | null {
  if (body !== null && typeof body === 'object' && !Array.isArray(body)) {
    return body as ApiErrorPayload
  }

  return null
}

export class SmlApiClient {
  private readonly baseUrl: string
  private readonly fetcher: typeof fetch
  private readonly getAccessToken: AccessTokenProvider

  constructor(options: SmlApiClientOptions) {
    this.baseUrl = trimTrailingSlash(options.baseUrl ?? defaultBaseUrl)
    this.fetcher = options.fetcher ?? globalThis.fetch.bind(globalThis)
    this.getAccessToken = options.getAccessToken
  }

  async listPlatforms(
    options: RequestOptions = {},
  ): Promise<CollectionResponse<Platform>> {
    return this.request('/platforms', { signal: options.signal })
  }

  async listWidgetTemplates(
    options: RequestOptions = {},
  ): Promise<CollectionResponse<WidgetTemplate>> {
    return this.request('/widget-templates', { signal: options.signal })
  }

  async getWidgetBuilderCatalog(
    options: RequestOptions = {},
  ): Promise<ResourceResponse<WidgetBuilderCatalog>> {
    return this.request('/widget-builder/catalog', { signal: options.signal })
  }

  async listClients(
    params: PaginationParams = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<Client>> {
    return this.request(withQuery('/clients', { page: params.page }), {
      signal: options.signal,
    })
  }

  async createClient(
    input: CreateClientInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Client>> {
    return this.request('/clients', {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getClient(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Client>> {
    return this.request(resourcePath('clients', clientId), {
      signal: options.signal,
    })
  }

  async updateClient(
    clientId: string,
    input: UpdateClientInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Client>> {
    return this.request(resourcePath('clients', clientId), {
      method: 'PATCH',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getClientOverview(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ClientOverview>> {
    return this.request(clientResourcePath(clientId, 'overview'), {
      signal: options.signal,
    })
  }

  async listClientPlatforms(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<CollectionResponse<Platform>> {
    return this.request(clientResourcePath(clientId, 'platforms'), {
      signal: options.signal,
    })
  }

  async queryMetrics(
    clientId: string,
    input: QueryMetricsInput,
    options: RequestOptions = {},
  ): Promise<CollectionResponse<MetricQueryResult>> {
    return this.request(clientResourcePath(clientId, 'metrics/query'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async listDashboards(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<CollectionResponse<Dashboard>> {
    return this.request(clientResourcePath(clientId, 'dashboards'), {
      signal: options.signal,
    })
  }

  async createDashboard(
    clientId: string,
    input: CreateDashboardInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Dashboard>> {
    return this.request(clientResourcePath(clientId, 'dashboards'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getDashboard(
    clientId: string,
    dashboardId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Dashboard>> {
    return this.request(
      clientResourcePath(clientId, 'dashboards', dashboardId),
      { signal: options.signal },
    )
  }

  async updateDashboard(
    clientId: string,
    dashboardId: string,
    input: UpdateDashboardInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Dashboard>> {
    return this.request(
      clientResourcePath(clientId, 'dashboards', dashboardId),
      {
        method: 'PATCH',
        body: JSON.stringify(input),
        signal: options.signal,
      },
    )
  }

  async saveDashboardLayout(
    clientId: string,
    dashboardId: string,
    input: SaveDashboardLayoutInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Dashboard>> {
    return this.request(
      `${clientResourcePath(clientId, 'dashboards', dashboardId)}/layout`,
      {
        method: 'PUT',
        body: JSON.stringify(input),
        signal: options.signal,
      },
    )
  }

  async saveDashboardPreferences(
    clientId: string,
    dashboardId: string,
    input: SaveDashboardPreferencesInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<DashboardUserPreference>> {
    return this.request(
      `${clientResourcePath(clientId, 'dashboards', dashboardId)}/preferences`,
      {
        method: 'PUT',
        body: JSON.stringify(input),
        signal: options.signal,
      },
    )
  }

  async publishDashboard(
    clientId: string,
    dashboardId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<DashboardVersion>> {
    return this.request(
      `${clientResourcePath(clientId, 'dashboards', dashboardId)}/publish`,
      {
        method: 'POST',
        signal: options.signal,
      },
    )
  }

  async listDashboardVersions(
    clientId: string,
    dashboardId: string,
    options: RequestOptions = {},
  ): Promise<CollectionResponse<DashboardVersion>> {
    return this.request(
      `${clientResourcePath(clientId, 'dashboards', dashboardId)}/versions`,
      { signal: options.signal },
    )
  }

  async listBrands(
    clientId: string,
    params: PaginationParams = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<Brand>> {
    return this.request(
      withQuery(clientResourcePath(clientId, 'brands'), {
        page: params.page,
      }),
      { signal: options.signal },
    )
  }

  async createBrand(
    clientId: string,
    input: CreateBrandInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Brand>> {
    return this.request(clientResourcePath(clientId, 'brands'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getBrand(
    clientId: string,
    brandId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Brand>> {
    return this.request(clientResourcePath(clientId, 'brands', brandId), {
      signal: options.signal,
    })
  }

  async updateBrand(
    clientId: string,
    brandId: string,
    input: UpdateBrandInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Brand>> {
    return this.request(clientResourcePath(clientId, 'brands', brandId), {
      method: 'PATCH',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async listProjects(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<CollectionResponse<Project>> {
    return this.request(clientResourcePath(clientId, 'projects'), {
      signal: options.signal,
    })
  }

  async createProject(
    clientId: string,
    input: CreateProjectInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Project>> {
    return this.request(clientResourcePath(clientId, 'projects'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async updateProject(
    clientId: string,
    projectId: string,
    input: UpdateProjectInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Project>> {
    return this.request(clientResourcePath(clientId, 'projects', projectId), {
      method: 'PATCH',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async listExtractionConfigs(
    clientId: string,
    params: PaginationParams & {
      projectId?: string
      activeOnly?: boolean
    } = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<ExtractionConfig>> {
    return this.request(
      withQuery(clientResourcePath(clientId, 'extraction-configs'), {
        page: params.page,
        project_id: params.projectId,
        active_only: params.activeOnly,
      }),
      { signal: options.signal },
    )
  }

  async createExtractionConfig(
    clientId: string,
    input: CreateExtractionConfigInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionConfig>> {
    return this.request(clientResourcePath(clientId, 'extraction-configs'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getExtractionConfig(
    clientId: string,
    extractionConfigId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionConfig>> {
    return this.request(
      clientResourcePath(
        clientId,
        'extraction-configs',
        extractionConfigId,
      ),
      { signal: options.signal },
    )
  }

  async updateExtractionConfig(
    clientId: string,
    extractionConfigId: string,
    input: UpdateExtractionConfigInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionConfig>> {
    return this.request(
      clientResourcePath(
        clientId,
        'extraction-configs',
        extractionConfigId,
      ),
      {
        method: 'PATCH',
        body: JSON.stringify(input),
        signal: options.signal,
      },
    )
  }

  async listExtractionBatches(
    clientId: string,
    params: PaginationParams = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<ExtractionBatch>> {
    return this.request(
      withQuery(clientResourcePath(clientId, 'extraction-batches'), {
        page: params.page,
      }),
      { signal: options.signal },
    )
  }

  async getExtractionWorkspace(
    clientId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionWorkspacePayload>> {
    return this.request(clientResourcePath(clientId, 'extraction-workspace'), {
      signal: options.signal,
    })
  }

  async createExtractionBatch(
    clientId: string,
    input: CreateExtractionBatchInput,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionBatch>> {
    return this.request(clientResourcePath(clientId, 'extraction-batches'), {
      method: 'POST',
      body: JSON.stringify(input),
      signal: options.signal,
    })
  }

  async getExtractionBatch(
    clientId: string,
    extractionBatchId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<ExtractionBatch>> {
    return this.request(
      clientResourcePath(clientId, 'extraction-batches', extractionBatchId),
      { signal: options.signal },
    )
  }

  async listPosts(
    clientId: string,
    filters: PostFilters = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<Post>> {
    return this.request(
      withQuery(clientResourcePath(clientId, 'posts'), {
        project_id: filters.projectId,
        brand_id: filters.brandId,
        platform_id: filters.platformId,
        date_from: filters.dateFrom,
        date_to: filters.dateTo,
        search: filters.search,
        brand_type: filters.brandType,
        relevance: filters.relevance,
        per_page: filters.perPage,
        page: filters.page,
      }),
      { signal: options.signal },
    )
  }

  async getPost(
    clientId: string,
    postId: string,
    options: RequestOptions = {},
  ): Promise<ResourceResponse<Post>> {
    return this.request(clientResourcePath(clientId, 'posts', postId), {
      signal: options.signal,
    })
  }

  async listUsageLedger(
    clientId: string,
    params: PaginationParams = {},
    options: RequestOptions = {},
  ): Promise<PaginatedResponse<UsageLedgerEntry>> {
    return this.request(
      withQuery(clientResourcePath(clientId, 'usage-ledger'), {
        page: params.page,
      }),
      { signal: options.signal },
    )
  }

  private async request<T>(path: string, init: RequestInit): Promise<T> {
    const accessToken = await this.getAccessToken()

    if (accessToken === null || accessToken === '') {
      throw new ApiError(
        401,
        'An authenticated Supabase session is required.',
        null,
      )
    }

    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')
    headers.set('Authorization', `Bearer ${accessToken}`)

    if (init.body !== undefined) {
      headers.set('Content-Type', 'application/json')
    }

    const response = await this.fetcher(`${this.baseUrl}${path}`, {
      ...init,
      headers,
    })
    const body = await parseResponseBody(response)

    if (!response.ok) {
      const payload = errorPayload(body)
      const message =
        payload?.message ??
        (typeof body === 'string' ? body : `API request failed (${response.status}).`)

      throw new ApiError(response.status, message, payload)
    }

    return body as T
  }
}
