# Architect Mode Rules (Non-Obvious Only)

- All domain services MUST be `final class` implementing `ServiceContract` (ARCH-002) — no abstract service classes.
- Value objects MUST be `final readonly class` (ARCH-004) — `Money`, `Minutes`, `PayPeriod`, `DateRange`, `EmployeeCode`, `WorkingDays`, `OvertimeMultiplier`.
- Controllers have ZERO business logic, ZERO `DB::` calls (ARCH-001) — only `authorize()` then delegate to service.
- `Shared\Contracts` may contain ONLY interfaces (ARCH-006) — no abstract classes, no traits.
- SoD constraint: creator cannot approve their own record — enforced in policies via `$user->id !== $record->created_by_id`.
- Department scoping is auto-applied via middleware; only 4 roles bypass: `admin`, `super_admin`, `executive`, `vice_president`. Managers and heads do NOT bypass.
- Session-cookie auth (Sanctum) only — no JWT, no localStorage tokens. Frontend uses `withCredentials: true`.
- Money is always integer centavos in DB (`unsignedBigInteger`) and `Money` VO in PHP — never `float`, `decimal`, or raw arithmetic.
- Payroll pipeline (17 steps) uses Laravel Pipeline pattern — steps only mutate `PayrollComputationContext`, never query DB directly.
- Payroll run has 14 states with specific transition rules — see `CLAUDE.md` for the full state machine.
- Stock changes MUST go through `StockService::receive()` for audit trail (`stock_ledger_entries`).
- Only 2 Zustand stores allowed (`authStore`, `uiStore`) — all server state must use TanStack Query.
