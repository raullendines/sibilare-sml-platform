# API

Typed API client and endpoint wrappers. The frontend talks to Laravel only.

Configure the backend URL with:

```dotenv
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

Create the client with a token provider backed by the current Supabase
session:

```ts
import { SmlApiClient } from './client.ts'

const api = new SmlApiClient({
  getAccessToken: async () => supabase.auth
    .getSession()
    .then(({ data }) => data.session?.access_token ?? null),
})

const { data: clients } = await api.listClients()
```

The browser never receives Laravel service credentials and never calls Apify,
LLM providers, storage signing endpoints, or PostgreSQL directly.
