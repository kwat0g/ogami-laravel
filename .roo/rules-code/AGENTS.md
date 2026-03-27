# Code Mode Rules (Non-Obvious Only)

- `Money::fromCentavos()` throws on negative — always guard with `max(0, $val)` or conditional before subtracting.
- `DomainException` has 4 args: `message`, `errorCode`, `httpStatus`, `context[]` — missing any causes runtime error.
- Generated columns (`daily_rate`, `hourly_rate`) are PostgreSQL `STORED GENERATED` — never put in `$fillable`, factories, or seeders.
- `HasPublicUlid` trait silently breaks if model doesn't also use `SoftDeletes`.
- DB enums: always `string` column + `DB::statement()` CHECK constraint — `$table->enum()` fails on PG.
- `StockService::receive()` is the only valid way to update stock — direct `StockBalance` increment bypasses `stock_ledger_entries` audit trail.
- `VendorFulfillmentService` is in `app/Domains/AP/Services/` (not Procurement) — AP owns vendor fulfillment.
- Queued notifications: `::fromModel()` static factory only — constructing with Eloquent model causes `ModelNotFoundException` if soft-deleted before job runs.
- PHPStan: columns with `'date'` cast must be typed `string` in PHPDocs. `(string) $model->date_col` not `->toDateString()`.
- `asset_code` on fixed assets is set by a PG trigger — setting it in PHP or factories silently gets overwritten.
- Frontend: only 2 Zustand stores (`authStore`, `uiStore`). All server state goes in TanStack Query hooks.
- `api.ts` write cooldown: duplicate POST/PUT/PATCH/DELETE to same URL within 1500ms silently aborted via `AbortController`.
- `authStore.hasPermission()` is strict: `admin` role only has `system.*` — no implicit HR/payroll/accounting permissions.
- Vendor propose-changes `items` validation: `['present','array']` not `['required','array','min:1']` — empty array is valid for PO-level-only changes.
