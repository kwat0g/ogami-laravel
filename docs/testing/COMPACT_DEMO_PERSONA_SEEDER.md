# Compact Demo Persona Seeder (No Superadmin)

## Why this exists

The default seed creates many accounts across departments and hierarchy levels, which is realistic but heavy for live demos.

This seeder creates a **small persona pack** so you can run full chain demos with fewer account switches and without using `superadmin`.

## Command

```bash
php artisan db:seed --class=CompactDemoPersonaSeeder
```

## Demo Accounts

| Persona | Email | Password | Role | Primary Department |
|---|---|---|---|---|
| Client Buyer | demo.client@ogami.test | DemoPortal@1234! | client | N/A |
| Sales Coordinator | demo.sales@ogamierp.local | DemoSales@1234! | manager | SALES |
| Procurement Requester | demo.requester@ogamierp.local | DemoReq@1234! | officer | PURCH |
| Operations Executor | demo.ops@ogamierp.local | DemoOps@1234! | head | PROD |
| Finance Officer | demo.finance@ogamierp.local | DemoFin@1234! | manager | ACCTG |
| VP Approver | demo.approver@ogamierp.local | DemoVP@1234! | vice_president | EXEC |

## Suggested Baton Usage

1. `demo.client@ogami.test` creates demand.
2. `demo.sales@ogamierp.local` reviews/confirms sales side.
3. `demo.requester@ogamierp.local` creates PR/PO requests.
4. `demo.ops@ogamierp.local` handles production/warehouse/qc execution steps.
5. `demo.finance@ogamierp.local` handles AP/AR finance steps.
6. `demo.approver@ogamierp.local` performs approval gates.

## Notes

- This does **not** remove existing seeded accounts; it adds/updates a compact set.
- This is intended for demo/testing convenience, not production auth design.
- If your chain enforces strict SoD on a specific step, keep requester and approver personas separate.
