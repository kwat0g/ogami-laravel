# Recruitment Role Based Test Guide

Verified on 2026-04-04 against:
- routes/api/v1/recruitment.php
- app/Policies/Recruitment/*.php
- app/Http/Controllers/HR/Recruitment/*.php
- app/Domains/HR/Recruitment/Enums/*.php
- database/seeders/RolePermissionSeeder.php
- database/seeders/ModulePermissionSeeder.php
- storage/app/test-credentials.md

This guide is for manual role-based QA of the Recruitment module.

## 1. Pre Test Setup

Run these commands first so your role matrix matches the current code:

```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=ModulePermissionSeeder
php artisan cache:clear
php artisan permission:cache-reset
```

Optional sanity check:

```bash
./vendor/bin/pest tests/Feature/Recruitment/RecruitmentApiTest.php
```

## 2. Test Accounts

Use these accounts from storage/app/test-credentials.md:

| Persona | Email | Password |
|---|---|---|
| HR Manager | demo.hr@ogamierp.local | DemoHr@1234! |
| HR Head | demo.hr.head@ogamierp.local | DemoHrHead@1234! |
| HR Staff | demo.hr.staff@ogamierp.local | DemoHrStaff@1234! |
| VP Approver | demo.approver@ogamierp.local | DemoVP@1234! |
| Super Admin (for hiring final approve endpoint) | superadmin@ogamierp.local | SuperAdmin@12345! |

## 3. Current Effective Permission Matrix

Observed after setup commands above.

| Capability | HR Manager | HR Head | HR Staff | VP Approver |
|---|---:|---:|---:|---:|
| View requisitions | Yes | Yes | No | Yes |
| Create requisitions | Yes | Yes | No | No |
| Submit requisitions | Yes | Yes | No | No |
| Approve requisitions | Yes | No | No | Yes |
| Create/publish postings | Yes | Yes | No | No |
| Review applications | Yes | Yes | No | No |
| Schedule/evaluate interviews | Yes | Yes | No | No |
| Create/send offers | Yes | No | No | Send only |
| Pre-employment view/verify | Yes | No | No | No |
| Execute hiring submit | Yes | No | No | No |
| Approve hiring (vp-approve route) | No | No | No | No |
| View recruitment reports | Yes | No | No | Yes |

Important behavior:
- HR Staff is self-service only and should be denied on all recruitment endpoints.
- The hiring vp-approve and vp-reject endpoints currently require recruitment.hiring.approve, which is not present on demo.approver. Use superadmin for positive-path testing of those endpoints.

## 4. Route Level Access Smoke Tests

Login URL:
- http://localhost:5173/login

Smoke checks:
1. Login as HR Staff and open /hr/recruitment.
2. Expect no functional recruitment access (blocked UI and/or 403 API responses).
3. Login as HR Head and open /hr/recruitment.
4. Expect list and operational tabs visible, but no approve actions.
5. Login as HR Manager and open /hr/recruitment.
6. Expect full operational access.

## 5. Requisition Workflow Tests

### 5.1 Happy Path (Manager)
1. Login as HR Manager.
2. Create requisition: POST /api/v1/recruitment/requisitions
3. Submit requisition: POST /api/v1/recruitment/requisitions/{ulid}/submit
4. Approve requisition: POST /api/v1/recruitment/requisitions/{ulid}/approve
5. Open requisition: POST /api/v1/recruitment/requisitions/{ulid}/open

Expected:
- draft -> pending_approval -> approved -> open

### 5.2 Head Cannot Approve
1. Login as HR Head.
2. Attempt approve: POST /api/v1/recruitment/requisitions/{ulid}/approve

Expected:
- 403 Forbidden

### 5.3 VP Can Approve
1. Login as VP Approver.
2. Approve a pending requisition.

Expected:
- 200 OK and status approved

### 5.4 SoD Note (Current Policy)
Current policy has an HR manager exception: HR manager may approve/reject own requisition.
Do not expect self-approval denial for HR manager in this module.

## 6. Posting And Application Workflow Tests

### 6.1 HR Head Operational Path
1. Login as HR Head.
2. Create posting: POST /api/v1/recruitment/postings
3. Publish posting: POST /api/v1/recruitment/postings/{ulid}/publish
4. Review application: POST /api/v1/recruitment/applications/{ulid}/review
5. Shortlist application: POST /api/v1/recruitment/applications/{ulid}/shortlist

Expected:
- All requests succeed for head operational actions.

### 6.2 HR Staff Hard Deny
1. Login as HR Staff.
2. Attempt each endpoint above.

Expected:
- 403 Forbidden for each recruitment operation.

## 7. Interview Workflow Tests

1. Login as HR Head.
2. Schedule interview: POST /api/v1/recruitment/interviews
3. Submit evaluation: POST /api/v1/recruitment/interviews/{id}/evaluation

Expected:
- Head can schedule and evaluate.

Negative check:
1. Login as HR Staff.
2. Attempt schedule or evaluation.

Expected:
- 403 Forbidden.

## 8. Offer And Pre Employment Tests

### 8.1 Manager Offer + Pre Employment
1. Login as HR Manager.
2. Create offer: POST /api/v1/recruitment/offers
3. Send offer: POST /api/v1/recruitment/offers/{ulid}/send
4. Accept offer: POST /api/v1/recruitment/offers/{ulid}/accept
5. Init pre-employment: POST /api/v1/recruitment/pre-employment/{application_ulid}/init
6. Verify requirement: POST /api/v1/recruitment/pre-employment/requirements/{ulid}/verify

Expected:
- Manager can complete this path.

### 8.2 VP Offer Send
1. Login as VP Approver.
2. Open an existing draft/sent offer.
3. Attempt send endpoint.

Expected:
- Allowed (VP currently has recruitment.offers.send).

## 9. Hiring Workflow Tests

### 9.1 Submit For Approval (Manager)
1. Login as HR Manager.
2. Submit hiring: POST /api/v1/recruitment/hire/{application_ulid}

Expected:
- 201 Created, hiring status pending_vp_approval.

### 9.2 VP Approve Route (Current State)
1. Login as VP Approver.
2. Attempt: POST /api/v1/recruitment/hirings/{ulid}/vp-approve

Expected:
- 403 Forbidden (demo.approver currently lacks recruitment.hiring.approve).

### 9.3 Superadmin Positive Path
1. Login as Super Admin.
2. Approve hiring: POST /api/v1/recruitment/hirings/{ulid}/vp-approve

Expected:
- 200 OK, status becomes hired.

## 10. Reports And Candidates

### 10.1 Manager Access
- GET /api/v1/recruitment/dashboard
- GET /api/v1/recruitment/reports/pipeline
- GET /api/v1/recruitment/candidates

Expected:
- 200 OK

### 10.2 VP Access
- Reports should be accessible.
- Candidate management should be denied unless additional permissions are granted.

### 10.3 Staff Access
- Dashboard/reports/candidates should return 403.

## 11. Status Transition Checks

Use these transitions as pass criteria while executing steps:

- Requisition: draft -> pending_approval -> approved -> open -> on_hold/closed
- Posting: draft -> published -> closed/expired
- Application: new -> under_review -> shortlisted -> hired/rejected/withdrawn
- Interview: scheduled -> in_progress -> completed (or cancelled/no_show)
- Offer: draft -> sent -> accepted/rejected/expired/withdrawn
- Hiring: pending -> pending_vp_approval -> hired (or rejected_by_vp)

## 12. Pass Fail Checklist

Pass when all are true:
- HR Staff cannot execute any recruitment write or read endpoints.
- HR Head can run operational flow but cannot approve requisitions.
- HR Manager can complete full operational flow including hire submission.
- VP can approve requisitions and view reports.
- Hiring final approval route behavior matches current RBAC state (403 for demo.approver, success for superadmin).

Fail when any role can perform actions outside the matrix above.
