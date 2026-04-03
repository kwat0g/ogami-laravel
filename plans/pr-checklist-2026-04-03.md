# PR Checklist (2026-04-03)

## Feature commits prepared
- bfa40b23 - fix(production): guard actual cost labor query and add regression test
- 67e0c891 - feat(recruitment): add VP hiring approval and interviewer department assignment
- 45e4535f - test(production): cover unrelated maintenance rows in actual cost fallback

## Migration state check
- 2026_04_03_120000_expand_production_order_source_types: Ran
- 2026_04_03_120100_add_replenishment_and_release_approval_fields: Ran
- 2026_04_03_170000_add_recruitment_vp_hiring_and_interviewer_department: Pending

## Final targeted verification
- Production material consumption: PASS
- Procurement MRQ to PR conversion: PASS
- CRM force production and auto-creation chain: PASS
- CRM business rules: PASS
- Recruitment hiring service: PASS
- Production actual-cost regression tests: PASS
- Aggregate: 36 tests passed, 130 assertions

## Notes
- PHPStan now runs end-to-end but reports existing repo-wide issues outside this feature scope.
- Pint check reports existing repo-wide style issues outside this feature scope.

## Recommended release action
1. Push current branch with the three feature commits.
2. Open PR scoped to these commits only.
3. Keep static/style debt remediation in separate follow-up tickets.
