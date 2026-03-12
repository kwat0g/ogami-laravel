---
description: "Use when writing or editing React components, hooks, pages, stores, or Zod schemas in the frontend. Covers Ogami ERP frontend architecture, routing, auth, and API patterns."
applyTo: "frontend/src/**"
---
# Ogami ERP — Frontend Development Guidelines

## Imports & Aliases

- Always use `@/` path alias for imports from `src/` (e.g., `import api from '@/lib/api'`).
- `api.ts` is a **default export**: `import api from '@/lib/api'` — not `import { api }`.
- No JWT or tokens in localStorage. Auth lives in the session cookie only; `api.ts` sets `withCredentials: true` automatically.

## Router

All routes are lazy-loaded in a single file: `frontend/src/router/index.tsx`. Do not create additional router files. Every protected route wraps its element in the local `RequirePermission` guard.

## State Management

- **Only 2 Zustand stores**: `authStore.ts` (auth + RBAC) and `uiStore.ts` (UI state). Do not create new global stores unless absolutely necessary.
- Server state lives in TanStack Query hooks under `frontend/src/hooks/` — one file per domain.
- One context exists: `PayrollWizardContext.tsx`. Use React contexts only for component-tree-scoped wizard/multi-step form state.

## API & TanStack Query

```typescript
// queryKey always includes the filters object
export function useLeaveRequests(filters: LeaveFilters = {}) {
  return useQuery({ queryKey: ['leave-requests', filters], queryFn: () => api.get(...) })
}

// Invalidate on mutation success
export function useSubmitLeaveRequest() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: ..., onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] }) })
}
```

**Global QueryClient defaults**: `staleTime: 30_000`, `refetchOnWindowFocus: false`. API errors with `error_code` in the response never retry.

**Write cooldown**: `api.ts` silently aborts duplicate POST/PUT/PATCH/DELETE calls to the same URL within 1500 ms. Do not fire the same mutation twice in quick succession.

## Types & Schemas

- Paginated responses: `{ data: T[], meta: { current_page, last_page, per_page, total } }` — use `.meta`, not `.pagination`.
- URL params use **ULID** strings: `useParams<{ ulid: string }>()`.
- Use `z.coerce.number()` for all numeric IDs and monetary inputs in Zod schemas.
- Derive TS types: `type Foo = z.infer<typeof fooSchema>`.
- Zod schemas live in `frontend/src/schemas/`. Only 9 domains have schema files; others use inline Zod or plain TS types.

## Permissions & SoD

- `authStore.hasPermission()` is **strict** — `admin` only holds `system.*` permissions and does NOT implicitly have HR/payroll/etc. permissions.
- Department access bypass: `admin`, `super_admin`, `executive`, `vice_president` only. `manager` and `head` require explicit department pivot.
- SoD via `useSodCheck(createdById)` — same user who created cannot approve. `manager` **can** be blocked; only `admin`/`super_admin` bypass.

## File Placement

| Type | Location |
|------|----------|
| Page components | `frontend/src/pages/<domain>/` |
| TanStack Query hooks | `frontend/src/hooks/use<Domain>.ts` |
| Zod schemas | `frontend/src/schemas/<domain>.ts` |
| TypeScript types | `frontend/src/types/<domain>.ts` |
| AP/Vendor pages | `frontend/src/pages/accounting/` (not `pages/ap/`) |

## ESLint Rules to Watch

- `@typescript-eslint/no-unused-vars` is **error** — prefix intentionally unused vars/args with `_`.
- `react-refresh/only-export-components` is **warn** — helper functions co-located with components need `// eslint-disable-next-line react-refresh/only-export-components`.
- All components use `function` declarations with explicit return types.
