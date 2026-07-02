import { type ReactNode, useMemo, useState } from 'react'
import {
  BriefcaseBusiness,
  Building2,
  LoaderCircle,
  Plus,
  Radio,
  Save,
  Sparkles,
} from 'lucide-react'
import type { SmlApiClient } from '../../api/client.ts'
import type { Brand, Client, CreateBrandInput, CreateClientInput } from '../../api/types.ts'

interface BudgetWorkspaceProps {
  api: SmlApiClient
  clients: Client[]
  clientId: string
  activeClient: Client | undefined
  brands: Brand[]
  onClientsChange: (clients: Client[]) => void
  onSelectClient: (clientId: string) => void
  onBrandsChange: (brands: Brand[]) => void
}

interface ClientDraft {
  client_name: string
  primary_brand_name: string
  industry: string
  website_url: string
  color: string
}

const initialDraft: ClientDraft = {
  client_name: '',
  primary_brand_name: '',
  industry: '',
  website_url: '',
  color: '',
}

export function BudgetWorkspace({
  api,
  clients,
  clientId,
  activeClient,
  brands,
  onClientsChange,
  onSelectClient,
  onBrandsChange,
}: BudgetWorkspaceProps) {
  const [draft, setDraft] = useState<ClientDraft>(initialDraft)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const sortedClients = useMemo(
    () => [...clients].sort((left, right) => Date.parse(right.created_at ?? '') - Date.parse(left.created_at ?? '')),
    [clients],
  )

  const metrics = useMemo(() => ({
    total: clients.length,
    onboarding: clients.filter((client) => client.status === 'onboarding').length,
    active: clients.filter((client) => client.status === 'active').length,
    currentBrands: brands.filter((brand) => brand.brand_type === 'own_brand').length,
  }), [clients, brands])

  async function saveClientAccount() {
    setSaving(true)
    setError(null)
    setSuccess(null)

    try {
      const clientInput: CreateClientInput = {
        name: draft.client_name.trim(),
        industry: draft.industry.trim() || null,
        status: 'onboarding',
        default_locale: 'es-ES',
        timezone: 'Europe/Madrid',
      }

      const { data: client } = await api.createClient(clientInput)

      const rootBrandInput: CreateBrandInput = {
        name: draft.primary_brand_name.trim() || draft.client_name.trim(),
        brand_type: 'own_brand',
        website_url: draft.website_url.trim() || null,
        color: draft.color.trim() || null,
        is_active: true,
      }

      const { data: rootBrand } = await api.createBrand(client.id, rootBrandInput)

      onClientsChange([client, ...sortedClients])
      onSelectClient(client.id)
      onBrandsChange([rootBrand])
      setDraft(initialDraft)
      setSuccess(`Cuenta creada: ${client.name}`)
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : 'No se pudo crear la nueva cuenta.',
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <main className="builder-shell extraction-shell">
      <header className="builder-header extraction-header">
        <div className="builder-title">
          <div>
            <span>{activeClient?.name ?? 'Presupuestos'} / Presupuestos</span>
            <strong className="extraction-header-title">Alta comercial y nuevas cuentas</strong>
          </div>
        </div>

        <div className="header-actions">
          <span className="publish-state published">
            <span />
            El selector de cliente gobierna todo el workspace
          </span>
        </div>
      </header>

      {error ? (
        <div className="error-banner">
          <span>{error}</span>
          <button onClick={() => setError(null)}>Cerrar</button>
        </div>
      ) : null}

      {success ? (
        <div className="success-banner">
          <span>{success}</span>
          <button onClick={() => setSuccess(null)}>Cerrar</button>
        </div>
      ) : null}

      <section className="builder-body extraction-body">
        <div className="extraction-content">
          <div className="extraction-summary-grid budget-summary-grid">
            <BudgetMetric
              icon={<Building2 size={16} />}
              label="Cuentas totales"
              value={String(metrics.total)}
              hint="Clientes creados desde Sibilare"
            />
            <BudgetMetric
              icon={<BriefcaseBusiness size={16} />}
              label="En onboarding"
              value={String(metrics.onboarding)}
              hint="Pendientes de activar o completar setup"
            />
            <BudgetMetric
              icon={<Radio size={16} />}
              label="Activas"
              value={String(metrics.active)}
              hint="Listas para operar en listening"
            />
            <BudgetMetric
              icon={<Sparkles size={16} />}
              label="Cliente actual"
              value={activeClient?.name ?? 'Ninguno'}
              hint={`${metrics.currentBrands} marcas raíz cargadas en el contexto activo`}
            />
          </div>

          <div className="budget-grid">
            <section className="extraction-panel">
              <div className="extraction-panel-heading">
                <div>
                  <strong>Nueva cuenta / presupuesto</strong>
                  <span>
                    Crea aquí una nueva cuenta comercial. Al guardarla, pasará a ser el cliente activo para
                    Dashboards, Extracciones, Benchmarking e Informes.
                  </span>
                </div>
              </div>

              <form
                className="extraction-form"
                onSubmit={(event) => {
                  event.preventDefault()
                  void saveClientAccount()
                }}
              >
                <label>
                  Nombre del cliente
                  <input
                    value={draft.client_name}
                    onChange={(event) => setDraft((current) => ({ ...current, client_name: event.target.value }))}
                    placeholder="Ej. Colacao"
                    required
                  />
                </label>

                <div className="extraction-form-grid">
                  <label>
                    Marca principal
                    <input
                      value={draft.primary_brand_name}
                      onChange={(event) => setDraft((current) => ({ ...current, primary_brand_name: event.target.value }))}
                      placeholder="Si la dejas vacía usaremos el nombre del cliente"
                    />
                  </label>

                  <label>
                    Sector
                    <input
                      value={draft.industry}
                      onChange={(event) => setDraft((current) => ({ ...current, industry: event.target.value }))}
                      placeholder="Alimentación, banca, retail..."
                    />
                  </label>
                </div>

                <div className="extraction-form-grid">
                  <label>
                    Web principal
                    <input
                      value={draft.website_url}
                      onChange={(event) => setDraft((current) => ({ ...current, website_url: event.target.value }))}
                      placeholder="https://..."
                    />
                  </label>

                  <label>
                    Color principal
                    <input
                      value={draft.color}
                      onChange={(event) => setDraft((current) => ({ ...current, color: event.target.value }))}
                      placeholder="#7c3aed"
                    />
                  </label>
                </div>

                <div className="builder-side-card muted">
                  <span>Qué dejamos preparado</span>
                  <strong>Cuenta lista para entrar al workspace</strong>
                  <p>
                    Al crear la cuenta damos de alta el cliente y una marca principal inicial. Después podrás
                    entrar directamente a Dashboards, Extracciones y el resto de módulos con ese contexto activo.
                  </p>
                </div>

                <button className="primary-button" disabled={saving}>
                  {saving ? <LoaderCircle className="spin" size={15} /> : <Save size={15} />}
                  Crear cuenta
                </button>
              </form>
            </section>

            <section className="extraction-panel">
              <div className="extraction-panel-heading">
                <div>
                  <strong>Cuentas creadas</strong>
                  <span>
                    Selecciona una cuenta y todo el producto cambiará a ese workspace: dashboards, extracciones,
                    benchmarking e informes.
                  </span>
                </div>
              </div>

              <div className="budget-brand-group">
                <div className="extraction-form-heading">
                  <strong>Cliente activo</strong>
                  <span>{activeClient?.status ?? 'sin seleccionar'}</span>
                </div>
                <div className="budget-brand-row">
                  <div className="budget-brand-row-main">
                    <strong>{activeClient?.name ?? 'Ninguna cuenta activa'}</strong>
                    <span>{activeClient?.industry ?? 'Selecciona o crea una cuenta para empezar'}</span>
                  </div>
                  <small>{activeClient?.timezone ?? '—'}</small>
                </div>
              </div>

              <div className="budget-brand-group">
                <div className="extraction-form-heading">
                  <strong>Listado comercial</strong>
                  <span>{sortedClients.length}</span>
                </div>
                {sortedClients.length === 0 ? (
                  <div className="template-empty-state compact extraction-empty-state budget-empty-state">
                    <Plus size={16} />
                    <strong>Vacío</strong>
                    <span>Crea la primera cuenta comercial desde el formulario.</span>
                  </div>
                ) : (
                  <div className="budget-brand-list">
                    {sortedClients.map((client) => (
                      <button
                        className={`budget-brand-row ${client.id === clientId ? 'is-active' : ''}`}
                        key={client.id}
                        type="button"
                        onClick={() => onSelectClient(client.id)}
                      >
                        <div className="budget-brand-row-main">
                          <strong>{client.name}</strong>
                          <span>{client.industry ?? 'Sin sector definido'}</span>
                        </div>
                        <small>{humanClientStatus(client.status)}</small>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div className="builder-side-card strong">
                <span>Cómo usarlo</span>
                <strong>Crea primero la cuenta, luego opera dentro</strong>
                <p>
                  Esta pantalla es el alta general. Una vez seleccionas un cliente, el resto del producto trabaja
                  sólo sobre ese contexto y deja de mezclarse con otras marcas u oportunidades.
                </p>
              </div>
            </section>
          </div>
        </div>
      </section>
    </main>
  )
}

function BudgetMetric({
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

function humanClientStatus(status: Client['status']): string {
  if (status === 'active') return 'Activa'
  if (status === 'paused') return 'Pausada'
  if (status === 'churned') return 'Churned'
  return 'Onboarding'
}
