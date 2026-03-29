# Recruitment Module Testing Guide

This guide provides a step-by-step walkthrough to verify the stabilized HR Recruitment Module, covering the entire lifecycle from requisition to hiring.

## 0. Prerequisites
Ensure the database is freshly seeded with the latest permission matrix:
```bash
php artisan db:seed --class=ModulePermissionSeeder
```

## 1. Test Accounts
Use these standardized accounts for testing the workflow:

| Role | Email | Password | Responsibility |
|---|---|---|---|
| **Dept Manager** | `prod.manager@ogamierp.local` | `Manager@Test1234!` | Create/Submit Requisitions |
| **Vice President** | `vp@ogamierp.local` | `Vice_president@Test1234!` | Approve Requisitions |
| **HR Manager** | `hr.manager@ogamierp.local` | `Manager@Test1234!` | Execution, Offers, Hiring |

---

## Phase 1: Requisition Creation (Dept Manager)
1. **Login** as `prod.manager@ogamierp.local`.
2. **Navigate** to `HR > Recruitment > Requisitions`.
3. **Click** "Create Requisition".
4. **Fill Details**: Position (e.g., "Production Operator"), Headcount (e.g., 2), Reason (e.g., "Expansion").
5. **Save as Draft**: Verify it appears in the list as `draft`.
6. **Submit**: Click "Submit for Approval". Status should change to `pending_approval`.

## Phase 2: Approval (Vice President)
1. **Login** as `vp@ogamierp.local`.
2. **Navigate** to `HR > Recruitment > Requisitions`.
3. **Open** the pending requisition.
4. **Approve**: Click "Approve". Status should change to `approved`.
   - *Note: You can also test "Reject" and verify it goes back to draft for the manager.*

## Phase 3: Sourcing & Job Posting (HR Manager)
1. **Login** as `hr.manager@ogamierp.local`.
2. **Navigate** to `HR > Recruitment > Requisitions`.
3. **Open** the approved requisition.
4. **Click** "Open Requisition": Status changes to `open`.
5. **Create Posting**: Click "Create Job Posting". Fill in description/requirements and "Publish".
6. **Apply**: (Simulate) Navigate to the Job Board or use the "Internal Application" button to add a candidate.

## Phase 4: Selection & Interviews (HR Manager)
1. **Navigate** to `HR > Recruitment > Applications`.
2. **Shortlist**: Open a `new` application and click "Shortlist" (Status: `shortlisted`).
3. **Interview**: Click "Schedule Interview".
4. **Evaluate**: After the interview, click "Submit Evaluation" and select "Endorse" (Status: `under_review`).

## Phase 5: Offer & Hiring (HR Manager)
1. **Create Offer**: In the Application Detail, go to the "Offer" tab.
2. **Details**: Set Salary, Allowances, and Start Date. Click "Create Offer".
3. **Send & Accept**: Click "Send Offer" then "Accept Offer" (simulating candidate response).
4. **Hiring**: Click the **"Hire Candidate"** button.
   - **Hiring Modal**: Fill in Civil Status, BIR Status, and Actual Start Date.
   - **EXECUTE**: Verify the application status changes to `hired`.

## Phase 6: Verification (HR Manager/Admin)
1. **Employee Record**: Navigate to `HR > Employees`. Search for the new hire.
2. **Employee Code**: Verify the code follows the pattern `EMP-YYYY-NNNNNN`.
3. **Requisition Headcount**: Go back to the Requisition. Verify "Fulfilled" count increased.
   - *If headcount is met, the requisition should automatically change to `closed`.*

---

## Troubleshooting
- **Permission Denied**: Run `php artisan db:seed --class=ModulePermissionSeeder` to sync the latest RBAC matrix.
- **Form Errors**: Check the browser console (F12) for validation messages from the Laravel backend.
