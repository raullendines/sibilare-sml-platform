import { lazy, Suspense, useEffect, useMemo, useState } from 'react'
import type { Session } from '@supabase/supabase-js'
import { ArrowRight, LoaderCircle, Radio } from 'lucide-react'
import { SmlApiClient } from './api/client.ts'
import { hasSupabaseConfig, supabase } from './lib/supabase.ts'
import './App.css'

const DashboardWorkspace = lazy(() =>
  import('./features/dashboard/DashboardWorkspace.tsx').then((module) => ({
    default: module.DashboardWorkspace,
  })),
)

function App() {
  const [session, setSession] = useState<Session | null>(null)
  const [authReady, setAuthReady] = useState(false)

  useEffect(() => {
    if (!supabase) {
      setAuthReady(true)
      return
    }

    void supabase.auth.getSession().then(({ data }) => {
      setSession(data.session)
      setAuthReady(true)
    })

    const { data } = supabase.auth.onAuthStateChange((_event, nextSession) => {
      setSession(nextSession)
      setAuthReady(true)
    })

    return () => data.subscription.unsubscribe()
  }, [])

  const api = useMemo(
    () =>
      new SmlApiClient({
        getAccessToken: () => session?.access_token ?? null,
      }),
    [session?.access_token],
  )

  if (!authReady) {
    return (
      <main className="centered-state">
        <LoaderCircle className="spin" size={20} />
        <span>Abriendo Sibilare</span>
      </main>
    )
  }

  if (!hasSupabaseConfig) {
    return <ConfigurationScreen />
  }

  if (!session) {
    return <SignInScreen />
  }

  return (
    <Suspense
      fallback={
        <main className="centered-state">
          <LoaderCircle className="spin" size={20} />
          <span>Cargando editor</span>
        </main>
      }
    >
      <DashboardWorkspace
        api={api}
        userEmail={session.user.email ?? 'Usuario'}
        onSignOut={() => supabase?.auth.signOut()}
      />
    </Suspense>
  )
}

function ConfigurationScreen() {
  return (
    <main className="auth-page">
      <section className="auth-panel">
        <div className="brand-lockup">
          <span className="brand-mark"><Radio size={15} /></span>
          <strong>Sibilare</strong>
        </div>
        <p className="auth-kicker">Configuracion local</p>
        <h1>Conecta Sibilare con su proyecto Supabase.</h1>
        <p>
          Define <code>VITE_SUPABASE_URL</code> y{' '}
          <code>VITE_SUPABASE_PUBLISHABLE_KEY</code> en{' '}
          <code>frontend/.env</code>. Usa el mismo proyecto en Laravel: la
          identidad es unica para todo el flujo y Laravel valida la sesion y
          los permisos de cada cliente.
        </p>
      </section>
    </main>
  )
}

function SignInScreen() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function signIn(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!supabase) return

    setLoading(true)
    setError(null)
    const { error: signInError } = await supabase.auth.signInWithPassword({
      email,
      password,
    })
    setLoading(false)

    if (signInError) setError(signInError.message)
  }

  return (
    <main className="auth-page">
      <form className="auth-panel" onSubmit={signIn}>
        <div className="brand-lockup">
          <span className="brand-mark"><Radio size={15} /></span>
          <strong>Sibilare</strong>
        </div>
        <p className="auth-kicker">Social intelligence workspace</p>
        <h1>Vuelve a tu espacio de escucha.</h1>
        <label>
          Email
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
            required
          />
        </label>
        <label>
          Contrasena
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="current-password"
            required
          />
        </label>
        {error ? <p className="form-error">{error}</p> : null}
        <button className="primary-button auth-submit" disabled={loading}>
          {loading ? <LoaderCircle className="spin" size={16} /> : null}
          Entrar
          {!loading ? <ArrowRight size={16} /> : null}
        </button>
      </form>
    </main>
  )
}

export default App
