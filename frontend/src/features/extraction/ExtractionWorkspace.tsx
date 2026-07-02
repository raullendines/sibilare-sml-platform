import {
  type ReactNode,
  startTransition,
  useDeferredValue,
  useEffect,
  useMemo,
  useState,
} from 'react'
import {
  Activity,
  Bot,
  CircleDollarSign,
  FolderKanban,
  LoaderCircle,
  Play,
  Radio,
  RefreshCw,
  Save,
  Search,
  SquareTerminal,
} from 'lucide-react'
import type { SmlApiClient } from '../../api/client.ts'
import type {
  Brand,
  Client,
  CreateExtractionBatchInput,
  CreateExtractionConfigInput,
  CreateProjectInput,
  ExtractionBatch,
  ExtractionBatchJob,
  ExtractionConfig,
  ExtractionFrequency,
  Platform,
  Project,
  SelectionStrategy,
  UpdateExtractionConfigInput,
} from '../../api/types.ts'

interface ExtractionWorkspaceProps {
  api: SmlApiClient
  clientId: string
  activeClient: Client | undefined
  brands: Brand[]
  platforms: Platform[]
  view: 'configuration' | 'live'
  onChangeView: (view: 'configuration' | 'live') => void
}

const frequencyOptions: Array<{ value: ExtractionFrequency; label: string }> = [
  { value: 'daily', label: 'Diaria + 3 días' },
  { value: 'weekly', label: 'Semanal + 3 días' },
  { value: 'monthly', label: 'Mensual + 3 días' },
]

const strategyOptions: Array<{ value: SelectionStrategy; label: string }> = [
  { value: 'most_relevant', label: 'Más relevantes' },
  { value: 'most_recent', label: 'Más recientes' },
  { value: 'engagement_weighted', label: 'Ponderado por engagement' },
]

const emptyProjectForm: ProjectFormState = {
  name: '',
  description: '',
  default_data_frequency: '',
  brand_ids: [],
}

const emptyConfigForm: ConfigFormState = {
  project_id: '',
  brand_id: '',
  platform_id: '',
  search_query: '',
  frequency: '',
  max_posts_per_run: '100',
  selection_strategy: 'most_relevant',
  cost_limit_per_run: '',
  is_active: true,
}

type ProjectScope = 'all' | 'shared' | string

interface ProjectFormState {
  name: string
  description: string
  default_data_frequency: '' | ExtractionFrequency
  brand_ids: string[]
}

interface ConfigFormState {
  project_id: string
  brand_id: string
  platform_id: string
  search_query: string
  frequency: '' | ExtractionFrequency
  max_posts_per_run: string
  selection_strategy: SelectionStrategy
  cost_limit_per_run: string
  is_active: boolean
}

export function ExtractionWorkspace({
  api,
  clientId,
  activeClient,
  brands,
  platforms,
  view,
  onChangeView,
}: ExtractionWorkspaceProps) {
  const [projects, setProjects] = useState<Project[]>([])
  const [configs, setConfigs] = useState<ExtractionConfig[]>([])
  const [batches, setBatches] = useState<ExtractionBatch[]>([])
  const [currentBatch, setCurrentBatch] = useState<ExtractionBatch | null>(null)
  const [currentBatchId, setCurrentBatchId] = useState<string | null>(null)
  const [scope, setScope] = useState<ProjectScope>('all')
  const [configSearch, setConfigSearch] = useState('')
  const [projectSaving, setProjectSaving] = useState(false)
  const [configSaving, setConfigSaving] = useState(false)
  const [launching, setLaunching] = useState(false)
  const [refreshing, setRefreshing] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [projectEditorId, setProjectEditorId] = useState<string | null>(null)
  const [projectForm, setProjectForm] = useState<ProjectFormState>(emptyProjectForm)
  const [configEditorId, setConfigEditorId] = useState<string | null>(null)
  const [configForm, setConfigForm] = useState<ConfigFormState>(emptyConfigForm)
  const [selectedConfigIds, setSelectedConfigIds] = useState<string[]>([])
  const deferredConfigSearch = useDeferredValue(configSearch)

  const filteredConfigs = useMemo(() => {
    const search = deferredConfigSearch.trim().toLowerCase()

    return configs.filter((config) => {
      const matchesScope = scope === 'all'
        ? true
        : scope === 'shared'
          ? config.project_id === null
          : config.project_id === scope

      if (!matchesScope) return false

      if (search === '') return true

      return [
        config.search_query,
        config.brand?.name ?? '',
        config.platform?.name ?? '',
        config.project?.name ?? 'Compartido',
      ]
        .join(' ')
        .toLowerCase()
        .includes(search)
    })
  }, [configs, deferredConfigSearch, scope])

  const activeConfigCount = useMemo(
    () => filteredConfigs.filter((config) => config.is_active).length,
    [filteredConfigs],
  )

  useEffect(() => {
    const controller = new AbortController()
    let cancelled = false
    setLoading(true)
    setError(null)

    api.getExtractionWorkspace(clientId, { signal: controller.signal })
      .then(({ data }) => {
        if (cancelled) return

        startTransition(() => {
          setProjects(data.projects)
          setConfigs(data.configs)
          setBatches(data.batches)
          setScope((current) => current === 'all' ? current : resolveValidScope(current, data.projects))
          setCurrentBatchId((current) => current ?? data.batches[0]?.id ?? null)
        })
      })
      .catch((requestError: Error) => {
        if (requestError.name === 'AbortError') return
        if (!cancelled) setError(requestError.message)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
      controller.abort()
    }
  }, [api, clientId])

  useEffect(() => {
    setSelectedConfigIds((current) =>
      current.filter((configId) => filteredConfigs.some((config) => config.id === configId)),
    )
  }, [filteredConfigs])

  useEffect(() => {
    if (currentBatchId === null) {
      setCurrentBatch(null)
      return
    }

    let cancelled = false
    const loadBatch = () =>
      api.getExtractionBatch(clientId, currentBatchId)
        .then(({ data }) => {
          if (cancelled) return

          startTransition(() => {
            setCurrentBatch(data)
            setBatches((current) => mergeBatchIntoList(current, data))
          })
        })
        .catch((requestError: Error) => {
          if (!cancelled) setError(requestError.message)
        })

    void loadBatch()

    if (view !== 'live') {
      return () => {
        cancelled = true
      }
    }

    const intervalId = window.setInterval(() => {
      void loadBatch()
    }, 3000)

    return () => {
      cancelled = true
      window.clearInterval(intervalId)
    }
  }, [api, clientId, currentBatchId, view])

  function editProject(project: Project) {
    setProjectEditorId(project.id)
    setProjectForm({
      name: project.name,
      description: project.description ?? '',
      default_data_frequency: project.default_data_frequency ?? '',
      brand_ids: project.brands.map((brand) => brand.id),
    })
  }

  function resetProjectForm() {
    setProjectEditorId(null)
    setProjectForm(emptyProjectForm)
  }

  function editConfig(config: ExtractionConfig) {
    setConfigEditorId(config.id)
    setConfigForm({
      project_id: config.project_id ?? '',
      brand_id: config.brand_id,
      platform_id: config.platform_id,
      search_query: config.search_query,
      frequency: config.frequency ?? '',
      max_posts_per_run: String(config.max_posts_per_run),
      selection_strategy: config.selection_strategy,
      cost_limit_per_run: config.cost_limit_per_run ?? '',
      is_active: config.is_active,
    })
  }

  function resetConfigForm() {
    setConfigEditorId(null)
    setConfigForm({
      ...emptyConfigForm,
      project_id: scope !== 'all' && scope !== 'shared' ? scope : '',
    })
  }

  async function refreshOverview() {
    setRefreshing(true)
    setError(null)

    try {
      const { data } = await api.getExtractionWorkspace(clientId)

      startTransition(() => {
        setProjects(data.projects)
        setConfigs(data.configs)
        setBatches(data.batches)
        setCurrentBatchId((current) => current ?? data.batches[0]?.id ?? null)
      })
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo refrescar la consola de extracción.',
      )
    } finally {
      setRefreshing(false)
    }
  }

  async function saveProject() {
    setProjectSaving(true)
    setError(null)

    const input: CreateProjectInput = {
      name: projectForm.name.trim(),
      description: projectForm.description.trim() || null,
      default_data_frequency: projectForm.default_data_frequency || null,
      brand_ids: projectForm.brand_ids,
    }

    try {
      if (projectEditorId) {
        const { data } = await api.updateProject(clientId, projectEditorId, input)
        startTransition(() => {
          setProjects((current) => current.map((project) => project.id === data.id ? data : project))
        })
      } else {
        const { data } = await api.createProject(clientId, input)
        startTransition(() => {
          setProjects((current) => [...current, data].sort((left, right) => left.name.localeCompare(right.name)))
          setScope(data.id)
        })
      }

      resetProjectForm()
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo guardar el proyecto.',
      )
    } finally {
      setProjectSaving(false)
    }
  }

  async function saveConfig() {
    setConfigSaving(true)
    setError(null)

    const input: CreateExtractionConfigInput | UpdateExtractionConfigInput = {
      project_id: configForm.project_id || null,
      brand_id: configForm.brand_id,
      platform_id: configForm.platform_id,
      search_query: configForm.search_query.trim(),
      frequency: configForm.frequency || null,
      max_posts_per_run: Number(configForm.max_posts_per_run),
      selection_strategy: configForm.selection_strategy,
      cost_limit_per_run: configForm.cost_limit_per_run === '' ? null : Number(configForm.cost_limit_per_run),
      is_active: configForm.is_active,
    }

    try {
      if (configEditorId) {
        const { data } = await api.updateExtractionConfig(clientId, configEditorId, input)
        startTransition(() => {
          setConfigs((current) => current.map((config) => config.id === data.id ? data : config))
        })
      } else {
        const { data } = await api.createExtractionConfig(clientId, input as CreateExtractionConfigInput)
        startTransition(() => {
          setConfigs((current) => [data, ...current])
        })
      }

      resetConfigForm()
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo guardar la configuración de scraping.',
      )
    } finally {
      setConfigSaving(false)
    }
  }

  async function launchBatch() {
    setLaunching(true)
    setError(null)

    const targetConfigIds = selectedConfigIds.length > 0
      ? selectedConfigIds
      : filteredConfigs.filter((config) => config.is_active).map((config) => config.id)

    const input: CreateExtractionBatchInput = {
      project_id: scope !== 'all' && scope !== 'shared' ? scope : null,
      config_ids: targetConfigIds,
    }

    try {
      const { data } = await api.createExtractionBatch(clientId, input)
      startTransition(() => {
        setCurrentBatchId(data.id)
        setCurrentBatch(data)
        setBatches((current) => mergeBatchIntoList(current, data))
        onChangeView('live')
      })
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo lanzar el scraping manual.',
      )
    } finally {
      setLaunching(false)
    }
  }

  if (loading) {
    return (
      <main className="centered-state">
        <LoaderCircle className="spin" size={20} />
        <span>Preparando consola de scraping</span>
      </main>
    )
  }

  return (
    <main className="builder-shell extraction-shell">
      <header className="builder-header extraction-header">
        <div className="builder-title">
          <div>
            <span>{activeClient?.name ?? 'Cliente'} / Extracciones</span>
            <strong className="extraction-header-title">
              {view === 'configuration' ? 'Configuración operativa' : 'Ejecución en vivo'}
            </strong>
          </div>
        </div>

        <div className="header-actions">
          <div className="layout-mode-toggle">
            <button
              className={view === 'configuration' ? 'is-active' : ''}
              onClick={() => onChangeView('configuration')}
            >
              <FolderKanban size={15} />
              Configuración
            </button>
            <button
              className={view === 'live' ? 'is-active' : ''}
              onClick={() => onChangeView('live')}
            >
              <Activity size={15} />
              En vivo
            </button>
          </div>
          <button className="secondary-button" onClick={() => void refreshOverview()} disabled={refreshing}>
            {refreshing ? <LoaderCircle className="spin" size={15} /> : <RefreshCw size={15} />}
            Refrescar
          </button>
          <button className="primary-button" onClick={() => void launchBatch()} disabled={launching || activeConfigCount === 0}>
            {launching ? <LoaderCircle className="spin" size={15} /> : <Play size={15} />}
            Lanzar scraping
          </button>
        </div>
      </header>

      {error ? (
        <div className="error-banner">
          <span>{error}</span>
          <button onClick={() => setError(null)}>Cerrar</button>
        </div>
      ) : null}

      <section className="builder-body extraction-body">
        <div className="extraction-content">
          <div className="extraction-toolbar">
            <label className="workspace-switcher extraction-scope-switcher">
              <span>Proyecto</span>
              <select value={scope} onChange={(event) => setScope(event.target.value)}>
                <option value="all">Todo el cliente</option>
                <option value="shared">Configs compartidas</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>{project.name}</option>
                ))}
              </select>
            </label>

            <label className="search-field extraction-search">
              <Search size={14} />
              <input
                value={configSearch}
                onChange={(event) => setConfigSearch(event.target.value)}
                placeholder="Buscar por query, proyecto, marca o plataforma"
              />
            </label>
          </div>

          <div className="extraction-summary-grid">
            <MetricCard
              icon={<FolderKanban size={16} />}
              label="Proyectos activos"
              value={String(projects.filter((project) => project.status === 'active').length)}
              hint="Segmentación por cliente y proyecto"
            />
            <MetricCard
              icon={<Radio size={16} />}
              label="Configs visibles"
              value={String(filteredConfigs.length)}
              hint={`${activeConfigCount} activas listas para lanzar`}
            />
            <MetricCard
              icon={<Bot size={16} />}
              label="Batch actual"
              value={currentBatch ? humanBatchStatus(currentBatch.status) : 'Sin lote'}
              hint={currentBatch ? `${currentBatch.progress_percent}% completado` : 'Lanza uno manual para seguirlo aquí'}
            />
            <MetricCard
              icon={<CircleDollarSign size={16} />}
              label="Coste facturado"
              value={formatUsd(currentBatch?.summary.billed_cost_usd ?? '0')}
              hint={`Reservado ${formatUsd(currentBatch?.summary.reserved_cost_usd ?? '0')}`}
            />
          </div>

          {view === 'configuration' ? (
            <div className="extraction-config-grid">
              <section className="extraction-panel">
                <div className="extraction-panel-heading">
                  <div>
                    <strong>Proyectos</strong>
                    <span>Definen frecuencia heredada, scope de marcas y dashboards.</span>
                  </div>
                </div>

                <div className="extraction-project-list">
                  {projects.length === 0 ? (
                    <EmptyState
                      title="Todavía no hay proyectos"
                      body="Crea el primer proyecto para separar scraping por cliente/sub-área y heredar periodicidad."
                    />
                  ) : (
                    projects.map((project) => (
                      <button
                        className={`extraction-project-card ${scope === project.id ? 'active' : ''}`}
                        key={project.id}
                        onClick={() => {
                          setScope(project.id)
                          editProject(project)
                        }}
                      >
                        <div>
                          <strong>{project.name}</strong>
                          <span>{project.description || 'Sin descripción operativa.'}</span>
                        </div>
                        <small>{humanFrequency(project.default_data_frequency)}</small>
                      </button>
                    ))
                  )}
                </div>

                <form
                  className="extraction-form"
                  onSubmit={(event) => {
                    event.preventDefault()
                    void saveProject()
                  }}
                >
                  <div className="extraction-form-heading">
                    <strong>{projectEditorId ? 'Editar proyecto' : 'Nuevo proyecto'}</strong>
                    {projectEditorId ? (
                      <button className="secondary-button small" type="button" onClick={resetProjectForm}>
                        Nuevo
                      </button>
                    ) : null}
                  </div>
                  <label>
                    Nombre
                    <input
                      value={projectForm.name}
                      onChange={(event) => setProjectForm((current) => ({ ...current, name: event.target.value }))}
                      required
                    />
                  </label>
                  <label>
                    Descripción
                    <textarea
                      value={projectForm.description}
                      onChange={(event) => setProjectForm((current) => ({ ...current, description: event.target.value }))}
                      rows={3}
                    />
                  </label>
                  <label>
                    Frecuencia por defecto
                    <select
                      value={projectForm.default_data_frequency}
                      onChange={(event) => setProjectForm((current) => ({
                        ...current,
                        default_data_frequency: event.target.value as '' | ExtractionFrequency,
                      }))}
                    >
                      <option value="">Heredar del cliente</option>
                      {frequencyOptions.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </label>
                  <div className="extraction-checkbox-grid">
                    {brands.map((brand) => (
                      <label className="extraction-check-option" key={brand.id}>
                        <input
                          type="checkbox"
                          checked={projectForm.brand_ids.includes(brand.id)}
                          onChange={() => setProjectForm((current) => ({
                            ...current,
                            brand_ids: toggleArrayValue(current.brand_ids, brand.id),
                          }))}
                        />
                        <span>{brand.name}</span>
                      </label>
                    ))}
                  </div>
                  <button className="primary-button" disabled={projectSaving}>
                    {projectSaving ? <LoaderCircle className="spin" size={15} /> : <Save size={15} />}
                    Guardar proyecto
                  </button>
                </form>
              </section>

              <section className="extraction-panel">
                <div className="extraction-panel-heading">
                  <div>
                    <strong>Configs de scraping</strong>
                    <span>Selecciona scope, query, frecuencia y límite de posts por cada worker.</span>
                  </div>
                </div>

                <div className="extraction-config-table">
                  {filteredConfigs.length === 0 ? (
                    <EmptyState
                      title="No hay configs para este scope"
                      body="Crea una configuración para poder lanzar scraping manual y seguir el progreso en tiempo real."
                    />
                  ) : (
                    filteredConfigs.map((config) => (
                      <button
                        className={`extraction-config-row ${selectedConfigIds.includes(config.id) ? 'selected' : ''}`}
                        key={config.id}
                        onClick={() => editConfig(config)}
                      >
                        <label className="extraction-row-check" onClick={(event) => event.stopPropagation()}>
                          <input
                            type="checkbox"
                            checked={selectedConfigIds.includes(config.id)}
                            onChange={() => setSelectedConfigIds((current) => toggleArrayValue(current, config.id))}
                          />
                        </label>
                        <div>
                          <strong>{config.search_query}</strong>
                          <span>{config.project?.name ?? 'Compartido'} · {config.brand?.name ?? 'Marca'} · {config.platform?.name ?? 'Plataforma'}</span>
                        </div>
                        <small>{humanFrequency(config.effective_frequency)}</small>
                        <small>{config.max_posts_per_run} posts</small>
                      </button>
                    ))
                  )}
                </div>

                <form
                  className="extraction-form"
                  onSubmit={(event) => {
                    event.preventDefault()
                    void saveConfig()
                  }}
                >
                  <div className="extraction-form-heading">
                    <strong>{configEditorId ? 'Editar configuración' : 'Nueva configuración'}</strong>
                    {configEditorId ? (
                      <button className="secondary-button small" type="button" onClick={resetConfigForm}>
                        Nueva
                      </button>
                    ) : null}
                  </div>
                  <div className="extraction-form-grid">
                    <label>
                      Proyecto
                      <select
                        value={configForm.project_id}
                        onChange={(event) => setConfigForm((current) => ({ ...current, project_id: event.target.value }))}
                      >
                        <option value="">Compartido entre proyectos</option>
                        {projects.map((project) => (
                          <option key={project.id} value={project.id}>{project.name}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Marca
                      <select
                        value={configForm.brand_id}
                        onChange={(event) => setConfigForm((current) => ({ ...current, brand_id: event.target.value }))}
                        required
                      >
                        <option value="">Selecciona marca</option>
                        {brands.map((brand) => (
                          <option key={brand.id} value={brand.id}>{brand.name}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Plataforma
                      <select
                        value={configForm.platform_id}
                        onChange={(event) => setConfigForm((current) => ({ ...current, platform_id: event.target.value }))}
                        required
                      >
                        <option value="">Selecciona plataforma</option>
                        {platforms.map((platform) => (
                          <option key={platform.id} value={platform.id}>{platform.name}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Frecuencia
                      <select
                        value={configForm.frequency}
                        onChange={(event) => setConfigForm((current) => ({
                          ...current,
                          frequency: event.target.value as '' | ExtractionFrequency,
                        }))}
                      >
                        <option value="">Heredar</option>
                        {frequencyOptions.map((option) => (
                          <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                      </select>
                    </label>
                    <label className="extraction-form-span-2">
                      Search query
                      <input
                        value={configForm.search_query}
                        onChange={(event) => setConfigForm((current) => ({ ...current, search_query: event.target.value }))}
                        placeholder="Ej. Colacao OR @colacao"
                        required
                      />
                    </label>
                    <label>
                      Máx. posts
                      <input
                        value={configForm.max_posts_per_run}
                        onChange={(event) => setConfigForm((current) => ({ ...current, max_posts_per_run: event.target.value }))}
                        inputMode="numeric"
                        required
                      />
                    </label>
                    <label>
                      Estrategia
                      <select
                        value={configForm.selection_strategy}
                        onChange={(event) => setConfigForm((current) => ({
                          ...current,
                          selection_strategy: event.target.value as SelectionStrategy,
                        }))}
                      >
                        {strategyOptions.map((option) => (
                          <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Tope USD/run
                      <input
                        value={configForm.cost_limit_per_run}
                        onChange={(event) => setConfigForm((current) => ({ ...current, cost_limit_per_run: event.target.value }))}
                        inputMode="decimal"
                        placeholder="Opcional"
                      />
                    </label>
                    <label className="extraction-check-option inline">
                      <input
                        type="checkbox"
                        checked={configForm.is_active}
                        onChange={(event) => setConfigForm((current) => ({ ...current, is_active: event.target.checked }))}
                      />
                      <span>Activa para scheduler y lanzamientos manuales</span>
                    </label>
                  </div>
                  <button className="primary-button" disabled={configSaving}>
                    {configSaving ? <LoaderCircle className="spin" size={15} /> : <Save size={15} />}
                    Guardar configuración
                  </button>
                </form>
              </section>
            </div>
          ) : (
            <div className="extraction-live-grid">
              <section className="extraction-panel">
                <div className="extraction-panel-heading">
                  <div>
                    <strong>Lote en seguimiento</strong>
                    <span>Polling automático cada 3s. El coste reservado se ve antes del facturado real.</span>
                  </div>
                  {currentBatch ? (
                    <span className={`extraction-status-pill ${currentBatch.status}`}>
                      {humanBatchStatus(currentBatch.status)}
                    </span>
                  ) : null}
                </div>

                {currentBatch ? (
                  <>
                    <ProgressBar value={currentBatch.progress_percent} />
                    <div className="extraction-live-metrics">
                      <LiveMetric label="Jobs" value={`${currentBatch.summary.completed_jobs}/${currentBatch.summary.total_jobs}`} />
                      <LiveMetric label="Activos" value={String(currentBatch.summary.active_jobs)} />
                      <LiveMetric label="Reservado" value={formatUsd(currentBatch.summary.reserved_cost_usd)} />
                      <LiveMetric label="Facturado" value={formatUsd(currentBatch.summary.billed_cost_usd)} />
                    </div>

                    <div className="worker-grid">
                      {(currentBatch.jobs ?? []).map((job) => (
                        <WorkerCard key={job.id} job={job} />
                      ))}
                    </div>
                  </>
                ) : (
                  <EmptyState
                    title="No hay un lote seleccionado"
                    body="Lanza un scraping manual o elige uno reciente para ver workers, progreso, costes y retries."
                  />
                )}
              </section>

              <section className="extraction-panel">
                <div className="extraction-panel-heading">
                  <div>
                    <strong>Lotes recientes</strong>
                    <span>Historial inmediato para volver a inspeccionar workers, reintentos y costes.</span>
                  </div>
                </div>
                <div className="extraction-batch-list">
                  {batches.length === 0 ? (
                    <EmptyState
                      title="Aún no hay lotes manuales"
                      body="Al lanzar el primer scraping manual aparecerá aquí con su barra de progreso y tarjetas de worker."
                    />
                  ) : (
                    batches.map((batch) => (
                      <button
                        className={`extraction-batch-card ${currentBatchId === batch.id ? 'active' : ''}`}
                        key={batch.id}
                        onClick={() => setCurrentBatchId(batch.id)}
                      >
                        <div className="extraction-batch-card-top">
                          <strong>{batch.project?.name ?? 'Cliente completo'}</strong>
                          <span className={`extraction-status-pill ${batch.status}`}>
                            {humanBatchStatus(batch.status)}
                          </span>
                        </div>
                        <span>{formatLaunchMoment(batch.launched_at)}</span>
                        <div className="extraction-batch-card-foot">
                          <small>{batch.summary.total_jobs} jobs</small>
                          <small>{formatUsd(batch.summary.billed_cost_usd)} facturados</small>
                        </div>
                        <ProgressBar value={batch.progress_percent} compact />
                      </button>
                    ))
                  )}
                </div>
              </section>
            </div>
          )}
        </div>
      </section>
    </main>
  )
}

function MetricCard({
  icon,
  label,
  value,
  hint,
}: {
  icon: ReactNode
  label: string
  value: string
  hint: string
}) {
  return (
    <article className="extraction-metric-card">
      <span className="extraction-metric-icon">{icon}</span>
      <small>{label}</small>
      <strong>{value}</strong>
      <span>{hint}</span>
    </article>
  )
}

function ProgressBar({ value, compact = false }: { value: number; compact?: boolean }) {
  return (
    <div className={`progress-shell ${compact ? 'compact' : ''}`}>
      <div className="progress-track">
        <div className="progress-fill" style={{ width: `${Math.max(0, Math.min(value, 100))}%` }} />
      </div>
      {!compact ? <strong>{value}%</strong> : null}
    </div>
  )
}

function LiveMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="live-metric">
      <small>{label}</small>
      <strong>{value}</strong>
    </div>
  )
}

function WorkerCard({ job }: { job: ExtractionBatchJob }) {
  const run = job.latest_run

  return (
    <article className="worker-card">
      <div className="worker-card-head">
        <div>
          <strong>{job.config.brand?.name ?? 'Marca'} / {job.config.platform?.name ?? 'Plataforma'}</strong>
          <span>{job.config.search_query}</span>
        </div>
        <span className={`extraction-status-pill ${mapJobStatus(job.status)}`}>
          {humanJobStatus(job.status)}
        </span>
      </div>

      <div className="worker-card-grid">
        <div>
          <small>Proyecto</small>
          <strong>{job.config.project?.name ?? 'Compartido'}</strong>
        </div>
        <div>
          <small>Frecuencia</small>
          <strong>{humanFrequency(job.config.effective_frequency)}</strong>
        </div>
        <div>
          <small>Intento</small>
          <strong>{run?.attempt_number ?? job.retry_count + 1}</strong>
        </div>
        <div>
          <small>Actor</small>
          <strong>{run?.agent?.name ?? 'Pendiente'}</strong>
        </div>
        <div>
          <small>Posts</small>
          <strong>{run?.posts_stored ?? 0}/{run?.posts_requested ?? job.config.max_posts_per_run}</strong>
        </div>
        <div>
          <small>Coste</small>
          <strong>{formatUsd(run?.billed_cost_usd ?? job.reserved_cost_usd)}</strong>
        </div>
      </div>

      <div className="worker-card-foot">
        <span>{run?.external_run_id ?? 'Sin run externo todavía'}</span>
        <span>{run?.started_at ? formatLaunchMoment(run.started_at) : formatLaunchMoment(job.scheduled_for)}</span>
      </div>

      {run?.error_message ? (
        <div className="worker-inline-error">{run.error_message}</div>
      ) : null}
    </article>
  )
}

function EmptyState({ title, body }: { title: string; body: string }) {
  return (
    <div className="template-empty-state compact extraction-empty-state">
      <SquareTerminal size={16} />
      <strong>{title}</strong>
      <span>{body}</span>
    </div>
  )
}

function resolveValidScope(scope: ProjectScope, projects: Project[]): ProjectScope {
  if (scope === 'all' || scope === 'shared') return scope
  return projects.some((project) => project.id === scope) ? scope : 'all'
}

function toggleArrayValue(values: string[], value: string): string[] {
  return values.includes(value)
    ? values.filter((item) => item !== value)
    : [...values, value]
}

function humanFrequency(value: ExtractionFrequency | null | undefined): string {
  if (value === 'daily') return 'Diaria'
  if (value === 'weekly') return 'Semanal'
  if (value === 'monthly') return 'Mensual'
  return 'Heredada'
}

function humanBatchStatus(status: ExtractionBatch['status']): string {
  if (status === 'queued') return 'En cola'
  if (status === 'running') return 'Ejecutando'
  if (status === 'completed') return 'Completado'
  if (status === 'partial') return 'Parcial'
  if (status === 'skipped') return 'Saltado'
  return 'Fallido'
}

function humanJobStatus(status: ExtractionBatchJob['status']): string {
  if (status === 'pending') return 'Pendiente'
  if (status === 'locked') return 'Reservado'
  if (status === 'launching') return 'Lanzando'
  if (status === 'waiting_provider') return 'Esperando actor'
  if (status === 'finalizing') return 'Finalizando'
  if (status === 'completed') return 'Completado'
  if (status === 'failed') return 'Fallido'
  if (status === 'cancelled') return 'Cancelado'
  return 'Saltado'
}

function mapJobStatus(status: ExtractionBatchJob['status']): 'queued' | 'running' | 'completed' | 'partial' | 'failed' {
  if (status === 'completed') return 'completed'
  if (status === 'failed' || status === 'cancelled') return 'failed'
  if (status === 'skipped') return 'partial'
  if (status === 'pending') return 'queued'
  return 'running'
}

function mergeBatchIntoList(current: ExtractionBatch[], next: ExtractionBatch): ExtractionBatch[] {
  const existing = current.find((batch) => batch.id === next.id)

  if (existing) {
    return current
      .map((batch) => batch.id === next.id ? next : batch)
      .sort((left, right) => Date.parse(right.launched_at ?? '') - Date.parse(left.launched_at ?? ''))
  }

  return [next, ...current]
    .sort((left, right) => Date.parse(right.launched_at ?? '') - Date.parse(left.launched_at ?? ''))
}

function formatUsd(value: string | null | undefined): string {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(Number.isFinite(amount) ? amount : 0)
}

function formatLaunchMoment(value: string | null | undefined): string {
  if (!value) return 'Sin fecha'

  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
