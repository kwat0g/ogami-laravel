# Frontend Module Suite Summary

Run ID: 20260401-231223

| Module | Result | Specs |
|---|---|---|
| auth | PASS | e2e/specs/01-auth.spec.ts |
| hr | FAIL | e2e/specs/80-hr.spec.ts |
| payroll | FAIL | e2e/specs/90-payroll.spec.ts |
| accounting | FAIL | e2e/specs/50-accounting.spec.ts |
| procurement | FAIL | e2e/specs/40-procurement.spec.ts |
| inventory | FAIL | e2e/specs/20-inventory.spec.ts |
| production | FAIL | e2e/specs/30-production.spec.ts |
| qc | FAIL | e2e/specs/70-qc.spec.ts |
| mold | FAIL | e2e/specs/100-mold.spec.ts |
| crm | FAIL | e2e/specs/110-crm.spec.ts |
| delivery | FAIL | e2e/specs/60-delivery.spec.ts |
| rbac | PASS | e2e/specs/10-rbac.spec.ts |

Passed modules: 2
Failed modules: 10

## Retry Failed Modules

bash e2e/run-module-suite.sh --retry-failed
