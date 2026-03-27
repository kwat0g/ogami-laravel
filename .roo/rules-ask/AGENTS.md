# Ask Mode Rules (Non-Obvious Only)

- 20 domain modules live under `app/Domains/<Domain>/` — not standard Laravel `app/Models/` layout.
- `app/Shared/Contracts/` contains ONLY interfaces (`ServiceContract`, `BusinessRule`) — ARCH-006 enforces this.
- `VendorFulfillmentService` is in AP domain (`app/Domains/AP/Services/`), not Procurement — AP owns vendor-side fulfillment.
- Payroll pipeline is 17 steps (`Step01`–`Step17`) in `app/Domains/Payroll/Pipeline/` — each step is an invokable class receiving `PayrollComputationContext`.
- Routes are split into one file per domain under `routes/api/v1/` (28 files).
- Frontend hooks in `frontend/src/hooks/use<Domain>.ts` wrap TanStack Query — one file per domain.
- Zod schemas live in `frontend/src/schemas/<domain>.ts` (17 of 20 domains have them).
- `CLAUDE.md` has the seeder dependency chain (7 tiers, strict order) and PO status sequence.
- `.github/instructions/` has Copilot rules per concern: backend, frontend, migrations, tests, fixed-assets.
- `.agents/skills/` has specialized skills: payroll-debugger, migration-writer, code-reviewer, budget-planner.
- Fixed assets frontend uses `under_maintenance` status; DB CHECK uses `impaired` — DB is authoritative.
