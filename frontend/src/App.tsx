import './App.css'

const modules = [
  'Client dashboards',
  'Benchmarking',
  'Apify ingestion',
  'AI classification',
  'Reports and exports',
  'Chatbot',
]

function App() {
  return (
    <main className="shell">
      <section className="hero">
        <p className="eyebrow">Sibilare SML Platform</p>
        <h1>Managed Social Media Listening for client-specific intelligence.</h1>
        <p className="lede">
          Laravel owns the operational backend. React owns the client experience.
          PostgreSQL keeps the source of truth. Workers handle Apify, AI, reports
          and cost-bearing tasks.
        </p>
      </section>

      <section className="grid" aria-label="Platform modules">
        {modules.map((module) => (
          <article className="module-card" key={module}>
            <span className="module-mark" />
            <h2>{module}</h2>
            <p>
              Built as a capability that can be configured per client instead of
              a hard-coded one-off feature.
            </p>
          </article>
        ))}
      </section>
    </main>
  )
}

export default App

