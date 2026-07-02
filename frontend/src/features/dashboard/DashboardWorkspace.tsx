import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import GridLayout, { type Layout } from 'react-grid-layout'
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  CartesianGrid,
  Cell,
  PieChart,
  Pie,
  ResponsiveContainer,
  Tooltip,
  XAxis,
} from 'recharts'
import {
  BarChart3,
  Bell,
  ChevronLeft,
  ChevronDown,
  CircleGauge,
  Eye,
  EyeOff,
  FileText,
  GripVertical,
  LayoutDashboard,
  LineChart as LineChartIcon,
  LoaderCircle,
  LogOut,
  MessageSquareText,
  MoreHorizontal,
  PanelRightClose,
  PieChart as PieChartIcon,
  Plus,
  Radio,
  RefreshCw,
  Save,
  Search,
  Settings2,
  SlidersHorizontal,
  Table2,
  Trash2,
  Users,
  X,
} from 'lucide-react'
import type { SmlApiClient } from '../../api/client.ts'
import type {
  Brand,
  Client,
  Dashboard,
  DashboardFilter,
  DashboardFilterField,
  DashboardWidget,
  JsonObject,
  JsonValue,
  MetricQueryInput,
  MetricQueryResult,
  Platform,
  Post,
  SaveDashboardLayoutInput,
  VisualizationType,
  WidgetBuilderCatalog,
  WidgetBuilderMetric,
  WidgetBuilderSource,
  WidgetTemplate,
  WidgetType,
} from '../../api/types.ts'
import 'react-grid-layout/css/styles.css'
import 'react-resizable/css/styles.css'

interface DashboardWorkspaceProps {
  api: SmlApiClient
  userEmail: string
  onSignOut: () => void
}

const chartPalette = ['#202020', '#71717a', '#a1a1aa', '#d4d4d8', '#52525b']

const widgetIcons: Record<WidgetType, typeof CircleGauge> = {
  kpi: CircleGauge,
  line: LineChartIcon,
  bar: BarChart3,
  pie: PieChartIcon,
  table: Table2,
  map: LayoutDashboard,
  mentions_feed: MessageSquareText,
  text: FileText,
  heading: FileText,
  divider: MoreHorizontal,
}

type InsertionTarget =
  | { kind: 'append'; widgetId: null }
  | { kind: 'above' | 'below' | 'left' | 'right'; widgetId: string }

type TemplateGroup =
  | 'overview'
  | 'mentions'
  | 'competitors'
  | 'operations'
  | 'content'
  | 'personalized'

interface PersonalizedWidgetDraft {
  metric: WidgetBuilderMetric
  visualization: VisualizationType
  title: string
  config: JsonObject
  focusFieldCode?: string
}

export function DashboardWorkspace({
  api,
  userEmail,
  onSignOut,
}: DashboardWorkspaceProps) {
  const [clients, setClients] = useState<Client[]>([])
  const [clientId, setClientId] = useState('')
  const [dashboards, setDashboards] = useState<Dashboard[]>([])
  const [dashboardId, setDashboardId] = useState('')
  const [templates, setTemplates] = useState<WidgetTemplate[]>([])
  const [builderCatalog, setBuilderCatalog] = useState<WidgetBuilderCatalog | null>(null)
  const [brands, setBrands] = useState<Brand[]>([])
  const [platforms, setPlatforms] = useState<Platform[]>([])
  const [dashboard, setDashboard] = useState<Dashboard | null>(null)
  const [widgets, setWidgets] = useState<DashboardWidget[]>([])
  const [filters, setFilters] = useState<DashboardFilter[]>([])
  const [selectedWidgetId, setSelectedWidgetId] = useState<string | null>(null)
  const [addMenuOpen, setAddMenuOpen] = useState(false)
  const [propertiesOpen, setPropertiesOpen] = useState(true)
  const [previewMode, setPreviewMode] = useState(false)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [dirty, setDirty] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [newDashboardOpen, setNewDashboardOpen] = useState(false)
  const [metricResults, setMetricResults] = useState<Record<string, MetricQueryResult>>({})
  const [metricsLoading, setMetricsLoading] = useState(false)
  const [filterValues, setFilterValues] = useState<Partial<Record<DashboardFilterField, JsonValue>>>({})
  const [insertionTarget, setInsertionTarget] = useState<InsertionTarget>({ kind: 'append', widgetId: null })
  const [activeTemplateGroup, setActiveTemplateGroup] = useState<TemplateGroup>('overview')
  const skipNextPreferenceSave = useRef(true)

  const activeClient = clients.find((client) => client.id === clientId)
  const selectedWidget =
    widgets.find((widget) => widget.id === selectedWidgetId) ?? null
  const metricQueryKey = useMemo(
    () => JSON.stringify(buildMetricQueries(widgets, filters, filterValues)),
    [widgets, filters, filterValues],
  )

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)

    Promise.all([
      api.listClients({}, { signal: controller.signal }),
      api.listWidgetTemplates({ signal: controller.signal }),
      api.getWidgetBuilderCatalog({ signal: controller.signal }),
    ])
      .then(([clientResponse, templateResponse, builderCatalogResponse]) => {
        setClients(clientResponse.data)
        setTemplates(templateResponse.data)
        setBuilderCatalog(builderCatalogResponse.data)
        setClientId((current) => current || clientResponse.data[0]?.id || '')
      })
      .catch((requestError: Error) => {
        if (requestError.name !== 'AbortError') setError(requestError.message)
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [api])

  useEffect(() => {
    if (!clientId) return
    const controller = new AbortController()

    Promise.all([
      api.listDashboards(clientId, { signal: controller.signal }),
      api.listBrands(clientId, {}, { signal: controller.signal }),
      api.listClientPlatforms(clientId, { signal: controller.signal }),
    ])
      .then(([dashboardResponse, brandResponse, platformResponse]) => {
        setDashboards(dashboardResponse.data)
        setBrands(brandResponse.data)
        setPlatforms(platformResponse.data)
        setDashboardId((current) => {
          if (dashboardResponse.data.some((item) => item.id === current)) return current
          return dashboardResponse.data.find((item) => item.is_default)?.id
            ?? dashboardResponse.data[0]?.id
            ?? ''
        })
      })
      .catch((requestError: Error) => {
        if (requestError.name !== 'AbortError') setError(requestError.message)
      })

    return () => controller.abort()
  }, [api, clientId])

  const loadDashboard = useCallback(
    async (nextDashboardId: string) => {
      if (!clientId || !nextDashboardId) {
        setDashboard(null)
        setWidgets([])
        setFilters([])
        setFilterValues({})
        setMetricResults({})
        return
      }

      setLoading(true)
      setError(null)
      try {
        const { data } = await api.getDashboard(clientId, nextDashboardId)
        setDashboard(data)
        setWidgets(data.widgets ?? [])
        setFilters(data.filters ?? [])
        const defaultFilters = Object.fromEntries(
          (data.filters ?? []).map((filter) => [filter.field_code, filter.default_value]),
        )
        setFilterValues({
          ...defaultFilters,
          ...(data.viewer_preferences?.filter_values ?? {}),
        })
        setSelectedWidgetId(null)
        setInsertionTarget({ kind: 'append', widgetId: null })
        setAddMenuOpen(false)
        skipNextPreferenceSave.current = true
        setDirty(false)
      } catch (requestError) {
        setError(
          requestError instanceof Error
            ? requestError.message
            : 'No se pudo abrir el dashboard.',
        )
      } finally {
        setLoading(false)
      }
    },
    [api, clientId],
  )

  useEffect(() => {
    void loadDashboard(dashboardId)
  }, [dashboardId, loadDashboard])

  useEffect(() => {
    if (!clientId || !dashboardId) {
      setMetricResults({})
      setMetricsLoading(false)
      return
    }

    const queries = JSON.parse(metricQueryKey) as MetricQueryInput[]

    if (queries.length === 0) {
      setMetricResults({})
      setMetricsLoading(false)
      return
    }

    const controller = new AbortController()
    setError(null)
    setMetricsLoading(true)

    api
      .queryMetrics(clientId, { queries }, { signal: controller.signal })
      .then(({ data }) => {
        setMetricResults(Object.fromEntries(data.map((result) => [result.key, result])))
      })
      .catch((requestError: Error) => {
        if (requestError.name !== 'AbortError') setError(requestError.message)
      })
      .finally(() => {
        if (!controller.signal.aborted) setMetricsLoading(false)
      })

    return () => controller.abort()
  }, [api, clientId, dashboardId, metricQueryKey])

  useEffect(() => {
    if (!clientId || !dashboardId || !dashboard || !dashboard.preferences_supported) return

    if (skipNextPreferenceSave.current) {
      skipNextPreferenceSave.current = false
      return
    }

    const timeoutId = window.setTimeout(() => {
      void api.saveDashboardPreferences(clientId, dashboardId, {
        filter_values: filterValues,
      }).catch(() => {
        setError('No se pudieron guardar las preferencias de filtros.')
      })
    }, 350)

    return () => window.clearTimeout(timeoutId)
  }, [api, clientId, dashboard, dashboardId, filterValues])

  function updateDashboardName(name: string) {
    setDashboard((current) => (current ? { ...current, name } : current))
    setDirty(true)
  }

  function updateWidget(
    widgetId: string,
    changes: Partial<DashboardWidget>,
  ) {
    setWidgets((current) =>
      current.map((widget) =>
        widget.id === widgetId ? { ...widget, ...changes } : widget,
      ),
    )
    setDirty(true)
  }

  function insertNewWidget(nextWidget: DashboardWidget) {
    if (!dashboard) return

    setWidgets((current) => {
      const updated = insertWidgetAtTarget(
        current,
        nextWidget,
        dashboard.grid_columns,
        insertionTarget,
      )

      return updated.map((widget, index) => ({ ...widget, sort_order: index }))
    })
    setSelectedWidgetId(nextWidget.id)
    setInsertionTarget({ kind: 'append', widgetId: null })
    setAddMenuOpen(false)
    setPropertiesOpen(true)
    setDirty(true)
  }

  function addTemplate(template: WidgetTemplate) {
    if (!dashboard) return
    const nextWidget = createWidgetFromTemplate(template, dashboard, widgets.length)
    insertNewWidget(nextWidget)
  }

  function addPersonalizedWidget(draft: PersonalizedWidgetDraft) {
    if (!dashboard) return
    const nextWidget = createPersonalizedWidget(draft, dashboard, widgets.length)
    insertNewWidget(nextWidget)
  }

  function requestAddWidget(target: InsertionTarget, group?: TemplateGroup) {
    setInsertionTarget(target)
    if (group) setActiveTemplateGroup(group)
    setAddMenuOpen(true)
  }

  function removeWidget(widgetId: string) {
    setWidgets((current) =>
      current
        .filter((widget) => widget.id !== widgetId)
        .map((widget, index) => ({ ...widget, sort_order: index })),
    )
    setSelectedWidgetId(null)
    setDirty(true)
  }

  function onLayoutChange(layout: Layout[]) {
    if (previewMode) return

    const layoutById = new Map(layout.map((item) => [item.i, item]))
    const hasChanges = widgets.some((widget) => {
      const item = layoutById.get(widget.id)

      return item !== undefined && (
        widget.grid_x !== item.x ||
        widget.grid_y !== item.y ||
        widget.grid_width !== item.w ||
        widget.grid_height !== item.h
      )
    })

    if (!hasChanges) return

    setWidgets((current) => current.map((widget) => {
      const item = layoutById.get(widget.id)

      return item
        ? {
            ...widget,
            grid_x: item.x,
            grid_y: item.y,
            grid_width: item.w,
            grid_height: item.h,
          }
        : widget
    }))
    setDirty(true)
  }

  async function save(): Promise<boolean> {
    if (!dashboard || !clientId) return false
    setSaving(true)
    setError(null)

    const payload: SaveDashboardLayoutInput = {
      widgets: widgets.map((widget, index) => ({
        id: widget.id.startsWith('new-') ? null : widget.id,
        widget_template_id: widget.widget_template_id,
        widget_type: widget.widget_type,
        visualization_type: widget.visualization_type,
        metric_code: widget.metric_code,
        title: widget.title,
        description: widget.description,
        grid_x: widget.grid_x,
        grid_y: widget.grid_y,
        grid_width: widget.grid_width,
        grid_height: widget.grid_height,
        min_width: widget.min_width,
        min_height: widget.min_height,
        sort_order: index,
        config: widget.config,
        filters: widget.filters,
        is_visible: widget.is_visible,
      })),
      filters: filters.map((filter, index) => ({
        id: filter.id,
        field_code: filter.field_code,
        label: filter.label,
        filter_type: filter.filter_type,
        default_value: filter.default_value,
        config: filter.config,
        sort_order: index,
        is_visible: filter.is_visible,
      })),
    }

    try {
      if (dashboard.name.trim() === '') {
        throw new Error('El dashboard necesita un nombre.')
      }
      await api.updateDashboard(clientId, dashboard.id, {
        name: dashboard.name.trim(),
        layout_mode: dashboard.layout_mode,
      })
      const { data } = await api.saveDashboardLayout(
        clientId,
        dashboard.id,
        payload,
      )
      setDashboard(data)
      setWidgets(data.widgets ?? [])
      setFilters(data.filters ?? [])
      setDashboards((current) =>
        current.map((item) =>
          item.id === data.id
            ? { ...item, name: data.name, status: data.status }
            : item,
        ),
      )
      setSelectedWidgetId(null)
      setDirty(false)
      return true
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo guardar el dashboard.',
      )
      return false
    } finally {
      setSaving(false)
    }
  }

  async function publish() {
    if (!dashboard || !clientId) return
    if (dirty && !(await save())) return
    setPublishing(true)
    setError(null)

    try {
      const { data } = await api.publishDashboard(clientId, dashboard.id)
      setDashboard((current) =>
        current
          ? {
              ...current,
              status: 'published',
              current_version_number: data.version_number,
            }
          : current,
      )
      setDashboards((current) =>
        current.map((item) =>
          item.id === dashboard.id
            ? {
                ...item,
                status: 'published',
                current_version_number: data.version_number,
              }
            : item,
        ),
      )
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo publicar el dashboard.',
      )
    } finally {
      setPublishing(false)
    }
  }

  async function createDashboard(name: string, blank: boolean) {
    if (!clientId) return
    setLoading(true)
    setError(null)

    try {
      const { data } = await api.createDashboard(clientId, {
        name,
        starter_template: blank ? 'blank' : 'social-listening-overview',
      })
      setDashboards((current) => [...current, data])
      setDashboardId(data.id)
      setNewDashboardOpen(false)
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo crear el dashboard.',
      )
    } finally {
      setLoading(false)
    }
  }

  if (loading && clients.length === 0) {
    return (
      <main className="centered-state">
        <LoaderCircle className="spin" size={20} />
        <span>Cargando espacio de trabajo</span>
      </main>
    )
  }

  return (
    <div className="workspace">
      <aside className="app-sidebar">
        <div className="sidebar-brand">
          <span className="brand-mark"><Radio size={15} /></span>
          <strong>Sibilare</strong>
          <button className="icon-button" aria-label="Notificaciones" title="Notificaciones">
            <Bell size={16} />
          </button>
        </div>

        <label className="workspace-switcher">
          <span>Cliente</span>
          <select
            value={clientId}
            onChange={(event) => setClientId(event.target.value)}
          >
            {clients.map((client) => (
              <option key={client.id} value={client.id}>{client.name}</option>
            ))}
          </select>
          <ChevronDown size={14} />
        </label>

        <nav className="primary-nav" aria-label="Navegacion principal">
          <button className="nav-item active"><LayoutDashboard size={16} /> Dashboards</button>
          <button className="nav-item"><MessageSquareText size={16} /> Menciones</button>
          <button className="nav-item"><Users size={16} /> Benchmarking</button>
          <button className="nav-item"><FileText size={16} /> Informes</button>
        </nav>

        <div className="sidebar-section">
          <div className="sidebar-section-heading">
            <span>Dashboards</span>
            <button
              className="icon-button"
              onClick={() => setNewDashboardOpen(true)}
              aria-label="Nuevo dashboard"
              title="Nuevo dashboard"
            >
              <Plus size={15} />
            </button>
          </div>
          <div className="dashboard-list">
            {dashboards.map((item) => (
              <button
                className={`dashboard-link ${item.id === dashboardId ? 'active' : ''}`}
                key={item.id}
                onClick={() => setDashboardId(item.id)}
              >
                <span className={`status-dot ${item.status}`} />
                <span>{item.name}</span>
                {item.is_default ? <small>Inicio</small> : null}
              </button>
            ))}
          </div>
        </div>

        <div className="sidebar-footer">
          <button className="nav-item"><Settings2 size={16} /> Ajustes</button>
          <button className="user-row" onClick={onSignOut}>
            <span className="avatar">{userEmail.slice(0, 1).toUpperCase()}</span>
            <span>{userEmail}</span>
            <LogOut size={14} />
          </button>
        </div>
      </aside>

      <main className="builder-shell">
        <header className="builder-header">
          <div className="builder-title">
            <div>
              <span>{activeClient?.name ?? 'Cliente'} / Dashboard</span>
              <input
                value={dashboard?.name ?? ''}
                onChange={(event) => updateDashboardName(event.target.value)}
                disabled={!dashboard || previewMode}
                aria-label="Nombre del dashboard"
              />
            </div>
          </div>

          <div className="header-actions">
            {dashboard ? (
              <div className="layout-mode-toggle">
                <button onClick={() => requestAddWidget({ kind: 'append', widgetId: null })}>
                  <Plus size={15} />
                  Anadir
                </button>
              </div>
            ) : null}
            {dashboard ? (
              <span className={`publish-state ${dashboard.status}`}>
                <span />
                {dashboard.status === 'published'
                  ? `Publicado v${dashboard.current_version_number}`
                  : dirty
                    ? 'Cambios sin guardar'
                    : 'Borrador guardado'}
              </span>
            ) : null}
            <button
              className="secondary-button"
              onClick={() => setPreviewMode((current) => !current)}
              disabled={!dashboard}
            >
              {previewMode ? <EyeOff size={15} /> : <Eye size={15} />}
              {previewMode ? 'Editar' : 'Vista previa'}
            </button>
            <button
              className="secondary-button"
              onClick={() => void save()}
              disabled={!dashboard || saving || !dirty}
            >
              {saving ? <LoaderCircle className="spin" size={15} /> : <Save size={15} />}
              Guardar
            </button>
            <button
              className="primary-button"
              onClick={() => void publish()}
              disabled={!dashboard || publishing}
            >
              {publishing ? <LoaderCircle className="spin" size={15} /> : <Radio size={15} />}
              Publicar
            </button>
            <button
              className="icon-button"
              onClick={() => setPropertiesOpen((current) => !current)}
              aria-label="Alternar propiedades"
              title="Alternar propiedades"
            >
              <PanelRightClose size={16} />
            </button>
          </div>
        </header>

        {error ? (
          <div className="error-banner">
            <span>{error}</span>
            <button onClick={() => setError(null)}>Cerrar</button>
          </div>
        ) : null}

        <section
          className={`builder-body ${propertiesOpen && selectedWidget && !previewMode ? 'with-properties' : ''}`}
        >
          <div className="canvas-column">
            <GlobalFilters
              filters={filters}
              values={filterValues}
              brands={brands}
              platforms={platforms}
              previewMode={previewMode}
              onChange={(field, value) => {
                setFilterValues((current) => ({ ...current, [field]: value }))
              }}
            />
            {dashboard ? (
              <DashboardCanvas
                dashboard={dashboard}
                widgets={widgets}
                metricResults={metricResults}
                metricsLoading={metricsLoading}
                selectedWidgetId={selectedWidgetId}
                previewMode={previewMode}
                insertionTarget={insertionTarget}
                onSelect={setSelectedWidgetId}
                onRequestInsert={requestAddWidget}
                onLayoutChange={onLayoutChange}
              />
            ) : (
              <EmptyDashboards onCreate={() => setNewDashboardOpen(true)} />
            )}
          </div>

          {propertiesOpen && selectedWidget && !previewMode ? (
            <WidgetProperties
              widget={selectedWidget}
              onChange={(changes) => updateWidget(selectedWidget.id, changes)}
              onRemove={() => removeWidget(selectedWidget.id)}
              onClose={() => setSelectedWidgetId(null)}
            />
          ) : null}
        </section>
      </main>

      {newDashboardOpen ? (
        <NewDashboardDialog
          onClose={() => setNewDashboardOpen(false)}
          onCreate={(name, blank) => void createDashboard(name, blank)}
        />
      ) : null}
      {addMenuOpen && dashboard ? (
        <AddWidgetMenu
          templates={templates}
          builderCatalog={builderCatalog}
          brands={brands}
          platforms={platforms}
          activeGroup={activeTemplateGroup}
          insertionTarget={insertionTarget}
          onClose={() => setAddMenuOpen(false)}
          onSelectGroup={setActiveTemplateGroup}
          onAdd={addTemplate}
          onAddPersonalized={addPersonalizedWidget}
        />
      ) : null}
    </div>
  )
}

function AddWidgetMenu({
  templates,
  builderCatalog,
  brands,
  platforms,
  activeGroup,
  insertionTarget,
  onClose,
  onSelectGroup,
  onAdd,
  onAddPersonalized,
}: {
  templates: WidgetTemplate[]
  builderCatalog: WidgetBuilderCatalog | null
  brands: Brand[]
  platforms: Platform[]
  activeGroup: TemplateGroup
  insertionTarget: InsertionTarget
  onClose: () => void
  onSelectGroup: (group: TemplateGroup) => void
  onAdd: (template: WidgetTemplate) => void
  onAddPersonalized: (draft: PersonalizedWidgetDraft) => void
}) {
  const [view, setView] = useState<'group-list' | 'group-detail'>(
    insertionTarget.widgetId === null ? 'group-list' : 'group-detail',
  )
  const [search, setSearch] = useState('')
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null)
  const bodyRef = useRef<HTMLDivElement>(null)
  const filtered = templates.filter((template) =>
    `${template.name} ${template.description ?? ''} ${template.metric?.name ?? ''}`
      .toLowerCase()
      .includes(search.toLowerCase()),
  )
  const visibleTemplates = filtered.filter((template) => matchesTemplateGroup(template, activeGroup))
  const groupMeta = templateGroups.find((group) => group.id === activeGroup) ?? templateGroups[0]
  const featuredTemplates = visibleTemplates.slice(0, 3)
  const remainingTemplates = visibleTemplates.slice(3)
  const selectedTemplate = visibleTemplates.find((template) => template.id === selectedTemplateId) ?? visibleTemplates[0] ?? null

  useEffect(() => {
    if (activeGroup === 'personalized') return
    if (visibleTemplates.length === 0) {
      setSelectedTemplateId(null)
      return
    }
    if (!visibleTemplates.some((template) => template.id === selectedTemplateId)) {
      setSelectedTemplateId(visibleTemplates[0].id)
    }
  }, [activeGroup, selectedTemplateId, visibleTemplates])

  useEffect(() => {
    bodyRef.current?.scrollTo({ top: 0, behavior: 'auto' })
  }, [activeGroup, view])

  return (
    <div className="dialog-backdrop" role="presentation" onMouseDown={onClose}>
      <aside className="add-widget-panel" onMouseDown={(event) => event.stopPropagation()}>
        <div className="panel-heading">
          <div>
            <strong>{view === 'group-list' ? 'Anadir bloque' : groupMeta.label}</strong>
            <span>{view === 'group-list' ? describeInsertionTarget(insertionTarget) : groupMeta.description}</span>
          </div>
          <div className="panel-heading-actions">
            {view === 'group-detail' ? (
              <button
                className="icon-button"
                onClick={() => setView('group-list')}
                aria-label="Volver a tipos de widgets"
              >
                <ChevronLeft size={15} />
              </button>
            ) : null}
            <button className="icon-button" onClick={onClose} aria-label="Cerrar inserter">
              <X size={15} />
            </button>
          </div>
        </div>

        {view === 'group-list' ? (
          <div className="template-group-stage">
            <div className="template-group-intro">
              <span>Widget builder</span>
              <strong>Empieza por el tipo de bloque</strong>
              <p>
                Elige una familia y luego afinamos el widget. La idea es mantener el lienzo consistente
                sin perder flexibilidad cuando quieras bajar al detalle.
              </p>
            </div>
            <div className="widget-group-grid" role="tablist" aria-label="Tipos de widgets">
              {templateGroups.map((group) => {
                const Icon = templateGroupIcons[group.id]
                const groupCount = group.id === 'personalized'
                  ? builderCatalog?.sources.length ?? 0
                  : templates.filter((template) => matchesTemplateGroup(template, group.id)).length

                return (
                  <button
                    key={group.id}
                    className={`widget-group-card ${group.id === activeGroup ? 'active' : ''}`}
                    onClick={() => {
                      onSelectGroup(group.id)
                      setView('group-detail')
                    }}
                  >
                    <div className="widget-group-card-top">
                      <span className="widget-group-icon"><Icon size={16} /></span>
                      <small>{groupCount} opciones</small>
                    </div>
                    <strong>{group.label}</strong>
                    <span>{group.description}</span>
                  </button>
                )
              })}
            </div>
          </div>
        ) : (
          <div className="template-group-body" ref={bodyRef}>
            {activeGroup === 'personalized' ? (
              <PersonalizedWidgetBuilder
                catalog={builderCatalog}
                brands={brands}
                platforms={platforms}
                onAdd={onAddPersonalized}
              />
            ) : (
              <div className="template-group-layout">
                <div className="template-group-main">
                  <div className="template-group-hero">
                    <div>
                      <span>{groupMeta.label}</span>
                      <strong>{groupMeta.description}</strong>
                    </div>
                    <div className="builder-search-row">
                      <label className="search-field compact subtle">
                        <Search size={14} />
                        <input
                          value={search}
                          onChange={(event) => setSearch(event.target.value)}
                          placeholder={`Buscar en ${groupMeta.label}`}
                        />
                      </label>
                      {search !== '' ? (
                        <button className="secondary-button small" onClick={() => setSearch('')}>
                          Limpiar
                        </button>
                      ) : null}
                    </div>
                  </div>

                    <div className="builder-chip-list">
                      <BuilderChip label={`${visibleTemplates.length} bloques`} muted={visibleTemplates.length === 0} />
                      {search !== '' ? <BuilderChip label={`Busqueda "${search}"`} /> : null}
                      <BuilderChip label="Selecciona uno y confirma a la derecha" muted />
                    </div>

                  {featuredTemplates.length > 0 ? (
                    <div className="template-preview-grid">
                      {featuredTemplates.map((template) => {
                        const Icon = widgetIcons[template.widget_type]
                        const previewWidget = buildTemplatePreviewWidget(template)
                        const previewResult = buildTemplatePreviewResult(template)
                        return (
                          <div
                            className={`template-preview-card ${selectedTemplate?.id === template.id ? 'selected' : ''}`}
                            key={template.id}
                            onClick={() => setSelectedTemplateId(template.id)}
                            onKeyDown={(event) => {
                              if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault()
                                setSelectedTemplateId(template.id)
                              }
                            }}
                            role="button"
                            tabIndex={0}
                          >
                            <div className="template-preview-top">
                              <span className="template-icon"><Icon size={15} /></span>
                              <div className="template-preview-badges">
                                <small>Destacado</small>
                                <small>{template.metric?.code ?? template.widget_type}</small>
                              </div>
                            </div>
                            <strong>{template.name}</strong>
                            <span>{template.description ?? template.metric?.name ?? 'Bloque listo para anadir al dashboard.'}</span>
                            <div className="template-preview-frame">
                              <article className="widget-card preview compact">
                                <header>
                                  <div>
                                    <strong>{previewWidget.title}</strong>
                                    <span>{template.metric?.name ?? template.widget_type}</span>
                                  </div>
                                </header>
                                <div className="widget-content">
                                  <WidgetPreview widget={previewWidget} result={previewResult} loading={false} />
                                </div>
                              </article>
                            </div>
                            <div className="template-preview-footer">
                              <small>{labelForVisualization(template.default_visualization_type)}</small>
                              <small>{formatWidgetFootprint(template.default_width, template.default_height)}</small>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  ) : null}

                  <div className="template-groups">
                    {visibleTemplates.length > 0 ? (
                      <>
                        {remainingTemplates.length > 0 ? (
                          <>
                            <h3>Todos los bloques</h3>
                            {remainingTemplates.map((template) => {
                              const Icon = widgetIcons[template.widget_type]
                              return (
                                <div
                                  className={`template-row ${selectedTemplate?.id === template.id ? 'selected' : ''}`}
                                  key={template.id}
                                  onClick={() => setSelectedTemplateId(template.id)}
                                  onKeyDown={(event) => {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                      event.preventDefault()
                                      setSelectedTemplateId(template.id)
                                    }
                                  }}
                                  role="button"
                                  tabIndex={0}
                                >
                                  <span className="template-icon"><Icon size={15} /></span>
                                  <span>
                                    <strong>{template.name}</strong>
                                    <small>{template.metric?.name ?? template.description ?? 'Contenido editorial'}</small>
                                  </span>
                                  <small>{labelForVisualization(template.default_visualization_type)}</small>
                                </div>
                              )
                            })}
                          </>
                        ) : null}
                      </>
                    ) : (
                      <div className="template-empty-state">
                        <strong>No hay bloques para esta categoria</strong>
                        <span>Prueba otra categoria o limpia la búsqueda para volver a ver todos.</span>
                        {search !== '' ? (
                          <button className="secondary-button small" onClick={() => setSearch('')}>
                            Limpiar búsqueda
                          </button>
                        ) : null}
                      </div>
                    )}
                  </div>
                </div>

                <aside className="template-group-aside">
                  {selectedTemplate ? (
                    <>
                      <div className="builder-side-card strong">
                        <span>Bloque</span>
                        <strong>{selectedTemplate.name}</strong>
                        <p>{selectedTemplate.description ?? selectedTemplate.metric?.name ?? 'Plantilla lista para usar.'}</p>
                      </div>
                      <div className="builder-side-card">
                        <span>Resumen</span>
                        <div className="builder-meta-list">
                          <div className="builder-meta-row">
                            <small>Visualización</small>
                            <strong>{labelForVisualization(selectedTemplate.default_visualization_type)}</strong>
                          </div>
                          <div className="builder-meta-row">
                            <small>Tamaño</small>
                            <strong>{formatWidgetFootprint(selectedTemplate.default_width, selectedTemplate.default_height)}</strong>
                          </div>
                          <div className="builder-meta-row">
                            <small>Métrica</small>
                            <strong>{selectedTemplate.metric?.code ?? selectedTemplate.widget_type}</strong>
                          </div>
                        </div>
                      </div>
                      <div className="builder-side-card muted">
                        <span>Inserción</span>
                        <strong>{describeInsertionTarget(insertionTarget)}</strong>
                        <p>El bloque nuevo se creará justo donde estás trabajando, para mantener el dashboard legible.</p>
                      </div>
                      <div className="builder-action-stack">
                        <button className="secondary-button small" onClick={() => setView('group-list')}>
                          Cambiar categoría
                        </button>
                        <button className="primary-button" onClick={() => onAdd(selectedTemplate)}>
                          <Plus size={15} />
                          Añadir este bloque
                        </button>
                      </div>
                    </>
                  ) : (
                    <div className="builder-side-card muted">
                      <span>Consejo</span>
                      <strong>Empieza con un bloque ya armado</strong>
                      <p>Usa las plantillas para velocidad y deja el builder personalizado para preguntas más específicas.</p>
                    </div>
                  )}
                </aside>
              </div>
            )}
          </div>
        )}
      </aside>
    </div>
  )
}

function PersonalizedWidgetBuilder({
  catalog,
  brands,
  platforms,
  onAdd,
}: {
  catalog: WidgetBuilderCatalog | null
  brands: Brand[]
  platforms: Platform[]
  onAdd: (draft: PersonalizedWidgetDraft) => void
}) {
  const sources = useMemo(() => catalog?.sources ?? [], [catalog])
  const flowRef = useRef<HTMLDivElement>(null)
  const [step, setStep] = useState<'source' | 'field' | 'metric' | 'config'>('source')
  const [sourceSearch, setSourceSearch] = useState('')
  const [sourceCode, setSourceCode] = useState<WidgetBuilderSource['code']>('posts')
  const [fieldCode, setFieldCode] = useState('')
  const [metricCode, setMetricCode] = useState('')
  const [fieldSearch, setFieldSearch] = useState('')
  const [metricSearch, setMetricSearch] = useState('')
  const [title, setTitle] = useState('')
  const [visualization, setVisualization] = useState<VisualizationType>('kpi')
  const [visualizationMode, setVisualizationMode] = useState<'guided' | 'advanced'>('guided')
  const [dateRange, setDateRange] = useState<'7d' | '30d' | '90d'>('30d')
  const [interval, setInterval] = useState<'day' | 'week' | 'month'>('day')
  const [limit, setLimit] = useState(20)
  const [brandIds, setBrandIds] = useState<string[]>([])
  const [platformIds, setPlatformIds] = useState<string[]>([])
  const [brandType, setBrandType] = useState('')
  const [relevance, setRelevance] = useState<'all' | 'true' | 'false'>('all')
  const [searchValue, setSearchValue] = useState('')

  const source = sources.find((item) => item.code === sourceCode) ?? sources[0] ?? null
  const selectedField = source?.fields.find((field) => field.code === fieldCode) ?? source?.fields[0] ?? null
  const metric = source?.metrics.find((item) => item.code === metricCode) ?? source?.metrics[0] ?? null

  useEffect(() => {
    if (sources.length > 0 && !sources.some((item) => item.code === sourceCode)) {
      setSourceCode(sources[0].code)
    }
  }, [sourceCode, sources])

  useEffect(() => {
    if (!source) return
    if (!source.fields.some((field) => field.code === fieldCode)) {
      setFieldCode(source.fields[0]?.code ?? '')
    }
  }, [fieldCode, source])

  useEffect(() => {
    if (!source) return
    if (!source.metrics.some((item) => item.code === metricCode)) {
      setMetricCode(source.metrics[0]?.code ?? '')
    }
  }, [metricCode, source])

  useEffect(() => {
    if (!metric) return
    setTitle(metric.name)
    const recommendedVisualizations = metric.supported_visualizations
    const compatibleVisualizations = compatibleVisualizationsForMetric(metric)
    const availableVisualizations = visualizationMode === 'guided'
      ? recommendedVisualizations
      : compatibleVisualizations
    if (!availableVisualizations.includes(visualization)) {
      setVisualization(
        availableVisualizations[0]
        ?? recommendedVisualizations[0]
        ?? metric.default_visualization_type,
      )
    }
    const allowedIntervals = asStringArray(metric.config_schema.allowed_intervals)
    if (allowedIntervals.length > 0 && !allowedIntervals.includes(interval)) {
      setInterval((allowedIntervals[0] as 'day' | 'week' | 'month') ?? 'day')
    }
  }, [interval, metric, visualization, visualizationMode])

  useEffect(() => {
    flowRef.current?.scrollTo({ top: 0, behavior: 'auto' })
  }, [step])

  if (catalog === null) {
    return (
      <div className="template-empty-state">
        <LoaderCircle className="spin" size={18} />
        <strong>Cargando builder personalizado</strong>
        <span>Estamos preparando las tablas, métricas y opciones de visualización.</span>
      </div>
    )
  }

  if (source === null || metric === null) {
    return (
      <div className="template-empty-state">
        <strong>No hay catálogo disponible</strong>
        <span>Aún no hay métricas activas para construir widgets personalizados.</span>
      </div>
    )
  }

  const allowedIntervals = asStringArray(metric.config_schema.allowed_intervals)
    .filter((value): value is 'day' | 'week' | 'month' => value === 'day' || value === 'week' || value === 'month')
  const prioritizedMetrics = prioritizeMetricsForField(source, fieldCode)
  const suggestedMetrics = prioritizedMetrics.filter((item) => isMetricSuggestedForField(item.code, fieldCode))
  const alternativeMetrics = prioritizedMetrics.filter((item) => !isMetricSuggestedForField(item.code, fieldCode))
  const visibleSources = sources.filter((item) =>
    `${item.label} ${item.table_name} ${item.description ?? ''}`.toLowerCase().includes(sourceSearch.toLowerCase()),
  )
  const visibleFields = source.fields.filter((field) =>
    `${field.label} ${field.code} ${field.description ?? ''}`.toLowerCase().includes(fieldSearch.toLowerCase()),
  )
  const visibleMetrics = prioritizedMetrics.filter((item) =>
    `${item.name} ${item.code} ${item.description ?? ''}`.toLowerCase().includes(metricSearch.toLowerCase()),
  )
  const visibleSuggestedMetrics = suggestedMetrics.filter((item) =>
    `${item.name} ${item.code} ${item.description ?? ''}`.toLowerCase().includes(metricSearch.toLowerCase()),
  )
  const visibleAlternativeMetrics = alternativeMetrics.filter((item) =>
    `${item.name} ${item.code} ${item.description ?? ''}`.toLowerCase().includes(metricSearch.toLowerCase()),
  )
  const recommendedVisualizationCodes = metric.supported_visualizations
  const compatibleVisualizationCodes = compatibleVisualizationsForMetric(metric)
  const hasAdvancedVisualizationOptions = compatibleVisualizationCodes.some((code) => !recommendedVisualizationCodes.includes(code))
  const availableVisualizationCodes = visualizationMode === 'guided'
    ? recommendedVisualizationCodes
    : compatibleVisualizationCodes
  const visualizations = (catalog.visualizations ?? [])
    .filter((item) => availableVisualizationCodes.includes(item.code))
  const supportsFilter = (field: DashboardFilterField) => metric.recommended_filters.includes(field)
  const supportsComparison = metric.config_schema.supports_comparison === true
  const hasAdvancedFilters = supportsFilter('brand_ids')
    || supportsFilter('platform_ids')
    || supportsFilter('brand_type')
    || supportsFilter('relevance')
    || supportsFilter('search')
  const hasActiveAdvancedFilters = brandIds.length > 0
    || platformIds.length > 0
    || brandType !== ''
    || relevance !== 'all'
    || searchValue.trim() !== ''
  const previewWidget = buildBuilderPreviewWidget({
    metric,
    visualization,
    title: title.trim() || metric.name,
  })
  const previewResult = buildBuilderPreviewResult(metric, visualization, source.label, selectedField?.label)
  const configNarrative = buildBuilderNarrative({
    sourceLabel: source.label,
    fieldLabel: selectedField?.label ?? 'la dimensión elegida',
    metricName: metric.name,
    resultKind: metric.result_kind ?? 'scalar',
    visualization,
    dateRange,
    interval,
    limit,
  })

  function resetPersonalizedBuilder() {
    setSourceCode(sources[0]?.code ?? 'posts')
    setFieldCode(sources[0]?.fields[0]?.code ?? '')
    setMetricCode(sources[0]?.metrics[0]?.code ?? '')
    setTitle(sources[0]?.metrics[0]?.name ?? '')
    setVisualization('kpi')
    setVisualizationMode('guided')
    setDateRange('30d')
    setInterval('day')
    setLimit(20)
    setBrandIds([])
    setPlatformIds([])
    setBrandType('')
    setRelevance('all')
    setSearchValue('')
    setStep('source')
  }

  return (
    <div className={`personalized-shell ${step === 'config' ? 'configuring' : 'setup'}`}>
      <div className="personalized-flow" ref={flowRef}>
        <div className="personalized-flow-header">
          <button className={`personalized-step ${step === 'source' ? 'active' : 'done'}`} onClick={() => setStep('source')}>
            <BuilderStepLabel index="1." label="Tabla" value={source.label} />
          </button>
          <button
            className={`personalized-step ${step === 'field' ? 'active' : step === 'metric' || step === 'config' ? 'done' : ''}`}
            onClick={() => step !== 'source' ? setStep('field') : undefined}
            disabled={step === 'source'}
          >
            <BuilderStepLabel index="2." label="Columna" value={selectedField?.label ?? 'Por definir'} muted={step === 'source'} />
          </button>
          <button
            className={`personalized-step ${step === 'metric' ? 'active' : step === 'config' ? 'done' : ''}`}
            onClick={() => step === 'config' ? setStep('metric') : undefined}
            disabled={step !== 'config'}
          >
            <BuilderStepLabel index="3." label="Métrica" value={metric.name} muted={step === 'source' || step === 'field'} />
          </button>
          <button className={`personalized-step ${step === 'config' ? 'active' : ''}`} disabled>
            <BuilderStepLabel index="4." label="Configuración" value={labelForVisualization(visualization)} subtleValue />
          </button>
        </div>

        {step === 'source' ? (
          <section className="personalized-screen">
            <div className="personalized-screen-heading">
              <div>
                <strong>Elige una tabla</strong>
                <span>Empieza por el origen de los datos que quieres transformar en widget.</span>
              </div>
              <BuilderCountBadge count={visibleSources.length} label="tablas" />
            </div>
            <div className="builder-search-row">
              <label className="search-field compact subtle">
                <Search size={14} />
                <input
                  value={sourceSearch}
                  onChange={(event) => setSourceSearch(event.target.value)}
                  placeholder="Buscar tabla"
                />
              </label>
              {sourceSearch !== '' ? (
                <button className="secondary-button small" onClick={() => setSourceSearch('')}>
                  Limpiar
                </button>
              ) : null}
            </div>
            <div className="widget-group-grid sources">
              {visibleSources.length > 0 ? visibleSources.map((item) => (
                <button
                  key={item.code}
                  className={item.code === source.code ? 'active' : ''}
                  onClick={() => {
                    setSourceCode(item.code)
                    setFieldCode(item.fields[0]?.code ?? '')
                    setFieldSearch('')
                    setMetricSearch('')
                    setStep('field')
                  }}
                >
                  <div className="personalized-choice-card-meta">
                    <small>{item.table_name}</small>
                    {item.code === source.code ? <small>Seleccionada</small> : <small>{item.fields.length} columnas</small>}
                  </div>
                  <strong>{item.label}</strong>
                  <span>{item.description}</span>
                  <div className="personalized-card-footnote">
                    <small>{item.metrics.length} métricas disponibles</small>
                  </div>
                </button>
              )) : (
                <div className="template-empty-state compact">
                  <strong>No encontramos esa tabla</strong>
                  <span>Prueba otro término o borra la búsqueda para ver todas.</span>
                </div>
              )}
            </div>
          </section>
        ) : null}

        {step === 'field' ? (
          <section className="personalized-screen">
            <div className="personalized-screen-heading">
              <div>
                <strong>{source.label}</strong>
                <span>Elige la dimensión principal que guiará la lectura del bloque.</span>
              </div>
              <div className="builder-heading-actions">
                <BuilderCountBadge count={visibleFields.length} label="columnas" />
                <button className="icon-button" onClick={() => setStep('source')} aria-label="Volver a tablas">
                  <ChevronLeft size={15} />
                </button>
              </div>
            </div>
            <div className="builder-search-row">
              <label className="search-field compact subtle">
                <Search size={14} />
                <input
                  value={fieldSearch}
                  onChange={(event) => setFieldSearch(event.target.value)}
                  placeholder="Buscar columna"
                />
              </label>
              {fieldSearch !== '' ? (
                <button className="secondary-button small" onClick={() => setFieldSearch('')}>
                  Limpiar
                </button>
              ) : null}
            </div>
            <div className="personalized-choice-grid">
              {visibleFields.length > 0 ? visibleFields.map((field) => (
                <button
                  key={field.code}
                  className={`personalized-choice-card ${field.code === selectedField?.code ? 'active' : ''}`}
                  onClick={() => {
                    setFieldCode(field.code)
                    setStep('metric')
                  }}
                >
                  <div className="personalized-choice-card-meta">
                    <small>{field.code}</small>
                    {field.code === selectedField?.code ? <small>En foco</small> : <small>Dimensión</small>}
                  </div>
                  <strong>{field.label}</strong>
                  <span>{field.description}</span>
                </button>
              )) : (
                <div className="template-empty-state compact">
                  <strong>No encontramos esa columna</strong>
                  <span>Prueba otro nombre o vuelve a la lista completa.</span>
                </div>
              )}
            </div>
          </section>
        ) : null}

        {step === 'metric' ? (
          <section className="personalized-screen">
            <div className="personalized-screen-heading">
              <div>
                <strong>{selectedField?.label ?? 'Métrica'}</strong>
                <span>Escoge qué quieres medir y prioriza las métricas sugeridas para este foco.</span>
              </div>
              <div className="builder-heading-actions">
                <BuilderCountBadge count={visibleMetrics.length} label="métricas" />
                <button className="icon-button" onClick={() => setStep('field')} aria-label="Volver a columnas">
                  <ChevronLeft size={15} />
                </button>
              </div>
            </div>
            {selectedField ? (
              <div className="personalized-focus-card">
                <span>Columna seleccionada</span>
                <strong>{selectedField.label}</strong>
                <small>{selectedField.description}</small>
              </div>
            ) : null}
            <div className="builder-chip-list metric-step-chips">
              <BuilderChip label={`${visibleSuggestedMetrics.length} sugeridas`} muted={visibleSuggestedMetrics.length === 0} />
              <BuilderChip label={`${visibleAlternativeMetrics.length} alternativas`} muted={visibleAlternativeMetrics.length === 0} />
              <BuilderChip label="Al elegir una métrica pasas a configuración" muted />
            </div>
            <div className="builder-search-row">
              <label className="search-field compact subtle">
                <Search size={14} />
                <input
                  value={metricSearch}
                  onChange={(event) => setMetricSearch(event.target.value)}
                  placeholder="Buscar métrica"
                />
              </label>
              {metricSearch !== '' ? (
                <button className="secondary-button small" onClick={() => setMetricSearch('')}>
                  Limpiar
                </button>
              ) : null}
            </div>
            <div className="personalized-metric-list standalone">
              <div className="personalized-subheading">
                <strong>Métricas sugeridas</strong>
                <span>Priorizadas según la columna elegida, pero sin bloquear otras opciones.</span>
              </div>
              {visibleMetrics.length > 0 ? (
                <>
                  {visibleSuggestedMetrics.length > 0 ? (
                    <div className="personalized-metric-group recommended">
                      <div className="personalized-group-heading">
                        <div className="personalized-group-label">Recomendadas</div>
                        <small>Las más naturales para {selectedField?.label?.toLowerCase() ?? 'este foco'}</small>
                      </div>
                      <div className="metric-choice-grid">
                      {visibleSuggestedMetrics.map((item) => (
                        <MetricChoiceCard
                          key={item.code}
                          metric={item}
                          selected={item.code === metric.code}
                          kindLabel="Sugerida"
                          onSelect={() => {
                            setMetricCode(item.code)
                            setStep('config')
                          }}
                        />
                      ))}
                      </div>
                    </div>
                  ) : (
                    <div className="template-empty-state compact">
                      <strong>No hay sugerencias directas para esta columna</strong>
                      <span>Prueba una de las métricas generales que verás justo debajo.</span>
                    </div>
                  )}

                  {visibleAlternativeMetrics.length > 0 ? (
                    <details className="personalized-metric-more" open={metricSearch !== ''}>
                      <summary>
                        <span>Más opciones</span>
                        <small>{visibleAlternativeMetrics.length} métricas adicionales</small>
                        <ChevronDown size={14} />
                      </summary>
                      <div className="personalized-metric-group alternative">
                        {visibleAlternativeMetrics.map((item) => (
                          <MetricChoiceRow
                            key={item.code}
                            metric={item}
                            selected={item.code === metric.code}
                            onSelect={() => {
                              setMetricCode(item.code)
                              setStep('config')
                            }}
                          />
                        ))}
                      </div>
                    </details>
                  ) : null}
                </>
              ) : (
                <div className="template-empty-state compact">
                  <strong>No encontramos esa métrica</strong>
                  <span>Prueba otro término o borra la búsqueda para ver todas.</span>
                </div>
              )}
            </div>
          </section>
        ) : null}

        {step === 'config' ? (
          <section className="personalized-screen">
            <div className="personalized-screen-heading">
              <div>
                <strong>{metric.name}</strong>
                <span>Ajusta visualización, filtros y título antes de crear el widget.</span>
              </div>
              <button className="icon-button" onClick={() => setStep('metric')} aria-label="Volver a metricas">
                <ChevronLeft size={15} />
              </button>
            </div>
            <div className="personalized-config-card">
              <div className="personalized-config-summary">
                <span>{source.label}</span>
                <strong>{metric.name}</strong>
                {selectedField ? <small>Foco: {selectedField.label}</small> : null}
              </div>
              <div className="builder-chip-list">
                <BuilderChip label={labelForVisualization(visualization)} />
                <BuilderChip label={dateRange === '7d' ? '7 días' : dateRange === '90d' ? '90 días' : '30 días'} />
                {allowedIntervals.length > 0 && (visualization === 'line' || visualization === 'bar') ? (
                  <BuilderChip label={interval === 'day' ? 'Por día' : interval === 'week' ? 'Por semana' : 'Por mes'} />
                ) : null}
                {(visualization === 'table' || visualization === 'mentions_feed') && metric.value_type === 'list' ? (
                  <BuilderChip label={`${limit} filas`} />
                ) : null}
              </div>
              <div className="personalized-config-note">
                {configNarrative}
              </div>
              <div className="personalized-config-section">
                <div className="personalized-subheading">
                  <strong>Presentación</strong>
                  <span>Define el título y cómo se verá el bloque en el dashboard.</span>
                </div>
                <label>
                  Título
                  <input value={title} onChange={(event) => setTitle(event.target.value)} />
                </label>

                {hasAdvancedVisualizationOptions ? (
                  <div className="visualization-mode-row">
                    <div className="visualization-mode-copy">
                      <strong>Modo de visualización</strong>
                      <span>
                        {visualizationMode === 'guided'
                          ? 'Solo verás las vistas recomendadas para esta métrica.'
                          : 'Puedes forzar vistas compatibles aunque no sean la recomendación principal.'}
                      </span>
                    </div>
                    <div className="visualization-mode-toggle" role="tablist" aria-label="Modo de visualización">
                      <button
                        className={visualizationMode === 'guided' ? 'active' : ''}
                        onClick={() => setVisualizationMode('guided')}
                        type="button"
                      >
                        Guiado
                      </button>
                      <button
                        className={visualizationMode === 'advanced' ? 'active' : ''}
                        onClick={() => setVisualizationMode('advanced')}
                        type="button"
                      >
                        Avanzado
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="visualization-mode-note">
                    Esta métrica ya muestra todas las visualizaciones compatibles.
                  </div>
                )}

                <div className="personalized-visualizations">
                  {visualizations.map((option) => {
                    const Icon = widgetIcons[option.code === 'table' ? 'table' : option.code === 'mentions_feed' ? 'mentions_feed' : option.code as WidgetType]
                    const isRecommended = recommendedVisualizationCodes.includes(option.code)
                    return (
                      <button
                        key={option.code}
                        className={option.code === visualization ? 'active' : ''}
                        onClick={() => setVisualization(option.code)}
                        type="button"
                        title={option.description}
                      >
                        <Icon size={15} />
                        <span>{option.label}</span>
                        {visualizationMode === 'advanced' && !isRecommended ? (
                          <small>Compatible</small>
                        ) : null}
                      </button>
                    )
                  })}
                </div>
                <div className="personalized-inline-actions">
                  <button
                    className="secondary-button small"
                    onClick={() => {
                      setTitle(metric.name)
                      setVisualizationMode('guided')
                      setVisualization(metric.supported_visualizations[0] ?? metric.default_visualization_type)
                    }}
                  >
                    Reset presentación
                  </button>
                </div>
              </div>

              <div className="personalized-config-section">
                <div className="personalized-subheading">
                  <strong>Ajustes básicos</strong>
                  <span>Lo mínimo para que el bloque quede bien configurado.</span>
                </div>
                <div className="personalized-filter-grid">
                  {supportsFilter('date_range') ? (
                    <label>
                      Periodo
                      <select value={dateRange} onChange={(event) => setDateRange(event.target.value as '7d' | '30d' | '90d')}>
                        <option value="7d">Últimos 7 días</option>
                        <option value="30d">Últimos 30 días</option>
                        <option value="90d">Últimos 90 días</option>
                      </select>
                    </label>
                  ) : null}

                  {allowedIntervals.length > 0 && (visualization === 'line' || visualization === 'bar') ? (
                    <label>
                      Intervalo
                      <select value={interval} onChange={(event) => setInterval(event.target.value as 'day' | 'week' | 'month')}>
                        {allowedIntervals.map((option) => (
                          <option key={option} value={option}>{option === 'day' ? 'Día' : option === 'week' ? 'Semana' : 'Mes'}</option>
                        ))}
                      </select>
                    </label>
                  ) : null}

                  {(visualization === 'table' || visualization === 'mentions_feed') && metric.value_type === 'list' ? (
                    <label>
                      Límite de filas
                      <input
                        type="number"
                        min={5}
                        max={100}
                        step={5}
                        value={limit}
                        onChange={(event) => setLimit(Math.max(5, Math.min(100, Number(event.target.value) || 20)))}
                      />
                    </label>
                  ) : null}
                </div>
                <div className="personalized-inline-actions">
                  <button
                    className="secondary-button small"
                    onClick={() => {
                      setDateRange('30d')
                      setInterval('day')
                      setLimit(20)
                    }}
                  >
                    Reset básicos
                  </button>
                </div>
              </div>

              {hasAdvancedFilters ? (
                <details className="personalized-advanced-filters" open={hasActiveAdvancedFilters}>
                  <summary>
                    <div className="personalized-advanced-summary">
                      <span>Segmentación avanzada</span>
                      <small>
                        {hasActiveAdvancedFilters
                          ? summarizeAdvancedFilters({
                            brandIds,
                            platformIds,
                            brandType,
                            relevance,
                            searchValue,
                          })
                          : 'Afina marcas, plataformas, relevancia o búsqueda.'}
                      </small>
                    </div>
                    <ChevronDown size={14} />
                  </summary>
                  <div className="personalized-filter-grid advanced">
                    {supportsFilter('brand_ids') ? (
                      <MultiSelectFilter
                        label="Marcas"
                        options={brands.map((brand) => ({ id: brand.id, name: brand.name }))}
                        selected={brandIds}
                        onChange={setBrandIds}
                      />
                    ) : null}
                    {supportsFilter('platform_ids') ? (
                      <MultiSelectFilter
                        label="Plataformas"
                        options={platforms.map((platform) => ({ id: platform.id, name: platform.name }))}
                        selected={platformIds}
                        onChange={setPlatformIds}
                      />
                    ) : null}
                    {supportsFilter('brand_type') ? (
                      <label>
                        Tipo de marca
                        <select value={brandType} onChange={(event) => setBrandType(event.target.value)}>
                          <option value="">Todas</option>
                          <option value="own_brand">Marca propia</option>
                          <option value="own_subbrand">Submarca propia</option>
                          <option value="competitor">Competidor</option>
                          <option value="competitor_subbrand">Submarca competidora</option>
                        </select>
                      </label>
                    ) : null}
                    {supportsFilter('relevance') ? (
                      <label>
                        Relevancia
                        <select value={relevance} onChange={(event) => setRelevance(event.target.value as 'all' | 'true' | 'false')}>
                          <option value="all">Todas</option>
                          <option value="true">Solo relevantes</option>
                          <option value="false">Solo no relevantes</option>
                        </select>
                      </label>
                    ) : null}
                    {supportsFilter('search') ? (
                      <label>
                        Búsqueda
                        <input
                          value={searchValue}
                          onChange={(event) => setSearchValue(event.target.value)}
                          placeholder="Texto, marca o contexto"
                        />
                      </label>
                    ) : null}
                  </div>
                  <div className="personalized-advanced-actions">
                    <button
                      className="secondary-button small"
                      onClick={() => {
                        setBrandIds([])
                        setPlatformIds([])
                        setBrandType('')
                        setRelevance('all')
                        setSearchValue('')
                      }}
                    >
                      Limpiar segmentación
                    </button>
                  </div>
                </details>
              ) : null}

              <div className="personalized-builder-footer">
                <button className="secondary-button" onClick={() => setStep('metric')}>
                  <ChevronLeft size={15} />
                  Volver
                </button>
                <button
                  className="primary-button"
                  onClick={() => onAdd({
                    metric,
                    visualization,
                    title: title.trim() || metric.name,
                    focusFieldCode: selectedField?.code,
                    config: buildPersonalizedConfig(metric, {
                      dateRange,
                      interval,
                      limit,
                      brandIds,
                      platformIds,
                      brandType,
                      relevance,
                      search: searchValue,
                      focusFieldCode: selectedField?.code,
                      supportsComparison,
                    }),
                  })}
                >
                  <Plus size={15} />
                  Crear widget
                </button>
              </div>
            </div>
          </section>
        ) : null}
      </div>

      <aside className={`personalized-sidebar ${step === 'config' ? 'full' : 'compact minimal'}`}>
        {step === 'config' ? (
          <div className="builder-side-card strong">
            <span>Resumen</span>
            <strong>{title.trim() || metric.name}</strong>
            <p>{`${source.label} · ${selectedField?.label ?? 'Sin columna'} · ${metric.name}`}</p>
          </div>
        ) : null}
        {step === 'config' ? (
          <div className="builder-preview-card">
            <div className="builder-preview-heading">
              <span>Preview</span>
              <strong>{labelForVisualization(visualization)}</strong>
            </div>
            <div className="builder-widget-preview">
              <article className="widget-card preview">
                <header>
                  <div>
                    <strong>{previewWidget.title}</strong>
                    <span>{metric.name}</span>
                  </div>
                </header>
                <div className="widget-content">
                  <WidgetPreview widget={previewWidget} result={previewResult} loading={false} />
                </div>
              </article>
            </div>
            <button className="secondary-button small" onClick={resetPersonalizedBuilder}>
              Empezar de nuevo
            </button>
          </div>
        ) : null}
        {step === 'config' ? (
          <div className="builder-selection-stack">
            <div className="builder-side-card">
              <span>Estructura</span>
              <div className="builder-selection-summary">
                <div className="builder-meta-row">
                  <small>Tabla</small>
                  <strong>{source.label}</strong>
                </div>
                <div className="builder-meta-row">
                  <small>Columna</small>
                  <strong>{selectedField?.label ?? 'Por definir'}</strong>
                </div>
                <div className="builder-meta-row">
                  <small>Métrica</small>
                  <strong>{metric.name}</strong>
                </div>
                <div className="builder-meta-row">
                  <small>Vista</small>
                  <strong>{labelForVisualization(visualization)}</strong>
                </div>
              </div>
            </div>
          </div>
        ) : null}
        <div className="builder-side-card muted">
          <span>{step === 'config' ? 'Filtros activos' : 'Base del widget'}</span>
          <div className="builder-chip-list">
            <BuilderChip label={dateRange === '7d' ? '7 días' : dateRange === '90d' ? '90 días' : '30 días'} />
            {allowedIntervals.length > 0 && (visualization === 'line' || visualization === 'bar') ? <BuilderChip label={interval === 'day' ? 'Por día' : interval === 'week' ? 'Por semana' : 'Por mes'} /> : null}
            {brandIds.length > 0 ? <BuilderChip label={`${brandIds.length} marcas`} /> : null}
            {platformIds.length > 0 ? <BuilderChip label={`${platformIds.length} plataformas`} /> : null}
            {brandType !== '' ? <BuilderChip label="Tipo de marca" /> : null}
            {relevance !== 'all' ? <BuilderChip label={relevance === 'true' ? 'Solo relevantes' : 'No relevantes'} /> : null}
            {searchValue.trim() !== '' ? <BuilderChip label={`"${searchValue.trim()}"`} /> : null}
            {brandIds.length === 0 && platformIds.length === 0 && brandType === '' && relevance === 'all' && searchValue.trim() === '' ? (
              <BuilderChip label="Sin filtros extra" muted />
            ) : null}
          </div>
        </div>
      </aside>
    </div>
  )
}

function DashboardCanvas({
  dashboard,
  widgets,
  metricResults,
  metricsLoading,
  selectedWidgetId,
  previewMode,
  insertionTarget,
  onSelect,
  onRequestInsert,
  onLayoutChange,
}: {
  dashboard: Dashboard
  widgets: DashboardWidget[]
  metricResults: Record<string, MetricQueryResult>
  metricsLoading: boolean
  selectedWidgetId: string | null
  previewMode: boolean
  insertionTarget: InsertionTarget
  onSelect: (id: string | null) => void
  onRequestInsert: (target: InsertionTarget, group?: TemplateGroup) => void
  onLayoutChange: (layout: Layout[]) => void
}) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(960)

  useEffect(() => {
    const element = containerRef.current
    if (!element) return
    const observer = new ResizeObserver(([entry]) => {
      setWidth(Math.max(320, entry.contentRect.width))
    })
    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  const layout = widgets.map((widget) => ({
    i: widget.id,
    x: widget.grid_x,
    y: widget.grid_y,
    w: widget.grid_width,
    h: widget.grid_height,
    minW: widget.min_width,
    minH: widget.min_height,
  }))

  return (
    <div
      className={`dashboard-canvas ${previewMode ? 'preview' : ''}`}
      ref={containerRef}
      onClick={(event) => {
        if (event.target === event.currentTarget) onSelect(null)
      }}
    >
      {widgets.length === 0 ? (
        <div className="canvas-empty">
          <LayoutDashboard size={24} />
          <strong>El lienzo esta vacio</strong>
          <span>Anade el primer bloque desde el inserter.</span>
          {!previewMode ? (
            <button
              className="primary-button"
              onClick={() => onRequestInsert({ kind: 'append', widgetId: null })}
            >
              <Plus size={15} />
              Anadir bloque
            </button>
          ) : null}
        </div>
      ) : (
        <>
          {!previewMode ? (
            <div className="canvas-add-bar">
              <button
                className={matchesInsertionTarget(insertionTarget, { kind: 'append', widgetId: null }) ? 'active' : ''}
                onClick={() => onRequestInsert({ kind: 'append', widgetId: null })}
              >
                <Plus size={14} />
                Anadir al final
              </button>
            </div>
          ) : null}
          <GridLayout
            className="layout"
            layout={layout}
            width={width}
            cols={dashboard.grid_columns}
            rowHeight={58}
            margin={[12, 12]}
            containerPadding={[16, 16]}
            isDraggable={!previewMode}
            isResizable={!previewMode}
            draggableHandle=".widget-drag-handle"
            onLayoutChange={onLayoutChange}
          >
            {widgets
              .filter((widget) => widget.is_visible || !previewMode)
              .map((widget) => (
                <div key={widget.id}>
                  <WidgetCard
                    widget={widget}
                    result={metricResults[widget.id]}
                    loading={metricsLoading}
                    selected={widget.id === selectedWidgetId}
                    previewMode={previewMode}
                    insertionTarget={insertionTarget}
                    onSelect={() => onSelect(widget.id)}
                    onRequestInsert={onRequestInsert}
                  />
                </div>
              ))}
          </GridLayout>
        </>
      )}
    </div>
  )
}

function WidgetCard({
  widget,
  result,
  loading,
  selected,
  previewMode,
  insertionTarget,
  onSelect,
  onRequestInsert,
}: {
  widget: DashboardWidget
  result?: MetricQueryResult
  loading: boolean
  selected: boolean
  previewMode: boolean
  insertionTarget: InsertionTarget
  onSelect: () => void
  onRequestInsert: (target: InsertionTarget, group?: TemplateGroup) => void
}) {
  const preferredGroup = groupForWidget(widget)

  return (
    <div className={`widget-card-shell ${selected ? 'selected' : ''}`}>
      <article
        className={`widget-card ${selected ? 'selected' : ''} ${
          !widget.is_visible ? 'hidden-widget' : ''
        }`}
        onClick={(event) => {
          event.stopPropagation()
          if (!previewMode) onSelect()
        }}
      >
        <header>
          {!previewMode ? (
            <button className="widget-drag-handle" aria-label="Mover widget" title="Mover widget">
              <GripVertical size={14} />
            </button>
          ) : null}
          <div>
            <strong>{widget.title}</strong>
            <span>{widget.metric?.name ?? widget.metric_code ?? 'Contenido'}</span>
          </div>
          {!previewMode ? <MoreHorizontal size={15} /> : null}
        </header>
        <div className="widget-content">
          <WidgetPreview widget={widget} result={result} loading={loading} />
        </div>
        {!widget.is_visible ? <span className="visibility-label">Oculto</span> : null}
      </article>
      {!previewMode ? (
        <div className="widget-insert-overlay">
          <button
            className={`widget-insert-button top ${
              matchesInsertionTarget(insertionTarget, { kind: 'above', widgetId: widget.id }) ? 'active' : ''
            }`}
            onClick={(event) => {
              event.stopPropagation()
              onRequestInsert({ kind: 'above', widgetId: widget.id }, preferredGroup)
            }}
          >
            <Plus size={14} />
          </button>
          <button
            className={`widget-insert-button left ${
              matchesInsertionTarget(insertionTarget, { kind: 'left', widgetId: widget.id }) ? 'active' : ''
            }`}
            onClick={(event) => {
              event.stopPropagation()
              onRequestInsert({ kind: 'left', widgetId: widget.id }, preferredGroup)
            }}
          >
            <Plus size={14} />
          </button>
          <button
            className={`widget-insert-button right ${
              matchesInsertionTarget(insertionTarget, { kind: 'right', widgetId: widget.id }) ? 'active' : ''
            }`}
            onClick={(event) => {
              event.stopPropagation()
              onRequestInsert({ kind: 'right', widgetId: widget.id }, preferredGroup)
            }}
          >
            <Plus size={14} />
          </button>
          <button
            className={`widget-insert-button bottom ${
              matchesInsertionTarget(insertionTarget, { kind: 'below', widgetId: widget.id }) ? 'active' : ''
            }`}
            onClick={(event) => {
              event.stopPropagation()
              onRequestInsert({ kind: 'below', widgetId: widget.id }, preferredGroup)
            }}
          >
            <Plus size={14} />
          </button>
        </div>
      ) : null}
    </div>
  )
}

function WidgetPreview({
  widget,
  result,
  loading,
}: {
  widget: DashboardWidget
  result?: MetricQueryResult
  loading: boolean
}) {
  const visual = widget.visualization_type

  if (widget.widget_type === 'text') {
    return (
      <p className="text-widget">
        {String(widget.config.content || 'Anade contexto, conclusiones o notas para tu cliente.')}
      </p>
    )
  }

  if (loading && result === undefined) {
    return <div className="coming-data"><LoaderCircle className="spin" size={18} /> Actualizando datos</div>
  }

  if (visual === 'kpi') {
    if (result?.kind !== 'scalar') return <EmptyMetric />

    const change = result.comparison?.change_percent
    return (
      <div className="kpi-preview">
        <strong>{formatScalarMetric(result)}</strong>
        {change === null || change === undefined ? (
          <span className="neutral">Sin periodo anterior comparable</span>
        ) : (
          <span className={change < 0 ? 'negative' : ''}>
            {change > 0 ? '+' : ''}{formatNumber(change)}% vs. periodo anterior
          </span>
        )}
      </div>
    )
  }

  if (visual === 'line') {
    if (result?.kind !== 'series' || result.points.length === 0) return <EmptyMetric />

    return (
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={result.points} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <defs>
            <linearGradient id={`area-${widget.id}`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#18181b" stopOpacity={0.16} />
              <stop offset="100%" stopColor="#18181b" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#eeeeef" />
          <XAxis dataKey="label" interval="preserveStartEnd" tickFormatter={formatPreviewAxisLabel} axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#71717a' }} />
          <Tooltip />
          <Area type="monotone" dataKey="value" stroke="#18181b" strokeWidth={2} fill={`url(#area-${widget.id})`} />
        </AreaChart>
      </ResponsiveContainer>
    )
  }

  if (visual === 'bar') {
    if (result?.kind === 'scalar') {
      return (
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={[{ label: 'Valor', value: result.value }]} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
            <CartesianGrid vertical={false} stroke="#eeeeef" />
            <XAxis dataKey="label" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#71717a' }} />
            <Tooltip />
            <Bar dataKey="value" fill="#27272a" radius={[3, 3, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      )
    }

    if (result?.kind !== 'series' || result.points.length === 0) return <EmptyMetric />

    return (
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={result.points} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#eeeeef" />
          <XAxis dataKey="label" interval={0} tickFormatter={formatPreviewAxisLabel} axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#71717a' }} />
          <Tooltip />
          <Bar dataKey="value" fill="#27272a" radius={[3, 3, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    )
  }

  if (visual === 'pie') {
    if (result?.kind === 'scalar' && result.value_type === 'percentage') {
      const currentValue = Math.max(0, Math.min(100, Number(result.value)))
      const points = [
        { key: 'current', label: 'Actual', value: currentValue, color: chartPalette[0] },
        { key: 'remaining', label: 'Restante', value: Math.max(0, 100 - currentValue), color: chartPalette[2] },
      ]

      return (
        <div className="pie-preview">
          <ResponsiveContainer width="55%" height="100%">
            <PieChart>
              <Pie data={points} dataKey="value" innerRadius="55%" outerRadius="82%" paddingAngle={2}>
                {points.map((entry) => <Cell key={entry.key} fill={entry.color} />)}
              </Pie>
            </PieChart>
          </ResponsiveContainer>
          <div>
            <span>
              <i style={{ background: points[0].color }} />Actual <b>{formatNumber(currentValue)}%</b>
            </span>
            <span>
              <i style={{ background: points[1].color }} />Restante <b>{formatNumber(points[1].value)}%</b>
            </span>
          </div>
        </div>
      )
    }

    if (result?.kind !== 'series' || result.points.length === 0) return <EmptyMetric />

    const total = result.points.reduce((sum, point) => sum + point.value, 0)
    const points = result.points.map((point, index) => ({
      ...point,
      color: point.color || chartPalette[index % chartPalette.length],
    }))
    return (
      <div className="pie-preview">
        <ResponsiveContainer width="55%" height="100%">
          <PieChart>
            <Pie data={points} dataKey="value" innerRadius="55%" outerRadius="82%" paddingAngle={2}>
              {points.map((entry) => <Cell key={entry.key} fill={entry.color} />)}
            </Pie>
          </PieChart>
        </ResponsiveContainer>
        <div>
          {points.map((entry) => (
            <span key={entry.key}>
              <i style={{ background: entry.color }} />{entry.label}{' '}
              <b>{total > 0 ? formatNumber((entry.value / total) * 100) : '0'}%</b>
            </span>
          ))}
        </div>
      </div>
    )
  }

  if (visual === 'mentions_feed' || visual === 'table') {
    if (visual === 'table' && result?.kind === 'scalar') {
      return (
        <div className="simple-value-table">
          <div>
            <small>Valor</small>
            <strong>{formatScalarMetric(result)}</strong>
          </div>
        </div>
      )
    }

    if (visual === 'table' && result?.kind === 'series') {
      if (result.points.length === 0) return <EmptyMetric />

      return (
        <div className="simple-value-table">
          {result.points.map((point) => (
            <div key={point.key}>
              <small>{point.label}</small>
              <strong>{formatNumber(point.value)}</strong>
            </div>
          ))}
        </div>
      )
    }

    if (result?.kind !== 'list' || result.items.length === 0) return <EmptyMetric />

    return (
      <div className="mention-table">
        {result.items.map((post) => {
          const platform = post.platform_post?.platform?.name ?? 'Web'
          const author = post.platform_post?.author_handle ?? post.platform_post?.author_name ?? 'Autor desconocido'
          return (
          <div key={post.id}>
            <span className="platform-pill">{platform.slice(0, 2)}</span>
            <strong>{author}</strong>
            <p>{post.platform_post?.content_text ?? 'Mencion sin texto'}</p>
            <small>{formatMentionDate(post.platform_post?.posted_at)}</small>
          </div>
          )
        })}
      </div>
    )
  }

  return <div className="coming-data"><RefreshCw size={18} /> Vista de datos pendiente</div>
}

function WidgetProperties({
  widget,
  onChange,
  onRemove,
  onClose,
}: {
  widget: DashboardWidget
  onChange: (changes: Partial<DashboardWidget>) => void
  onRemove: () => void
  onClose: () => void
}) {
  const allowedVisualizations: VisualizationType[] = [
    'kpi',
    'line',
    'bar',
    'pie',
    'table',
    'mentions_feed',
    'text',
  ]

  function updateConfig(changes: JsonObject) {
    onChange({ config: { ...widget.config, ...changes } })
  }

  return (
    <aside className="properties-panel">
      <div className="panel-heading">
        <div>
          <strong>Propiedades</strong>
          <span>{widget.metric?.name ?? widget.metric_code ?? 'Contenido editorial'}</span>
        </div>
        <button className="icon-button" onClick={onClose} aria-label="Cerrar propiedades">
          <PanelRightClose size={15} />
        </button>
      </div>

      <div className="properties-content">
        <section>
          <h3>General</h3>
          <label>
            Titulo
            <input value={widget.title} onChange={(event) => onChange({ title: event.target.value })} />
          </label>
          <label>
            Descripcion
            <textarea
              value={widget.description ?? ''}
              onChange={(event) => onChange({ description: event.target.value || null })}
              rows={3}
            />
          </label>
        </section>

        <section>
          <h3>Visualizacion</h3>
          <div className="visualization-picker">
            {allowedVisualizations.map((visualization) => {
              const type = visualization === 'mentions_feed' ? 'mentions_feed' : visualization
              const Icon = widgetIcons[type as WidgetType] ?? LayoutDashboard
              return (
                <button
                  className={widget.visualization_type === visualization ? 'active' : ''}
                  key={visualization}
                  onClick={() => onChange({ visualization_type: visualization })}
                  title={visualization}
                >
                  <Icon size={15} />
                </button>
              )
            })}
          </div>
        </section>

        <section>
          <h3>Datos</h3>
          <label>
            Metrica
            <input value={widget.metric?.name ?? widget.metric_code ?? 'Sin metrica'} readOnly />
          </label>
          <label>
            Periodo
            <select
              value={String(widget.config.date_range ?? '30d')}
              onChange={(event) => updateConfig({ date_range: event.target.value })}
            >
              <option value="7d">Ultimos 7 dias</option>
              <option value="30d">Ultimos 30 dias</option>
              <option value="90d">Ultimos 90 dias</option>
            </select>
          </label>
          {widget.visualization_type === 'line' ? (
            <label>
              Intervalo
              <select
                value={String(widget.config.interval ?? 'day')}
                onChange={(event) => updateConfig({ interval: event.target.value })}
              >
                <option value="day">Dia</option>
                <option value="week">Semana</option>
                <option value="month">Mes</option>
              </select>
            </label>
          ) : null}
        </section>

        <section>
          <h3>Estado</h3>
          <label className="toggle-row">
            <span>
              Visible para el cliente
              <small>Los widgets ocultos solo aparecen en edicion.</small>
            </span>
            <input
              type="checkbox"
              checked={widget.is_visible}
              onChange={(event) => onChange({ is_visible: event.target.checked })}
            />
          </label>
        </section>
      </div>

      <div className="properties-footer">
        <button className="danger-button" onClick={onRemove}>
          <Trash2 size={14} /> Eliminar widget
        </button>
      </div>
    </aside>
  )
}

function GlobalFilters({
  filters,
  values,
  brands,
  platforms,
  previewMode,
  onChange,
}: {
  filters: DashboardFilter[]
  values: Partial<Record<DashboardFilterField, JsonValue>>
  brands: Brand[]
  platforms: Platform[]
  previewMode: boolean
  onChange: (field: DashboardFilterField, value: JsonValue) => void
}) {
  const visibleFilters = filters.filter((filter) => filter.is_visible)

  if (visibleFilters.length === 0) return null

  return (
    <div className="global-filters">
      <SlidersHorizontal size={14} />
      {visibleFilters.map((filter) => {
        const value = values[filter.field_code] ?? filter.default_value

        if (filter.field_code === 'date_range') {
          return (
            <label className="global-filter-select" key={filter.id}>
              <span>{filter.label}</span>
              <select
                value={asDateRange(value) ?? '30d'}
                onChange={(event) => onChange(filter.field_code, event.target.value)}
              >
                <option value="7d">7 dias</option>
                <option value="30d">30 dias</option>
                <option value="90d">90 dias</option>
              </select>
              <ChevronDown size={13} />
            </label>
          )
        }

        if (filter.field_code === 'brand_ids') {
          return (
            <MultiSelectFilter
              key={filter.id}
              label={filter.label}
              options={brands.map((brand) => ({ id: brand.id, name: brand.name }))}
              selected={asStringArray(value)}
              onChange={(selected) => onChange(filter.field_code, selected)}
            />
          )
        }

        if (filter.field_code === 'platform_ids') {
          return (
            <MultiSelectFilter
              key={filter.id}
              label={filter.label}
              options={platforms.map((platform) => ({ id: platform.id, name: platform.name }))}
              selected={asStringArray(value)}
              onChange={(selected) => onChange(filter.field_code, selected)}
            />
          )
        }

        if (filter.field_code === 'brand_type') {
          return (
            <label className="global-filter-select" key={filter.id}>
              <span>{filter.label}</span>
              <select
                value={asBrandType(value) ?? ''}
                onChange={(event) => onChange(filter.field_code, event.target.value || null)}
              >
                <option value="">Todos</option>
                <option value="own_brand">Marca propia</option>
                <option value="own_subbrand">Submarca propia</option>
                <option value="competitor">Competidor</option>
                <option value="competitor_subbrand">Submarca competidora</option>
              </select>
              <ChevronDown size={13} />
            </label>
          )
        }

        if (filter.field_code === 'relevance') {
          return (
            <label className="global-filter-select" key={filter.id}>
              <span>{filter.label}</span>
              <select
                value={typeof value === 'boolean' ? String(value) : ''}
                onChange={(event) => onChange(
                  filter.field_code,
                  event.target.value === '' ? null : event.target.value === 'true',
                )}
              >
                <option value="">Todas</option>
                <option value="true">Relevantes</option>
                <option value="false">No relevantes</option>
              </select>
              <ChevronDown size={13} />
            </label>
          )
        }

        return (
          <label className="global-filter-search" key={filter.id}>
            <Search size={13} />
            <input
              value={asNonEmptyString(value) ?? ''}
              onChange={(event) => onChange(filter.field_code, event.target.value)}
              placeholder={filter.label}
            />
          </label>
        )
      })}
      {!previewMode ? <small>Filtros globales</small> : null}
    </div>
  )
}

function MultiSelectFilter({
  label,
  options,
  selected,
  onChange,
}: {
  label: string
  options: Array<{ id: string; name: string }>
  selected: string[]
  onChange: (selected: string[]) => void
}) {
  const selectedNames = options
    .filter((option) => selected.includes(option.id))
    .map((option) => option.name)
  const summary = selectedNames.length === 0
    ? 'Todos'
    : selectedNames.length === 1
      ? selectedNames[0]
      : `${selectedNames.length} seleccionados`

  return (
    <details className="global-filter-menu">
      <summary>
        <span>{label}</span>
        <strong>{summary}</strong>
        <ChevronDown size={13} />
      </summary>
      <div className="global-filter-options">
        <button type="button" onClick={() => onChange([])}>Todos</button>
        {options.map((option) => (
          <label key={option.id}>
            <input
              type="checkbox"
              checked={selected.includes(option.id)}
              onChange={(event) => onChange(
                event.target.checked
                  ? [...selected, option.id]
                  : selected.filter((id) => id !== option.id),
              )}
            />
            <span>{option.name}</span>
          </label>
        ))}
      </div>
    </details>
  )
}

function buildMetricQueries(
  widgets: DashboardWidget[],
  filters: DashboardFilter[],
  values: Partial<Record<DashboardFilterField, JsonValue>>,
): MetricQueryInput[] {
  const globalFilters = new Map(
    filters.map((filter) => [
      filter.field_code,
      values[filter.field_code] ?? filter.default_value,
    ]),
  )

  return widgets.flatMap((widget) => {
    if (!widget.metric_code) return []

    const dateRange = asDateRange(globalFilters.get('date_range'))
      ?? asDateRange(widget.config.date_range)
      ?? '30d'
    const brandIds = asStringArray(
      globalFilters.get('brand_ids') ?? widget.config.brand_ids,
    )
    const platformIds = asStringArray(
      globalFilters.get('platform_ids') ?? widget.config.platform_ids,
    )
    const brandType = asBrandType(
      globalFilters.get('brand_type') ?? widget.config.brand_type,
    )
    const relevance = asBoolean(
      globalFilters.get('relevance') ?? widget.config.relevance,
    )
    const search = asNonEmptyString(
      globalFilters.get('search') ?? widget.config.search,
    )
    const interval = asInterval(widget.config.interval)
    const limit = asPositiveInteger(widget.config.limit)

    return [{
      key: widget.id,
      metric_code: widget.metric_code,
      date_range: dateRange,
      ...(interval ? { interval } : {}),
      ...(limit ? { limit } : {}),
      ...(brandIds.length > 0 ? { brand_ids: brandIds } : {}),
      ...(platformIds.length > 0 ? { platform_ids: platformIds } : {}),
      ...(brandType ? { brand_type: brandType } : {}),
      ...(relevance === undefined ? {} : { relevance }),
      ...(search ? { search } : {}),
    }]
  })
}

function createWidgetFromTemplate(
  template: WidgetTemplate,
  dashboard: Dashboard,
  sortOrder: number,
): DashboardWidget {
  return {
    id: `new-${crypto.randomUUID()}`,
    client_id: dashboard.client_id,
    dashboard_id: dashboard.id,
    widget_template_id: template.id,
    widget_type: template.widget_type,
    visualization_type: template.default_visualization_type,
    metric_code: template.metric_code,
    title: template.default_title,
    description: template.description,
    grid_x: 0,
    grid_y: 0,
    grid_width: Math.min(template.default_width, dashboard.grid_columns),
    grid_height: template.default_height,
    min_width: template.min_width,
    min_height: template.min_height,
    sort_order: sortOrder,
    config: template.default_config,
    filters: [],
    is_visible: true,
    template,
    metric: template.metric,
    created_at: null,
    updated_at: null,
  }
}

function createPersonalizedWidget(
  draft: PersonalizedWidgetDraft,
  dashboard: Dashboard,
  sortOrder: number,
): DashboardWidget {
  const sizing = personalizedVisualizationSizing(draft.visualization)

  return {
    id: `new-${crypto.randomUUID()}`,
    client_id: dashboard.client_id,
    dashboard_id: dashboard.id,
    widget_template_id: null,
    widget_type: personalizedWidgetType(draft.visualization),
    visualization_type: draft.visualization,
    metric_code: draft.metric.code,
    title: draft.title,
    description: draft.metric.description,
    grid_x: 0,
    grid_y: 0,
    grid_width: Math.min(sizing.defaultWidth, dashboard.grid_columns),
    grid_height: sizing.defaultHeight,
    min_width: sizing.minWidth,
    min_height: sizing.minHeight,
    sort_order: sortOrder,
    config: draft.config,
    filters: [],
    is_visible: true,
    metric: draft.metric,
    created_at: null,
    updated_at: null,
  }
}

function personalizedWidgetType(visualization: VisualizationType): WidgetType {
  return visualization === 'mentions_feed'
    ? 'mentions_feed'
    : visualization === 'table'
      ? 'table'
      : visualization === 'line'
        ? 'line'
        : visualization === 'bar'
          ? 'bar'
          : visualization === 'pie'
            ? 'pie'
            : 'kpi'
}

function personalizedVisualizationSizing(visualization: VisualizationType): {
  defaultWidth: number
  defaultHeight: number
  minWidth: number
  minHeight: number
} {
  switch (visualization) {
    case 'line':
      return { defaultWidth: 8, defaultHeight: 4, minWidth: 4, minHeight: 3 }
    case 'bar':
    case 'pie':
      return { defaultWidth: 4, defaultHeight: 4, minWidth: 3, minHeight: 3 }
    case 'table':
    case 'mentions_feed':
      return { defaultWidth: 8, defaultHeight: 5, minWidth: 4, minHeight: 3 }
    default:
      return { defaultWidth: 3, defaultHeight: 2, minWidth: 2, minHeight: 2 }
  }
}

function buildPersonalizedConfig(
  metric: WidgetBuilderMetric,
  options: {
    dateRange: '7d' | '30d' | '90d'
    interval: 'day' | 'week' | 'month'
    limit: number
    brandIds: string[]
    platformIds: string[]
    brandType: string
    relevance: 'all' | 'true' | 'false'
    search: string
    focusFieldCode?: string
    supportsComparison: boolean
  },
): JsonObject {
  const config: JsonObject = {}
  const filters = new Set(metric.recommended_filters)

  if (filters.has('date_range')) config.date_range = options.dateRange
  if (filters.has('brand_ids') && options.brandIds.length > 0) config.brand_ids = options.brandIds
  if (filters.has('platform_ids') && options.platformIds.length > 0) config.platform_ids = options.platformIds
  if (filters.has('brand_type') && options.brandType !== '') config.brand_type = options.brandType
  if (filters.has('relevance') && options.relevance !== 'all') config.relevance = options.relevance === 'true'
  if (filters.has('search') && options.search.trim() !== '') config.search = options.search.trim()
  if (options.focusFieldCode) config.focus_field_code = options.focusFieldCode
  if (asStringArray(metric.config_schema.allowed_intervals).length > 0) config.interval = options.interval
  if (metric.value_type === 'list') config.limit = options.limit
  if (options.supportsComparison) config.show_comparison = true

  return config
}

function prioritizeMetricsForField(
  source: WidgetBuilderSource,
  fieldCode: string,
): WidgetBuilderMetric[] {
  const suggested = source.metrics.filter((metric) => isMetricSuggestedForField(metric.code, fieldCode))
  const rest = source.metrics.filter((metric) => !isMetricSuggestedForField(metric.code, fieldCode))

  return [...suggested, ...rest]
}

function isMetricSuggestedForField(metricCode: string, fieldCode: string): boolean {
  switch (metricCode) {
    case 'mentions.timeline':
      return fieldCode === 'posted_at'
    case 'mentions.by_platform':
      return fieldCode === 'platform_id'
    case 'mentions.by_brand':
      return fieldCode === 'brand_id'
    case 'mentions.latest':
      return ['content_text', 'posted_at', 'brand_id', 'platform_id'].includes(fieldCode)
    case 'mentions.total':
      return ['posted_at', 'brand_id', 'platform_id'].includes(fieldCode)
    case 'mentions.relevant':
      return ['is_relevant_candidate', 'posted_at', 'brand_id'].includes(fieldCode)
    case 'brands.total':
    case 'competitors.total':
      return ['name', 'brand_type', 'is_active'].includes(fieldCode)
    case 'usage.cost':
      return ['cost_amount', 'occurred_at', 'platform_id', 'usage_type'].includes(fieldCode)
    case 'extractions.success_rate':
      return ['status', 'finished_at', 'platform_id', 'brand_id'].includes(fieldCode)
    default:
      return false
  }
}

function MetricChoiceCard({
  metric,
  selected,
  kindLabel,
  compact = false,
  onSelect,
}: {
  metric: WidgetBuilderMetric
  selected: boolean
  kindLabel: string
  compact?: boolean
  onSelect: () => void
}) {
  return (
    <button
      className={`metric-choice-card ${selected ? 'active' : ''} ${compact ? 'compact' : ''}`}
      onClick={onSelect}
      type="button"
    >
      <div className="metric-choice-card-top">
        <small>{kindLabel}</small>
        <small>{labelForVisualization(metric.default_visualization_type)}</small>
      </div>
      <strong>{metric.name}</strong>
      <span>{metric.description ?? 'Métrica lista para convertirse en widget.'}</span>
      <div className="metric-choice-card-meta">
        <small>{metric.code}</small>
        {metric.supported_visualizations.length > 1 ? (
          <small>{metric.supported_visualizations.length} vistas</small>
        ) : null}
      </div>
    </button>
  )
}

function MetricChoiceRow({
  metric,
  selected,
  onSelect,
}: {
  metric: WidgetBuilderMetric
  selected: boolean
  onSelect: () => void
}) {
  return (
    <button
      className={`metric-choice-row ${selected ? 'active' : ''}`}
      onClick={onSelect}
      type="button"
    >
      <div className="metric-choice-row-main">
        <strong>{metric.name}</strong>
        <span>{metric.description ?? 'Métrica disponible para este widget.'}</span>
      </div>
      <div className="metric-choice-row-meta">
        <small>{labelForVisualization(metric.default_visualization_type)}</small>
        <small>{metric.code}</small>
      </div>
    </button>
  )
}

function BuilderStepLabel({
  index,
  label,
  value,
  muted = false,
  subtleValue = false,
}: {
  index: string
  label: string
  value: string
  muted?: boolean
  subtleValue?: boolean
}) {
  return (
    <div className={`builder-step-label ${muted ? 'muted' : ''} ${subtleValue ? 'subtle-value' : ''}`}>
      <strong>{index} {label}</strong>
      <small>{value}</small>
    </div>
  )
}

function BuilderChip({
  label,
  muted = false,
}: {
  label: string
  muted?: boolean
}) {
  return <span className={`builder-chip ${muted ? 'muted' : ''}`}>{label}</span>
}

function BuilderCountBadge({
  count,
  label,
}: {
  count: number
  label: string
}) {
  return <span className="builder-count-badge">{count} {label}</span>
}

function labelForVisualization(visualization: VisualizationType): string {
  switch (visualization) {
    case 'line':
      return 'Línea'
    case 'bar':
      return 'Barras'
    case 'pie':
      return 'Circular'
    case 'table':
      return 'Tabla'
    case 'mentions_feed':
      return 'Feed'
    default:
      return 'KPI'
  }
}

function compatibleVisualizationsForMetric(
  metric: Pick<WidgetBuilderMetric, 'result_kind' | 'value_type' | 'supported_visualizations'>,
): VisualizationType[] {
  if (metric.result_kind === 'list') {
    return ['mentions_feed', 'table']
  }

  if (metric.result_kind === 'series') {
    return ['line', 'bar', 'pie', 'table']
  }

  if (metric.value_type === 'percentage') {
    return ['kpi', 'bar', 'pie']
  }

  return ['kpi', 'bar', 'table']
}

function formatWidgetFootprint(width: number, height: number): string {
  return `${width} × ${height}`
}

function buildBuilderNarrative(options: {
  sourceLabel: string
  fieldLabel: string
  metricName: string
  resultKind: 'scalar' | 'series' | 'list'
  visualization: VisualizationType
  dateRange: '7d' | '30d' | '90d'
  interval: 'day' | 'week' | 'month'
  limit: number
}): string {
  const rangeLabel = options.dateRange === '7d'
    ? 'los últimos 7 días'
    : options.dateRange === '90d'
      ? 'los últimos 90 días'
      : 'los últimos 30 días'

  if ((options.visualization === 'line' || options.visualization === 'bar') && options.resultKind === 'series') {
    const intervalLabel = options.interval === 'day'
      ? 'por día'
      : options.interval === 'week'
        ? 'por semana'
        : 'por mes'

    return `Mostraremos ${options.metricName.toLowerCase()} de ${options.sourceLabel.toLowerCase()}, centrado en ${options.fieldLabel.toLowerCase()}, ${intervalLabel}, en ${rangeLabel}.`
  }

  if (options.visualization === 'table' || options.visualization === 'mentions_feed') {
    return `Mostraremos hasta ${options.limit} filas de ${options.sourceLabel.toLowerCase()}, centradas en ${options.fieldLabel.toLowerCase()}, dentro de ${rangeLabel}.`
  }

  if (options.visualization === 'bar' && options.resultKind === 'scalar') {
    return `Mostraremos ${options.metricName.toLowerCase()} de ${options.sourceLabel.toLowerCase()} como comparación directa, con foco en ${options.fieldLabel.toLowerCase()}, dentro de ${rangeLabel}.`
  }

  if (options.visualization === 'pie' && options.resultKind === 'scalar') {
    return `Mostraremos ${options.metricName.toLowerCase()} de ${options.sourceLabel.toLowerCase()} como reparto visual, con foco en ${options.fieldLabel.toLowerCase()}, dentro de ${rangeLabel}.`
  }

  return `Mostraremos ${options.metricName.toLowerCase()} de ${options.sourceLabel.toLowerCase()}, con foco en ${options.fieldLabel.toLowerCase()}, dentro de ${rangeLabel}.`
}

function summarizeAdvancedFilters(options: {
  brandIds: string[]
  platformIds: string[]
  brandType: string
  relevance: 'all' | 'true' | 'false'
  searchValue: string
}): string {
  const parts: string[] = []

  if (options.brandIds.length > 0) parts.push(`${options.brandIds.length} marcas`)
  if (options.platformIds.length > 0) parts.push(`${options.platformIds.length} plataformas`)
  if (options.brandType !== '') parts.push('tipo de marca')
  if (options.relevance !== 'all') parts.push(options.relevance === 'true' ? 'solo relevantes' : 'no relevantes')
  if (options.searchValue.trim() !== '') parts.push(`busqueda "${options.searchValue.trim()}"`)

  return parts.length > 0 ? parts.join(' · ') : 'Sin segmentación adicional.'
}

function buildBuilderPreviewWidget({
  metric,
  visualization,
  title,
}: {
  metric: WidgetBuilderMetric
  visualization: VisualizationType
  title: string
}): DashboardWidget {
  return {
    id: 'builder-preview',
    client_id: 'builder-preview',
    dashboard_id: 'builder-preview',
    widget_template_id: null,
    widget_type: personalizedWidgetType(visualization),
    visualization_type: visualization,
    metric_code: metric.code,
    title,
    description: metric.description,
    grid_x: 0,
    grid_y: 0,
    grid_width: 4,
    grid_height: 3,
    min_width: 2,
    min_height: 2,
    sort_order: 0,
    config: {},
    filters: [],
    is_visible: true,
    metric,
    created_at: null,
    updated_at: null,
  }
}

function buildBuilderPreviewResult(
  metric: Pick<WidgetBuilderMetric, 'code' | 'value_type' | 'result_kind'>,
  visualization: VisualizationType,
  sourceLabel: string,
  fieldLabel?: string,
): MetricQueryResult | undefined {
  const resultKind = metric.result_kind
    ?? (visualization === 'mentions_feed' || visualization === 'table'
      ? 'list'
      : visualization === 'line' || visualization === 'bar' || visualization === 'pie'
        ? 'series'
        : 'scalar')

  if (resultKind === 'scalar') {
    return {
      key: 'builder-preview',
      metric_code: metric.code,
      kind: 'scalar',
      value_type: metric.value_type === 'currency' ? 'currency' : metric.value_type === 'percentage' ? 'percentage' : 'number',
      value: metric.value_type === 'percentage' ? 74 : metric.value_type === 'currency' ? 1280 : 284,
      comparison: { previous_value: 241, change_percent: 17.8 },
      meta: {},
    }
  }

  if (resultKind === 'series') {
    const labels = visualization === 'line'
      ? ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab']
      : fieldLabel
        ? [fieldLabel, 'Competencia', 'Earned']
        : ['A', 'B', 'C']

    return {
      key: 'builder-preview',
      metric_code: metric.code,
      kind: 'series',
      value_type: 'number',
      points: labels.map((label, index) => ({
        key: `${index}`,
        label,
        value: visualization === 'line'
          ? [28, 34, 31, 42, 39, 47][index] ?? 18
          : [52, 31, 17][index] ?? 12,
      })),
      meta: {
        preview_source: sourceLabel,
      },
    }
  }

  if (resultKind === 'list') {
    return {
      key: 'builder-preview',
      metric_code: metric.code,
      kind: 'list',
      items: buildBuilderPreviewPosts(sourceLabel, fieldLabel),
      meta: {},
    }
  }

  return undefined
}

function buildBuilderPreviewPosts(sourceLabel: string, fieldLabel?: string): Post[] {
  return [
    {
      id: 'preview-post-1',
      client_id: 'preview',
      brand_id: 'preview-brand',
      platform_post_id: 'preview-platform-post-1',
      extraction_run_id: null,
      matched_query: null,
      match_type: 'keyword',
      is_relevant_candidate: true,
      created_at: null,
      platform_post: {
        id: 'preview-platform-post-1',
        platform_id: 'preview-platform',
        external_id: 'preview-1',
        author_handle: '@sibilare',
        author_name: 'Sibilare',
        content_text: `${sourceLabel}: ejemplo de contenido con foco en ${fieldLabel ?? 'la dimensión elegida'}.`,
        url: null,
        posted_at: '2026-07-01T08:00:00Z',
        language_code: 'es',
        media_urls: [],
        metrics: {},
        platform: { id: 'preview-platform', code: 'instagram', name: 'Instagram', is_active: true },
      },
    },
    {
      id: 'preview-post-2',
      client_id: 'preview',
      brand_id: 'preview-brand',
      platform_post_id: 'preview-platform-post-2',
      extraction_run_id: null,
      matched_query: null,
      match_type: 'keyword',
      is_relevant_candidate: false,
      created_at: null,
      platform_post: {
        id: 'preview-platform-post-2',
        platform_id: 'preview-platform-2',
        external_id: 'preview-2',
        author_handle: '@brandwatcher',
        author_name: 'Brand Watcher',
        content_text: 'Una preview breve para ver densidad, tono y legibilidad del feed.',
        url: null,
        posted_at: '2026-06-30T17:30:00Z',
        language_code: 'es',
        media_urls: [],
        metrics: {},
        platform: { id: 'preview-platform-2', code: 'news', name: 'News', is_active: true },
      },
    },
  ]
}

function buildTemplatePreviewWidget(template: WidgetTemplate): DashboardWidget {
  return {
    id: `template-preview-${template.id}`,
    client_id: 'template-preview',
    dashboard_id: 'template-preview',
    widget_template_id: template.id,
    widget_type: template.widget_type,
    visualization_type: template.default_visualization_type,
    metric_code: template.metric_code,
    title: template.name,
    description: template.description,
    grid_x: 0,
    grid_y: 0,
    grid_width: template.default_width,
    grid_height: template.default_height,
    min_width: template.min_width,
    min_height: template.min_height,
    sort_order: 0,
    config: template.default_config,
    filters: [],
    is_visible: true,
    metric: template.metric,
    created_at: null,
    updated_at: null,
  }
}

function buildTemplatePreviewResult(template: WidgetTemplate): MetricQueryResult | undefined {
  const metric = template.metric
  if (!metric) return undefined

  return buildBuilderPreviewResult(
    metric,
    template.default_visualization_type,
    mapTemplateGroup(template),
    metric.name,
  )
}

const templateGroups: Array<{
  id: TemplateGroup
  label: string
  description: string
}> = [
  { id: 'overview', label: 'Overview', description: 'KPIs y resumen general del dashboard.' },
  { id: 'mentions', label: 'Mentions', description: 'Volumen, evolucion y feed de menciones.' },
  { id: 'competitors', label: 'Competitors', description: 'Share of voice y comparativas entre marcas.' },
  { id: 'content', label: 'Content', description: 'Bloques editoriales y notas de contexto.' },
  { id: 'operations', label: 'Operations', description: 'Costes, salud operativa y seguimiento interno.' },
  { id: 'personalized', label: 'Personalized', description: 'Partimos de una plantilla y luego ajustas filtros y visualizacion.' },
]

const templateGroupIcons: Record<TemplateGroup, typeof LayoutDashboard> = {
  overview: LayoutDashboard,
  mentions: MessageSquareText,
  competitors: Users,
  operations: Settings2,
  content: FileText,
  personalized: SlidersHorizontal,
}

function matchesTemplateGroup(template: WidgetTemplate, group: TemplateGroup): boolean {
  if (group === 'personalized') return true
  return mapTemplateGroup(template) === group
}

function mapTemplateGroup(template: WidgetTemplate): Exclude<TemplateGroup, 'personalized'> {
  switch (template.category) {
    case 'competitors':
      return 'competitors'
    case 'content':
      return 'content'
    case 'operations':
      return 'operations'
    case 'overview':
      return 'overview'
    case 'mentions':
    case 'brands':
    case 'engagement':
    default:
      return 'mentions'
  }
}

function groupForWidget(widget: DashboardWidget): TemplateGroup {
  if (widget.widget_type === 'text') return 'content'
  if (widget.widget_type === 'mentions_feed') return 'mentions'
  if (widget.metric_code?.startsWith('usage.')) return 'operations'
  if (widget.metric_code === 'mentions.by_brand') return 'competitors'
  if (widget.metric_code?.startsWith('mentions.')) return 'mentions'
  return 'overview'
}

function appendFreeformWidget(
  widgets: DashboardWidget[],
  widget: DashboardWidget,
  gridColumns: number,
): DashboardWidget {
  const bottom = widgets.reduce(
    (max, current) => Math.max(max, current.grid_y + current.grid_height),
    0,
  )

  return {
    ...widget,
    grid_x: 0,
    grid_y: bottom,
    grid_width: Math.min(widget.grid_width, gridColumns),
  }
}

function insertStackedWidget(
  widgets: DashboardWidget[],
  rowWidgets: DashboardWidget[],
  widget: DashboardWidget,
  gridColumns: number,
  direction: 'above' | 'below',
): DashboardWidget[] {
  const rowTop = rowWidgets.reduce((min, current) => Math.min(min, current.grid_y), rowWidgets[0]?.grid_y ?? 0)
  const rowBottom = rowWidgets.reduce(
    (max, current) => Math.max(max, current.grid_y + current.grid_height),
    rowTop,
  )
  const insertY = direction === 'above' ? rowTop : rowBottom
  const inserted = {
    ...widget,
    grid_x: 0,
    grid_y: insertY,
    grid_width: gridColumns,
  }

  return [
    ...widgets.map((current) => current.grid_y >= insertY
      ? { ...current, grid_y: current.grid_y + inserted.grid_height }
      : current),
    inserted,
  ]
}

function insertSideWidget(
  widgets: DashboardWidget[],
  rowWidgets: DashboardWidget[],
  anchor: DashboardWidget,
  widget: DashboardWidget,
  gridColumns: number,
  direction: 'left' | 'right',
): DashboardWidget[] {
  if (rowWidgets.length >= 3) {
    return insertStackedWidget(widgets, rowWidgets, widget, gridColumns, 'below')
  }

  const anchorIndex = rowWidgets.findIndex((current) => current.id === anchor.id)
  const insertIndex = direction === 'left' ? anchorIndex : anchorIndex + 1
  const rowMembers = [...rowWidgets]
  rowMembers.splice(insertIndex, 0, widget)

  const widths = distributeColumns(gridColumns, rowMembers.length)
  const oldRowBottom = rowWidgets.reduce(
    (max, current) => Math.max(max, current.grid_y + current.grid_height),
    anchor.grid_y + anchor.grid_height,
  )
  const rowHeight = Math.max(
    widget.grid_height,
    ...rowWidgets.map((current) => current.grid_height),
  )
  const rowIds = new Set(rowWidgets.map((current) => current.id))
  const xById = new Map<string, number>()
  const widthById = new Map<string, number>()

  let cursor = 0
  for (let index = 0; index < rowMembers.length; index += 1) {
    xById.set(rowMembers[index].id, cursor)
    widthById.set(rowMembers[index].id, widths[index])
    cursor += widths[index]
  }

  const inserted = {
    ...widget,
    grid_x: xById.get(widget.id) ?? 0,
    grid_y: anchor.grid_y,
    grid_width: widthById.get(widget.id) ?? gridColumns,
    grid_height: rowHeight,
  }
  const deltaHeight = rowHeight - (oldRowBottom - anchor.grid_y)

  const updatedExisting = widgets.map((current) => {
    if (rowIds.has(current.id)) {
      return {
        ...current,
        grid_x: xById.get(current.id) ?? current.grid_x,
        grid_y: anchor.grid_y,
        grid_width: widthById.get(current.id) ?? current.grid_width,
        grid_height: rowHeight,
      }
    }

    if (deltaHeight > 0 && current.grid_y >= oldRowBottom) {
      return {
        ...current,
        grid_y: current.grid_y + deltaHeight,
      }
    }

    return current
  })

  return [...updatedExisting, inserted]
}

function distributeColumns(totalColumns: number, items: number): number[] {
  const base = Math.floor(totalColumns / items)
  const remainder = totalColumns % items

  return Array.from({ length: items }, (_, index) => base + (index < remainder ? 1 : 0))
}

function insertWidgetAtTarget(
  widgets: DashboardWidget[],
  widget: DashboardWidget,
  gridColumns: number,
  target: InsertionTarget,
): DashboardWidget[] {
  if (target.kind === 'append' || target.widgetId === null) {
    return [...widgets, appendFreeformWidget(widgets, widget, gridColumns)]
  }

  const anchor = widgets.find((current) => current.id === target.widgetId)

  if (!anchor) {
    return [...widgets, appendFreeformWidget(widgets, widget, gridColumns)]
  }

  const rowWidgets = widgets
    .filter((current) => current.grid_y === anchor.grid_y)
    .sort((left, right) => left.grid_x - right.grid_x)

  if (target.kind === 'above' || target.kind === 'below') {
    return insertStackedWidget(widgets, rowWidgets, widget, gridColumns, target.kind)
  }

  return insertSideWidget(widgets, rowWidgets, anchor, widget, gridColumns, target.kind)
}

function describeInsertionTarget(target: InsertionTarget): string {
  switch (target.kind) {
    case 'append':
      return 'Se anadira un bloque nuevo al final del dashboard.'
    case 'above':
      return 'Se insertara una fila nueva encima del bloque seleccionado.'
    case 'below':
      return 'Se insertara una fila nueva debajo del bloque seleccionado.'
    case 'left':
      return 'Se insertara un bloque a la izquierda dentro de la misma fila.'
    case 'right':
      return 'Se insertara un bloque a la derecha dentro de la misma fila.'
  }
}

function matchesInsertionTarget(
  current: InsertionTarget,
  expected: InsertionTarget,
): boolean {
  return current.kind === expected.kind && current.widgetId === expected.widgetId
}

function asDateRange(value: unknown): MetricQueryInput['date_range'] | undefined {
  return value === '7d' || value === '30d' || value === '90d'
    ? value
    : undefined
}

function asInterval(value: unknown): MetricQueryInput['interval'] | undefined {
  return value === 'day' || value === 'week' || value === 'month'
    ? value
    : undefined
}

function asBrandType(value: unknown): MetricQueryInput['brand_type'] | undefined {
  return value === 'own_brand'
    || value === 'own_subbrand'
    || value === 'competitor'
    || value === 'competitor_subbrand'
    ? value
    : undefined
}

function asStringArray(value: unknown): string[] {
  return Array.isArray(value)
    ? value.filter((item): item is string => typeof item === 'string')
    : []
}

function asBoolean(value: unknown): boolean | undefined {
  return typeof value === 'boolean' ? value : undefined
}

function asNonEmptyString(value: unknown): string | undefined {
  return typeof value === 'string' && value.trim() !== ''
    ? value.trim()
    : undefined
}

function asPositiveInteger(value: unknown): number | undefined {
  return typeof value === 'number' && Number.isInteger(value) && value > 0
    ? value
    : undefined
}

function EmptyMetric() {
  return <div className="coming-data"><RefreshCw size={18} /> Sin datos para estos filtros</div>
}

function formatScalarMetric(
  result: Extract<MetricQueryResult, { kind: 'scalar' }>,
): string {
  if (result.value_type === 'currency') {
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: String(result.meta.currency ?? 'EUR'),
      maximumFractionDigits: 2,
    }).format(result.value)
  }

  if (result.value_type === 'percentage') {
    return `${formatNumber(result.value)}%`
  }

  return formatNumber(result.value)
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('es-ES', {
    maximumFractionDigits: 1,
  }).format(value)
}

function formatPointLabel(value: string): string {
  const date = /^\d{4}-\d{2}-\d{2}/.test(value)
    ? new Date(`${value.slice(0, 10)}T00:00:00`)
    : null

  return date && !Number.isNaN(date.getTime())
    ? new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'short' }).format(date)
    : value
}

function formatPreviewAxisLabel(value: string): string {
  const formattedDate = formatPointLabel(value)

  if (formattedDate !== value) {
    return formattedDate
  }

  if (value.length <= 12) {
    return value
  }

  const compactWord = value
    .split(' ')
    .map((part) => part.trim())
    .find((part) => part.length >= 4)

  if (compactWord) {
    return compactWord.length <= 10 ? compactWord : `${compactWord.slice(0, 9)}…`
  }

  return `${value.slice(0, 9)}…`
}

function formatMentionDate(value: string | null | undefined): string {
  if (!value) return 'Sin fecha'

  const date = new Date(value)

  return Number.isNaN(date.getTime())
    ? 'Sin fecha'
    : new Intl.DateTimeFormat('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
      }).format(date)
}

function EmptyDashboards({ onCreate }: { onCreate: () => void }) {
  return (
    <div className="empty-dashboards">
      <LayoutDashboard size={26} />
      <strong>Crea el primer dashboard del cliente</strong>
      <p>Empieza con la vista de escucha social o con un lienzo vacio.</p>
      <button className="primary-button" onClick={onCreate}><Plus size={15} /> Nuevo dashboard</button>
    </div>
  )
}

function NewDashboardDialog({
  onClose,
  onCreate,
}: {
  onClose: () => void
  onCreate: (name: string, blank: boolean) => void
}) {
  const [name, setName] = useState('Resumen de escucha')
  const [blank, setBlank] = useState(false)

  return (
    <div className="dialog-backdrop" role="presentation" onMouseDown={onClose}>
      <form
        className="dialog"
        onMouseDown={(event) => event.stopPropagation()}
        onSubmit={(event) => {
          event.preventDefault()
          if (name.trim()) onCreate(name.trim(), blank)
        }}
      >
        <div className="dialog-heading">
          <div>
            <span>Nuevo dashboard</span>
            <h2>Prepara un espacio para este cliente.</h2>
          </div>
          <button type="button" className="icon-button" onClick={onClose} aria-label="Cerrar">
            <PanelRightClose size={16} />
          </button>
        </div>
        <label>
          Nombre
          <input value={name} onChange={(event) => setName(event.target.value)} autoFocus />
        </label>
        <div className="starter-options">
          <button type="button" className={!blank ? 'active' : ''} onClick={() => setBlank(false)}>
            <CircleGauge size={18} />
            <strong>Resumen SML</strong>
            <span>KPIs, evolucion, plataformas y menciones.</span>
          </button>
          <button type="button" className={blank ? 'active' : ''} onClick={() => setBlank(true)}>
            <LayoutDashboard size={18} />
            <strong>Lienzo vacio</strong>
            <span>Construye la vista widget a widget.</span>
          </button>
        </div>
        <div className="dialog-actions">
          <button type="button" className="secondary-button" onClick={onClose}>Cancelar</button>
          <button className="primary-button"><Plus size={15} /> Crear dashboard</button>
        </div>
      </form>
    </div>
  )
}
