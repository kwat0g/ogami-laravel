# Legacy E2E Specs Index

This file tracks legacy E2E specs that are preserved for reference but are not part of the canonical module suite executed by `e2e/run-module-suite.sh`.

## Canonical Source of Truth

- Canonical module specs live in `frontend/e2e/specs/`.
- Canonical module execution is defined only in `frontend/e2e/run-module-suite.sh`.
- If there is any mismatch between legacy and canonical files, canonical files are authoritative.

## Legacy Specs (Not in Module Runner Mapping)

- `frontend/e2e/accounting.spec.ts`
- `frontend/e2e/ap-workflow.spec.ts`
- `frontend/e2e/auth.spec.ts`
- `frontend/e2e/crm-role.spec.ts`
- `frontend/e2e/dashboard-routing.spec.ts`
- `frontend/e2e/hr-onboarding.spec.ts`
- `frontend/e2e/inventory-workflow.spec.ts`
- `frontend/e2e/leave.spec.ts`
- `frontend/e2e/mold-role.spec.ts`
- `frontend/e2e/payroll.spec.ts`
- `frontend/e2e/procurement.spec.ts`
- `frontend/e2e/production-workflow.spec.ts`
- `frontend/e2e/qc-workflow.spec.ts`
- `frontend/e2e/role-navigation.spec.ts`
- `frontend/e2e/rbac-backend-frontend-sync.spec.ts`
- `frontend/e2e/rbac-complete.spec.ts`
- `frontend/e2e/rbac-comprehensive-ui.spec.ts`
- `frontend/e2e/rbac-interactions.spec.ts`
- `frontend/e2e/rbac-v2-critical.spec.ts`
- `frontend/e2e/sod.spec.ts`

## Archived Legacy Specs

These files were moved to `frontend/e2e/legacy/` because they were not referenced by active module runners:

- `frontend/e2e/legacy/workflows-comprehensive.spec.ts`
- `frontend/e2e/legacy/module-detailed.spec.ts`
- `frontend/e2e/legacy/comprehensive_workflow.spec.ts`
- `frontend/e2e/legacy/advanced-scenarios.spec.ts`
- `frontend/e2e/legacy/api-integration.spec.ts`
- `frontend/e2e/legacy/blank.spec.ts`
- `frontend/e2e/legacy/smoke.spec.ts`
- `frontend/e2e/legacy/smoke_manual.spec.ts`

## Retirement Rule

Delete a legacy file only after:

1. Its intended coverage exists in a canonical numbered spec under `frontend/e2e/specs/`.
2. The mapped module passes on Lightpanda in `run-module-suite.sh`.
3. Full module sweep remains green after removal.
