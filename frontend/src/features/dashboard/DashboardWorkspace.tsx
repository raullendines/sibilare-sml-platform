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
  SaveDashboardLayoutInput,
  VisualizationType,
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
    ])
      .then(([clientResponse, templateResponse]) => {
        setClients(clientResponse.data)
        setTemplates(templateResponse.data)
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

  function addTemplate(template: WidgetTemplate) {
    if (!dashboard) return
    const nextWidget = createWidgetFromTemplate(template, dashboard, widgets.length)

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
          activeGroup={activeTemplateGroup}
          insertionTarget={insertionTarget}
          onClose={() => setAddMenuOpen(false)}
          onSelectGroup={setActiveTemplateGroup}
          onAdd={addTemplate}
        />
      ) : null}
    </div>
  )
}

function AddWidgetMenu({
  templates,
  activeGroup,
  insertionTarget,
  onClose,
  onSelectGroup,
  onAdd,
}: {
  templates: WidgetTemplate[]
  activeGroup: TemplateGroup
  insertionTarget: InsertionTarget
  onClose: () => void
  onSelectGroup: (group: TemplateGroup) => void
  onAdd: (template: WidgetTemplate) => void
}) {
  const [search, setSearch] = useState('')
  const filtered = templates.filter((template) =>
    `${template.name} ${template.description ?? ''} ${template.metric?.name ?? ''}`
      .toLowerCase()
      .includes(search.toLowerCase()),
  )
  const visibleTemplates = filtered.filter((template) => matchesTemplateGroup(template, activeGroup))
  const groupMeta = templateGroups.find((group) => group.id === activeGroup) ?? templateGroups[0]

  return (
    <div className="dialog-backdrop" role="presentation" onMouseDown={onClose}>
      <aside className="add-widget-panel" onMouseDown={(event) => event.stopPropagation()}>
        <div className="panel-heading">
          <div>
            <strong>Anadir bloque</strong>
            <span>{describeInsertionTarget(insertionTarget)}</span>
          </div>
          <button className="icon-button" onClick={onClose} aria-label="Cerrar inserter">
            <X size={15} />
          </button>
        </div>

        <label className="search-field">
          <Search size={14} />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Buscar bloque o metrica"
          />
        </label>

        <div className="template-group-tabs" role="tablist" aria-label="Categorias de widgets">
          {templateGroups.map((group) => (
            <button
              key={group.id}
              className={group.id === activeGroup ? 'active' : ''}
              onClick={() => onSelectGroup(group.id)}
            >
              <strong>{group.label}</strong>
              <small>{group.description}</small>
            </button>
          ))}
        </div>

        <div className="template-group-body">
          <div className="template-group-heading">
            <strong>{groupMeta.label}</strong>
            <span>{groupMeta.description}</span>
          </div>

          <div className="template-groups">
            {visibleTemplates.length > 0 ? visibleTemplates.map((template) => {
              const Icon = widgetIcons[template.widget_type]
              return (
                <button
                  className="template-row"
                  key={template.id}
                  onClick={() => onAdd(template)}
                >
                  <span className="template-icon"><Icon size={15} /></span>
                  <span>
                    <strong>{template.name}</strong>
                    <small>{template.metric?.name ?? template.description ?? 'Contenido editorial'}</small>
                  </span>
                  <Plus size={14} />
                </button>
              )
            }) : (
              <div className="template-empty-state">
                <strong>No hay bloques para esta categoria</strong>
                <span>Prueba otra categoria o busca por nombre de metrica.</span>
              </div>
            )}
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
          <XAxis dataKey="label" interval={0} tickFormatter={formatPointLabel} axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#71717a' }} />
          <Tooltip />
          <Area type="monotone" dataKey="value" stroke="#18181b" strokeWidth={2} fill={`url(#area-${widget.id})`} />
        </AreaChart>
      </ResponsiveContainer>
    )
  }

  if (visual === 'bar') {
    if (result?.kind !== 'series' || result.points.length === 0) return <EmptyMetric />

    return (
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={result.points} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#eeeeef" />
          <XAxis dataKey="label" interval={0} axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#71717a' }} />
          <Tooltip />
          <Bar dataKey="value" fill="#27272a" radius={[3, 3, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    )
  }

  if (visual === 'pie') {
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
