# Recruitment Module — Gap Fixes & Frontend Alignment

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all identified critical/high/medium gaps in the recruitment module: DB constraint bug, missing authorization, missing routes, HR Manager SoD bypass, TypeScript/Zod mismatches, missing frontend buttons, and VP approval UI.

**Architecture:** All backend fixes are surgical patches to existing files (no new domain structure). Frontend fixes align Zod schemas with backend enums, add missing UI buttons, and add VP approval hooks + UI to the ApplicationDetailPage. No new pages are needed — VP approval UI is added inline on the existing detail page.

**Tech Stack:** Laravel 11 / PHP 8.2 / PostgreSQL, React 18 / TypeScript / TanStack Query / Zod, Pest PHP tests.

---

## Task 1: Fix Critical DB Constraint — Add `hired` to `applications.status` CHECK

**Files:**
- Create: `database/migrations/2026_04_04_000001_fix_applications_status_check_add_hired.php`

The PostgreSQL CHECK constraint on `applications.status` was missing the `'hired'` value. `HiringService::vpApprove()` sets `ApplicationStatus::Hired` and saves — this throws a DB constraint violation at runtime.

- [ ] **Step 1: Create the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS chk_app_status');
        DB::statement(
            "ALTER TABLE applications ADD CONSTRAINT chk_app_status "
            . "CHECK (status IN ('new','under_review','shortlisted','hired','rejected','withdrawn'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS chk_app_status');
        DB::statement(
            "ALTER TABLE applications ADD CONSTRAINT chk_app_status "
            . "CHECK (status IN ('new','under_review','shortlisted','rejected','withdrawn'))"
        );
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_04_04_000001_fix_applications_status_check_add_hired` then `Migrated`.

- [ ] **Step 3: Verify constraint**

```bash
php artisan tinker --execute="DB::select(\"SELECT constraint_name, check_clause FROM information_schema.check_constraints WHERE constraint_name = 'chk_app_status'\")"
```

Expected: `check_clause` contains `'hired'`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_04_000001_fix_applications_status_check_add_hired.php
git commit -m "fix(recruitment): add 'hired' to applications status CHECK constraint"
```

---

## Task 2: Fix HiringController — Missing `use Illuminate\Http\Request` Import

**Files:**
- Modify: `app/Http/Controllers/HR/Recruitment/HiringController.php`

`vpApprove(Request $request, ...)` and `vpReject(Request $request, ...)` reference `Request` but it is not imported. PHP will throw a fatal error.

- [ ] **Step 1: Add the import**

In `app/Http/Controllers/HR/Recruitment/HiringController.php`, change:

```php
use App\Http\Requests\HR\Recruitment\HireRequest;
use Illuminate\Http\JsonResponse;
```

to:

```php
use App\Http\Requests\HR\Recruitment\HireRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
```

- [ ] **Step 2: Verify no syntax errors**

```bash
php artisan route:list --path=recruitment/hirings 2>&1 | head -10
```

Expected: routes listed without errors.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/HR/Recruitment/HiringController.php
git commit -m "fix(recruitment): add missing Request import in HiringController"
```

---

## Task 3: Fix OfferController — Add Authorization to `store()`

**Files:**
- Modify: `app/Http/Controllers/HR/Recruitment/OfferController.php`

`store()` has no `authorize()` call — any HR module user can create offers.

- [ ] **Step 1: Add authorization**

In `app/Http/Controllers/HR/Recruitment/OfferController.php`, change `store()`:

```php
public function store(PrepareOfferRequest $request): JsonResponse
{
    $this->authorize('create', JobOffer::class);

    $application = Application::findOrFail($request->validated('application_id'));
    $offer = $this->service->prepareOffer($application, $request->validated(), $request->user());

    return (new JobOfferResource($offer->load(['offeredPosition', 'offeredDepartment', 'preparer'])))
        ->response()
        ->setStatusCode(201);
}
```

- [ ] **Step 2: Verify no syntax errors**

```bash
php -l app/Http/Controllers/HR/Recruitment/OfferController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/HR/Recruitment/OfferController.php
git commit -m "fix(recruitment): add missing authorization in OfferController::store"
```

---

## Task 4: Fix InterviewController — Add Authorization to `submitEvaluation()`

**Files:**
- Modify: `app/Http/Controllers/HR/Recruitment/InterviewController.php`

`submitEvaluation()` has no `authorize()` call — any HR module user can submit evaluations for any interview.

- [ ] **Step 1: Add authorization**

In `app/Http/Controllers/HR/Recruitment/InterviewController.php`, change `submitEvaluation()`:

```php
public function submitEvaluation(SubmitEvaluationRequest $request, InterviewSchedule $interview): JsonResponse
{
    $this->authorize('evaluate', $interview);

    $evaluation = $this->service->submitEvaluation($interview, $request->validated(), $request->user());

    return response()->json($evaluation, 201);
}
```

- [ ] **Step 2: Verify**

```bash
php -l app/Http/Controllers/HR/Recruitment/InterviewController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/HR/Recruitment/InterviewController.php
git commit -m "fix(recruitment): add missing authorization in InterviewController::submitEvaluation"
```

---

## Task 5: Add `open` Route + Controller Method for Requisitions

**Files:**
- Modify: `routes/api/v1/recruitment.php`
- Modify: `app/Http/Controllers/HR/Recruitment/RequisitionController.php`
- Modify: `app/Policies/Recruitment/RequisitionPolicy.php`

After a requisition is `approved`, there is no HTTP route to transition it to `open`. `RequisitionService::open()` exists but is unreachable. The `approved → open` transition is needed before postings can be properly started.

- [ ] **Step 1: Add `open` policy method**

In `app/Policies/Recruitment/RequisitionPolicy.php`, add after the `cancel()` method:

```php
public function open(User $user, JobRequisition $requisition): bool
{
    return $user->hasPermissionTo('recruitment.requisitions.approve');
}
```

- [ ] **Step 2: Add `open` controller method**

In `app/Http/Controllers/HR/Recruitment/RequisitionController.php`, add after `resume()`:

```php
public function open(Request $request, JobRequisition $requisition): RequisitionResource
{
    $this->authorize('open', $requisition);

    $requisition = $this->service->open($requisition, $request->user());

    return new RequisitionResource($requisition);
}
```

- [ ] **Step 3: Add route**

In `routes/api/v1/recruitment.php`, after the `resume` route add:

```php
Route::post('requisitions/{requisition}/open', [RequisitionController::class, 'open'])
    ->name('requisitions.open');
```

- [ ] **Step 4: Verify routes**

```bash
php artisan route:list --path=recruitment/requisitions 2>&1 | grep open
```

Expected: `POST recruitment/requisitions/{requisition}/open` listed.

- [ ] **Step 5: Commit**

```bash
git add routes/api/v1/recruitment.php \
        app/Http/Controllers/HR/Recruitment/RequisitionController.php \
        app/Policies/Recruitment/RequisitionPolicy.php
git commit -m "feat(recruitment): add POST /requisitions/{requisition}/open route"
```

---

## Task 6: HR Manager SoD Bypass — Can Approve Own Requisition

**Files:**
- Modify: `app/Policies/Recruitment/RequisitionPolicy.php`
- Modify: `app/Domains/HR/Recruitment/Services/RequisitionService.php`

Business rule: HR Managers (role `manager` in the HR department) are organizational authorities over all headcount. They should be able to approve requisitions they themselves created. This is an explicit exception to the standard SoD rule.

The check uses `$user->employee?->department?->code === 'HR'` — consistent with how `RequisitionService::submit()` already identifies HR managers for notifications.

- [ ] **Step 1: Update `RequisitionPolicy::approve()`**

In `app/Policies/Recruitment/RequisitionPolicy.php`, replace the `approve()` method:

```php
public function approve(User $user, JobRequisition $requisition): bool
{
    if (! $user->hasPermissionTo('recruitment.requisitions.approve')) {
        return false;
    }

    // HR Manager exception: may approve their own requisitions.
    // HR department managers are organizational headcount authorities.
    if ($user->hasRole('manager') && $user->employee?->department?->code === 'HR') {
        return true;
    }

    // Standard SoD: cannot approve own requisition
    return $user->id !== $requisition->requested_by;
}
```

- [ ] **Step 2: Update `RequisitionService::approve()` service-layer SoD guard**

In `app/Domains/HR/Recruitment/Services/RequisitionService.php`, replace the SoD block inside `approve()` (lines 114–122):

```php
// SoD exception: HR Managers may approve their own requisitions
$isHrManager = $actor->hasRole('manager')
    && $actor->employee?->department?->code === 'HR';

if (! $isHrManager && $actor->id === $requisition->requested_by) {
    throw new DomainException(
        'You cannot approve your own requisition.',
        'SOD_SELF_APPROVAL',
        403,
        ['user_id' => $actor->id, 'requested_by' => $requisition->requested_by],
    );
}
```

- [ ] **Step 3: Update `RequisitionPolicy::reject()` to match**

In `app/Policies/Recruitment/RequisitionPolicy.php`, replace the `reject()` method:

```php
public function reject(User $user, JobRequisition $requisition): bool
{
    if (! $user->hasPermissionTo('recruitment.requisitions.reject')) {
        return false;
    }

    // HR Manager exception (mirrors approve)
    if ($user->hasRole('manager') && $user->employee?->department?->code === 'HR') {
        return true;
    }

    return $user->id !== $requisition->requested_by;
}
```

- [ ] **Step 4: Verify syntax**

```bash
php -l app/Policies/Recruitment/RequisitionPolicy.php
php -l app/Domains/HR/Recruitment/Services/RequisitionService.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add app/Policies/Recruitment/RequisitionPolicy.php \
        app/Domains/HR/Recruitment/Services/RequisitionService.php
git commit -m "feat(recruitment): HR Manager can approve their own requisitions (SoD bypass)"
```

---

## Task 7: Add Offer Expiry Console Command

**Files:**
- Create: `app/Console/Commands/ExpireJobOffersCommand.php`
- Modify: `routes/console.php` (or `app/Console/Kernel.php` if using Kernel)

`OfferService::expireOffer()` exists but is never called. Sent offers past `expires_at` stay in `sent` status forever.

- [ ] **Step 1: Check how scheduling is done in this project**

```bash
grep -n "schedule\|expireOffer\|Artisan" routes/console.php 2>/dev/null | head -20
grep -n "schedule\|expireOffer" app/Console/Kernel.php 2>/dev/null | head -20
```

- [ ] **Step 2: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\StateMachines\OfferStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireJobOffersCommand extends Command
{
    protected $signature = 'recruitment:expire-offers';

    protected $description = 'Transition sent job offers past their expiry date to expired status';

    public function __construct(private readonly OfferStateMachine $stateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = JobOffer::where('status', OfferStatus::Sent->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $offer) {
            DB::transaction(function () use ($offer): void {
                $this->stateMachine->transition($offer, OfferStatus::Expired);
                $offer->save();
            });
            $count++;
        }

        $this->info("Expired {$count} job offer(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Register the schedule**

Open `routes/console.php` (Laravel 11) and add:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('recruitment:expire-offers')->dailyAt('01:00');
```

If the project uses `app/Console/Kernel.php` style, add inside `schedule()`:

```php
$schedule->command('recruitment:expire-offers')->dailyAt('01:00');
```

- [ ] **Step 4: Verify command registered**

```bash
php artisan list | grep expire
```

Expected: `recruitment:expire-offers` listed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ExpireJobOffersCommand.php routes/console.php
git commit -m "feat(recruitment): add daily offer expiry command"
```

---

## Task 8: Wire RecruitmentSeeder into DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

`php artisan migrate:fresh --seed` never creates recruitment demo data.

- [ ] **Step 1: Check DatabaseSeeder seeder chain**

```bash
grep -n "class\|call\|seeder" database/seeders/DatabaseSeeder.php | head -40
```

- [ ] **Step 2: Add RecruitmentSeeder at end of chain**

In `database/seeders/DatabaseSeeder.php`, find the `$this->call([...])` array and append `RecruitmentSeeder::class` as the last item (after `TestAccountsSeeder` or `SystemSettingsSeeder`). The exact position:

```php
// After all transactional seeders:
RecruitmentSeeder::class,
```

- [ ] **Step 3: Verify it doesn't break full seed**

```bash
php artisan migrate:fresh --seed 2>&1 | tail -20
```

Expected: No errors, `RecruitmentSeeder` appears in output.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "chore(recruitment): wire RecruitmentSeeder into DatabaseSeeder"
```

---

## Task 9: Fix TypeScript `HiringStatus` Type + `statusColors`

**Files:**
- Modify: `frontend/src/types/recruitment.ts`

`HiringStatus` is missing `pending_vp_approval` and `rejected_by_vp`. The VP approval UI (Task 11) depends on these values being typed.

- [ ] **Step 1: Fix HiringStatus type**

In `frontend/src/types/recruitment.ts`, replace line 31:

```typescript
export type HiringStatus = 'pending' | 'hired' | 'failed_preemployment'
```

with:

```typescript
export type HiringStatus =
  | 'pending'
  | 'pending_vp_approval'
  | 'hired'
  | 'rejected_by_vp'
  | 'failed_preemployment'
```

- [ ] **Step 2: Add missing status colors**

In `frontend/src/types/recruitment.ts`, in the `statusColors` map, add after `failed_preemployment: 'red'`:

```typescript
pending_vp_approval: 'amber',
rejected_by_vp: 'red',
```

- [ ] **Step 3: Run typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "hiring\|recruitment" | head -20
```

Expected: No errors related to HiringStatus.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/types/recruitment.ts
git commit -m "fix(frontend): add pending_vp_approval and rejected_by_vp to HiringStatus type"
```

---

## Task 10: Fix Zod Schemas — Align All Enums with Backend

**Files:**
- Modify: `frontend/src/schemas/recruitment.ts`

Four mismatches between frontend Zod schemas and backend enum values:

| Schema field | Current (wrong) | Correct (backend) |
|---|---|---|
| `jobRequisitionSchema.employment_type` | includes `'probationary'` | `'regular'\|'contractual'\|'project_based'\|'part_time'` |
| `jobOfferSchema.employment_type` | includes `'probationary'` | `'regular'\|'contractual'\|'project_based'\|'part_time'` |
| `interviewEvaluationSchema.recommendation` | `'strong_hire'\|'hire'\|'consider'\|'no_hire'` | `'endorse'\|'reject'\|'hold'` |
| `applicationSchema.source` | `'website'\|'referral'\|...\|'social_media'\|'other'` | `'referral'\|'walk_in'\|'job_board'\|'agency'\|'internal'` |
| `interviewScheduleSchema.interview_type` | `'phone'\|'video'\|'in_person'\|'panel'\|'technical'` | `'panel'\|'one_on_one'\|'technical'\|'hr_screening'\|'final'` |
| `interviewScheduleSchema.interviewer_ids` | `z.array(...)` | `interviewer_id: z.coerce.number().positive().optional()` + `interviewer_department_id: z.coerce.number().positive().optional()` |

- [ ] **Step 1: Fix `jobRequisitionSchema.employment_type`**

Change line 8:

```typescript
employment_type: z.enum(['regular', 'contractual', 'project_based', 'part_time'], {
  required_error: 'Employment type is required',
}),
```

- [ ] **Step 2: Fix `applicationSchema.source`**

Change line 47:

```typescript
source: z.enum(['referral', 'walk_in', 'job_board', 'agency', 'internal']).default('job_board'),
```

- [ ] **Step 3: Fix `interviewScheduleSchema` — type and interviewer fields**

Replace the entire `interviewScheduleSchema` (lines 56–68):

```typescript
export const interviewScheduleSchema = z.object({
  application_id: z.coerce.number({ required_error: 'Application is required' }).positive(),
  type: z.enum(['panel', 'one_on_one', 'technical', 'hr_screening', 'final'], {
    required_error: 'Interview type is required',
  }),
  scheduled_at: z.string().trim().min(1, 'Date and time is required'),
  duration_minutes: z.coerce.number().min(15).max(480).default(60),
  location: z.string().trim().max(500).optional(),
  interviewer_id: z.coerce.number().positive().optional(),
  interviewer_department_id: z.coerce.number().positive().optional(),
  round: z.coerce.number().min(1).default(1),
  notes: z.string().trim().max(2000).optional(),
}).refine(
  (data) => data.interviewer_id !== undefined || data.interviewer_department_id !== undefined,
  { message: 'Either an interviewer or an interviewer department is required', path: ['interviewer_id'] },
)

export type InterviewScheduleFormValues = z.infer<typeof interviewScheduleSchema>
```

- [ ] **Step 4: Fix `jobOfferSchema.employment_type`**

Change line 79:

```typescript
employment_type: z.enum(['regular', 'contractual', 'project_based', 'part_time']),
```

- [ ] **Step 5: Fix `interviewEvaluationSchema.recommendation`**

Change lines 92–95:

```typescript
recommendation: z.enum(['endorse', 'reject', 'hold'], {
  required_error: 'Recommendation is required',
}),
```

- [ ] **Step 6: Run typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "schema\|recruitment" | head -20
```

Expected: No schema-related type errors.

- [ ] **Step 7: Commit**

```bash
git add frontend/src/schemas/recruitment.ts
git commit -m "fix(frontend): align recruitment Zod schemas with backend enum values"
```

---

## Task 11: Fix ScheduleInterviewModal — Align Field Names

**Files:**
- Modify: `frontend/src/components/recruitment/ScheduleInterviewModal.tsx`

The modal already uses `type` (not `interview_type`) and `interviewer_id` (not `interviewer_ids`) — these match the backend. But the modal's type dropdown options need to match the corrected schema values (`panel|one_on_one|technical|hr_screening|final`).

- [ ] **Step 1: Read the current modal**

```bash
cat frontend/src/components/recruitment/ScheduleInterviewModal.tsx
```

- [ ] **Step 2: Fix type dropdown options**

Find the `<select>` for `type` in the modal. Replace its `<option>` values so they match the backend enum (`panel|one_on_one|technical|hr_screening|final`):

```tsx
<select
  value={form.type}
  onChange={(e) => setForm({ ...form, type: e.target.value })}
  className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
>
  <option value="hr_screening">HR Screening</option>
  <option value="panel">Panel Interview</option>
  <option value="technical">Technical Interview</option>
  <option value="one_on_one">One-on-One</option>
  <option value="final">Final Interview</option>
</select>
```

- [ ] **Step 3: Typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "interview\|modal" | head -10
```

Expected: No errors.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/components/recruitment/ScheduleInterviewModal.tsx
git commit -m "fix(frontend): align interview type options with backend enum values"
```

---

## Task 12: Frontend — Add "Open Requisition" Button

**Files:**
- Modify: `frontend/src/pages/hr/recruitment/RequisitionDetailPage.tsx`

After a requisition is `approved`, HR must click "Open Requisition" to transition it to `open` so postings can begin. The button calls the new `open` action via the existing `useRequisitionAction` hook.

- [ ] **Step 1: Add `canOpen` flag and button**

In `frontend/src/pages/hr/recruitment/RequisitionDetailPage.tsx`, after line 25 (`const canResume = req.status === 'on_hold'`), add:

```tsx
const canOpen = req.status === 'approved'
```

Then in the actions section, add after the "Create Job Posting" `PermissionGuard` block and before the "Edit & Resubmit" block:

```tsx
<PermissionGuard permission={PERMISSIONS.hr.full_access}>
  {canOpen && (
    <button
      onClick={() => handleAction('open')}
      disabled={action.isPending}
      className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
    >
      Open for Applications
    </button>
  )}
</PermissionGuard>
```

- [ ] **Step 2: Typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "requisition" | head -10
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/pages/hr/recruitment/RequisitionDetailPage.tsx
git commit -m "feat(frontend): add 'Open for Applications' button on approved requisitions"
```

---

## Task 13: Add VP Approve/Reject Hooks for Hiring

**Files:**
- Modify: `frontend/src/hooks/useRecruitment.ts`

The VP approve/reject routes already exist (`POST /hirings/{hiring}/vp-approve` and `/vp-reject`) but there are no hooks for them. The UI (Task 14) depends on these.

- [ ] **Step 1: Add `hirings` key and VP hooks**

In `frontend/src/hooks/useRecruitment.ts`, in the `KEYS` object (after `candidates`), add:

```typescript
hirings: ['recruitment', 'hirings'] as const,
hiring: (ulid: string) => ['recruitment', 'hirings', ulid] as const,
```

Then after the `useHire` hook (around line 275), add:

```typescript
export function useVpApproveHiring() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ hiringUlid, notes }: { hiringUlid: string; notes?: string }) =>
      api.post(`/recruitment/hirings/${hiringUlid}/vp-approve`, { notes }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.applications })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

export function useVpRejectHiring() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ hiringUlid, reason }: { hiringUlid: string; reason: string }) =>
      api.post(`/recruitment/hirings/${hiringUlid}/vp-reject`, { reason }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.applications })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}
```

- [ ] **Step 2: Typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "hook\|hiring" | head -10
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/hooks/useRecruitment.ts
git commit -m "feat(frontend): add useVpApproveHiring and useVpRejectHiring hooks"
```

---

## Task 14: Frontend — Add VP Approve/Reject UI on ApplicationDetailPage

**Files:**
- Modify: `frontend/src/pages/hr/recruitment/ApplicationDetailPage.tsx`

When `app.hiring?.status === 'pending_vp_approval'`, the VP needs approve/reject buttons. This is guarded by `recruitment.hiring.approve` permission (which only `vice_president` / `executive` roles have). A rejection requires a reason.

- [ ] **Step 1: Add imports**

In `ApplicationDetailPage.tsx`, add to the existing imports:

```tsx
import { useVpApproveHiring, useVpRejectHiring } from '@/hooks/useRecruitment'
```

- [ ] **Step 2: Add hooks and state**

After the existing hook calls (around line 36), add:

```tsx
const vpApprove = useVpApproveHiring()
const vpReject = useVpRejectHiring()
const [vpRejectReason, setVpRejectReason] = useState('')
```

- [ ] **Step 3: Add VP approval banner in the main return**

In `ApplicationDetailPage.tsx`, after the header `</div>` block (around line 132, before `<div className="grid`), insert:

```tsx
{/* VP Hiring Approval Banner */}
{app.hiring?.status === 'pending_vp_approval' && (
  <PermissionGuard permission="recruitment.hiring.approve">
    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
      <p className="mb-1 text-sm font-semibold text-amber-800 dark:text-amber-400">
        Hiring Request Pending VP Approval
      </p>
      <p className="mb-3 text-xs text-amber-700 dark:text-amber-500">
        Candidate: {app.candidate?.full_name} — Hiring submitted, awaiting your approval to create employee record.
      </p>
      <textarea
        placeholder="VP notes (optional for approval, required for rejection)"
        value={vpRejectReason}
        onChange={(e) => setVpRejectReason(e.target.value)}
        className="mb-3 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
        rows={2}
      />
      <div className="flex gap-3">
        <button
          onClick={async () => {
            await vpApprove.mutateAsync({ hiringUlid: app.hiring!.ulid, notes: vpRejectReason || undefined })
            toast.success('Hiring approved — employee record created')
            refetch()
          }}
          disabled={vpApprove.isPending}
          className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
        >
          {vpApprove.isPending ? 'Approving…' : 'Approve Hiring'}
        </button>
        <button
          onClick={async () => {
            if (!vpRejectReason.trim()) {
              toast.error('Rejection reason is required')
              return
            }
            await vpReject.mutateAsync({ hiringUlid: app.hiring!.ulid, reason: vpRejectReason })
            toast.success('Hiring rejected')
            refetch()
          }}
          disabled={vpReject.isPending}
          className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
        >
          {vpReject.isPending ? 'Rejecting…' : 'Reject Hiring'}
        </button>
      </div>
    </div>
  </PermissionGuard>
)}

{/* VP Rejection Banner */}
{app.hiring?.status === 'rejected_by_vp' && (
  <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
    <p className="text-sm font-semibold text-red-800 dark:text-red-400">Hiring Rejected by VP</p>
    <p className="text-xs text-red-700 dark:text-red-500">
      The hiring request for {app.candidate?.full_name} was rejected. A new hiring request can be submitted.
    </p>
  </div>
)}
```

- [ ] **Step 4: Update `canHire` condition to allow re-hire after VP rejection**

Change line 42:

```tsx
const canHire = app.offer?.status === 'accepted' && app.status !== 'hired' && app.hiring?.status !== 'pending_vp_approval'
```

- [ ] **Step 5: Typecheck**

```bash
cd frontend && pnpm typecheck 2>&1 | grep -i "application\|hiring" | head -20
```

Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/pages/hr/recruitment/ApplicationDetailPage.tsx
git commit -m "feat(frontend): add VP hiring approve/reject UI on ApplicationDetailPage"
```

---

## Task 15: Write Tests for New Behavior

**Files:**
- Modify: `tests/Feature/Recruitment/RecruitmentApiTest.php`

Cover the new behaviors: SoD bypass, `open` route, offer auth, interview eval auth.

- [ ] **Step 1: Add SoD bypass test**

In `tests/Feature/Recruitment/RecruitmentApiTest.php`, add:

```php
test('HR manager can approve own requisition (SoD bypass)', function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    $hrDept = \App\Domains\HR\Models\Department::factory()->create(['code' => 'HR']);
    $hrManager = User::factory()->create();
    $hrManager->assignRole('manager');
    $employee = \App\Domains\HR\Models\Employee::factory()->create([
        'user_id' => $hrManager->id,
        'department_id' => $hrDept->id,
    ]);
    $hrManager->departments()->sync([$hrDept->id]);

    $dept = \App\Domains\HR\Models\Department::factory()->create();
    $position = \App\Domains\HR\Models\Position::factory()->create();
    $requisition = \App\Domains\HR\Recruitment\Models\JobRequisition::factory()->create([
        'requested_by' => $hrManager->id,
        'status' => 'pending_approval',
        'department_id' => $dept->id,
        'position_id' => $position->id,
    ]);

    $this->actingAs($hrManager)
        ->postJson("/api/v1/recruitment/requisitions/{$requisition->ulid}/approve", [
            'remarks' => 'HR Manager self-approving own requisition',
        ])
        ->assertOk();

    expect($requisition->fresh()->status->value)->toBe('approved');
});

test('non-HR manager cannot approve own requisition', function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $dept = \App\Domains\HR\Models\Department::factory()->create(['code' => 'PROD']);
    $manager->departments()->sync([$dept->id]);

    $position = \App\Domains\HR\Models\Position::factory()->create();
    $requisition = \App\Domains\HR\Recruitment\Models\JobRequisition::factory()->create([
        'requested_by' => $manager->id,
        'status' => 'pending_approval',
        'department_id' => $dept->id,
        'position_id' => $position->id,
    ]);

    $this->actingAs($manager)
        ->postJson("/api/v1/recruitment/requisitions/{$requisition->ulid}/approve")
        ->assertForbidden();
});

test('approved requisition can be opened via POST /open', function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $dept = \App\Domains\HR\Models\Department::factory()->create();
    $position = \App\Domains\HR\Models\Position::factory()->create();
    $requisition = \App\Domains\HR\Recruitment\Models\JobRequisition::factory()->create([
        'status' => 'approved',
        'department_id' => $dept->id,
        'position_id' => $position->id,
    ]);

    $this->actingAs($manager)
        ->postJson("/api/v1/recruitment/requisitions/{$requisition->ulid}/open")
        ->assertOk();

    expect($requisition->fresh()->status->value)->toBe('open');
});

test('create offer requires recruitment.offers.create permission', function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)
        ->postJson('/api/v1/recruitment/offers', [])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/pest tests/Feature/Recruitment/ -v 2>&1 | tail -30
```

Expected: All tests pass. The new tests may fail if factories don't exist — adjust factory calls to match what exists.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Recruitment/RecruitmentApiTest.php
git commit -m "test(recruitment): add SoD bypass, open route, and offer auth tests"
```

---

## Self-Review Checklist

**Spec coverage:**
- [x] Task 1: DB CHECK constraint bug (critical)
- [x] Task 2: HiringController missing Request import
- [x] Task 3: OfferController missing authorize
- [x] Task 4: InterviewController missing authorize
- [x] Task 5: `open` route for approved requisitions
- [x] Task 6: HR Manager SoD bypass
- [x] Task 7: Offer expiry command
- [x] Task 8: RecruitmentSeeder wired
- [x] Task 9: HiringStatus TS type
- [x] Task 10: Zod schema fixes
- [x] Task 11: ScheduleInterviewModal type options
- [x] Task 12: "Open for Applications" button
- [x] Task 13: VP approve/reject hooks
- [x] Task 14: VP approval UI on ApplicationDetailPage
- [x] Task 15: Tests

**Placeholder scan:** No TBD/TODO in any step. Every step has exact code or exact commands.

**Type consistency:** 
- `HiringStatus` updated in Task 9 before used in Task 14
- `useVpApproveHiring` / `useVpRejectHiring` defined in Task 13 before imported in Task 14
- Schema `InterviewScheduleFormValues` regenerated in Task 10 before modal aligned in Task 11
