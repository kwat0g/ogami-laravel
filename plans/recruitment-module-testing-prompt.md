# Recruitment Module Testing Prompt

## Context

A complete HR Recruitment Module has been built inside the Ogami ERP system (Laravel 11 + React 18 + PostgreSQL 16). The module implements a 12-stage recruitment lifecycle:

**Requisition -> Approval -> Job Posting -> Application -> Screening -> Interview -> Evaluation -> Offer -> Pre-Employment -> Hire**

### What was built (149 files)

**Backend:**
- 12 PostgreSQL tables: `candidates`, `job_requisitions`, `requisition_approvals`, `job_postings`, `applications`, `application_documents`, `interview_schedules`, `interview_evaluations`, `job_offers`, `pre_employment_checklists`, `pre_employment_requirements`, `hirings`
- 13 PHP enums with transition guards (e.g., `RequisitionStatus`, `ApplicationStatus`, `OfferStatus`)
- 12 Eloquent models with `Auditable`, `HasPublicUlid`, `SoftDeletes` traits
- 3 state machines enforcing valid status transitions
- 9 domain services (`RequisitionService`, `ApplicationService`, `InterviewService`, `OfferService`, `PreEmploymentService`, `HiringService`, `RecruitmentDashboardService`, `RecruitmentReportService`, `JobPostingService`)
- 10 controllers, 9 form requests, 9 API resources
- 7 policies with SoD enforcement (requester cannot approve own requisition)
- 13 queued notifications using `::fromModel()` pattern
- 2 scheduled jobs (offer/posting expiry)
- 27 RBAC permissions added to `RolePermissionSeeder`
- All routes under `/api/v1/recruitment/*` with `auth:sanctum` + `module_access:hr`

**Frontend:**
- 16 pages (Dashboard, Requisition CRUD, Posting CRUD, Application list/detail, Interview list/detail with scorecard, Offer list/detail with letter preview, Candidate list/profile, Pipeline report)
- 6 components (StatusBadge, ApplicationTimeline, KpiCards, PipelineFunnel, InterviewScorecardForm, OfferLetterPreview)
- Sidebar navigation under "HR & Payroll > Recruitment"

**Tests:**
- 6 test files with 39 test cases already written in `tests/Feature/Recruitment/`

---

## Task: Test the Recruitment Module

### Step 1: Run Migrations

```bash
php artisan migrate
```

Verify all 12 recruitment tables are created without errors. Check that:
- All CHECK constraints are applied (status enums, salary non-negative, headcount >= 1)
- Foreign keys reference correct tables
- Unique constraints work (application per posting per candidate)

### Step 2: Seed Demo Data

```bash
php artisan db:seed --class=RecruitmentSeeder
```

This should create 10 candidates, 5 requisitions, 10 postings, 30 applications, 15 interviews, 5 offers, and 3 hirings.

### Step 3: Run Existing Tests

```bash
./vendor/bin/pest tests/Feature/Recruitment/
```

All 39 tests should pass. Key things being tested:
- Requisition lifecycle (create, submit, approve, reject, SoD violation)
- Application workflow (apply, duplicate check, shortlist, reject, withdraw)
- Interview management (schedule, evaluate, cancel, no-show, auto-round)
- Offer lifecycle (prepare, send, accept, expire)
- Hiring (creates Employee, closes requisition on headcount)
- API endpoints (auth, CRUD, actions, dashboard, reports)

### Step 4: Manual API Testing

Seed RBAC first if not done:
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Test the full happy path via API:

```bash
# 1. Create requisition
POST /api/v1/recruitment/requisitions
{
  "department_id": 1,
  "position_id": 1,
  "employment_type": "regular",
  "headcount": 1,
  "reason": "Business expansion requires new hire."
}

# 2. Submit for approval
POST /api/v1/recruitment/requisitions/{ulid}/submit

# 3. Approve (must be different user than requester)
POST /api/v1/recruitment/requisitions/{ulid}/approve
{ "remarks": "Approved for hiring." }

# 4. Create job posting
POST /api/v1/recruitment/postings
{
  "job_requisition_id": 1,
  "title": "Software Engineer",
  "description": "We are looking for a skilled software engineer...",
  "requirements": "Bachelor's degree in CS, 3+ years experience"
}

# 5. Publish posting
POST /api/v1/recruitment/postings/{ulid}/publish

# 6. Submit application
POST /api/v1/recruitment/applications
{
  "job_posting_id": 1,
  "candidate": {
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "email": "juan@example.com",
    "source": "referral"
  }
}

# 7. Shortlist
POST /api/v1/recruitment/applications/{ulid}/shortlist

# 8. Schedule interview
POST /api/v1/recruitment/interviews
{
  "application_id": 1,
  "type": "technical",
  "scheduled_at": "2026-04-15T10:00:00Z",
  "duration_minutes": 60,
  "interviewer_id": 1
}

# 9. Submit evaluation
POST /api/v1/recruitment/interviews/{id}/evaluation
{
  "scorecard": [
    {"criterion": "Communication", "score": 5, "comments": "Excellent"},
    {"criterion": "Technical", "score": 4, "comments": "Strong"}
  ],
  "recommendation": "endorse",
  "general_remarks": "Highly recommended."
}

# 10. Create offer
POST /api/v1/recruitment/offers
{
  "application_id": 1,
  "offered_position_id": 1,
  "offered_department_id": 1,
  "offered_salary": 3500000,
  "employment_type": "regular",
  "start_date": "2026-05-01"
}

# 11. Send offer
POST /api/v1/recruitment/offers/{ulid}/send

# 12. Accept offer
POST /api/v1/recruitment/offers/{ulid}/accept

# 13. Init pre-employment
POST /api/v1/recruitment/pre-employment/{application_ulid}/init

# 14. Hire
POST /api/v1/recruitment/hire/{application_ulid}
{ "start_date": "2026-05-01" }

# 15. Check dashboard
GET /api/v1/recruitment/dashboard
```

### Step 5: Verify Business Rules

Test these specific scenarios:

1. **SoD: Self-approval blocked** -- Try approving a requisition you created. Should get 403.
2. **Duplicate application blocked** -- Apply same candidate to same posting twice. Should get 422 with `DUPLICATE_APPLICATION`.
3. **Closed posting rejection** -- Try applying to a closed posting. Should get 422 with `POSTING_NOT_OPEN`.
4. **Invalid transitions blocked** -- Try approving a cancelled requisition. Should throw `InvalidStateTransitionException`.
5. **Offer expiry** -- Create a sent offer with past `expires_at`, run `ExpireOffersJob`, verify status = expired.
6. **Headcount auto-close** -- Set requisition headcount to 1, complete a hire, verify requisition status = closed.
7. **Salary as centavos** -- Verify `offered_salary: 3500000` means PHP 35,000.00 (divide by 100).

### Step 6: Verify Frontend Routes

Navigate to these URLs and verify pages render:
- `/hr/recruitment` -- Dashboard with KPIs, pipeline funnel, source mix
- `/hr/recruitment/requisitions` -- List with filters
- `/hr/recruitment/requisitions/new` -- Create form
- `/hr/recruitment/applications` -- List with status badges
- `/hr/recruitment/applications/{ulid}` -- Detail with timeline + tabs
- `/hr/recruitment/interviews` -- List with calendar toggle
- `/hr/recruitment/offers` -- List with salary in PHP currency
- `/hr/recruitment/candidates` -- Searchable candidate pool
- `/hr/recruitment/reports` -- Pipeline, time-to-fill, source mix

### Key Files to Review

| Area | Path |
|------|------|
| Enums | `app/Domains/HR/Recruitment/Enums/` |
| Models | `app/Domains/HR/Recruitment/Models/` |
| Services | `app/Domains/HR/Recruitment/Services/` |
| Controllers | `app/Http/Controllers/HR/Recruitment/` |
| Policies | `app/Policies/Recruitment/` |
| Migrations | `database/migrations/2026_03_29_*` |
| Routes | `routes/api/v1/recruitment.php` |
| Tests | `tests/Feature/Recruitment/` |
| Frontend Pages | `frontend/src/pages/hr/recruitment/` |
| Hooks | `frontend/src/hooks/useRecruitment.ts` |
| Types | `frontend/src/types/recruitment.ts` |
