<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\PerformanceAppraisal;
use App\Domains\HR\Models\PerformanceAppraisalCriteria;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Performance Appraisal Service — manages employee performance evaluations.
 *
 * Workflow: draft -> submitted -> manager_reviewed -> hr_approved -> completed
 *
 * Flexibility:
 *   - Multiple review types: annual, mid_year, probationary, project_based
 *   - Weighted KPI criteria with 0-100 rating scale
 *   - Overall rating auto-computed from weighted criteria
 *   - Performance levels: outstanding, exceeds, meets, needs_improvement, unsatisfactory
 *   - SoD: reviewer must differ from the employee being reviewed
 */
final class PerformanceAppraisalService implements ServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceAppraisal::with(['employee', 'reviewer', 'criteria'])
            ->orderByDesc('created_at');

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['review_type'])) {
            $query->where('review_type', $filters['review_type']);
        }

        if (isset($filters['reviewer_id'])) {
            $query->where('reviewer_id', $filters['reviewer_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Create a new performance appraisal with criteria.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $criteria
     */
    public function store(array $data, array $criteria, User $actor): PerformanceAppraisal
    {
        if (empty($criteria)) {
            throw new DomainException(
                'At least one evaluation criteria is required.',
                'HR_APPRAISAL_NO_CRITERIA',
                422,
            );
        }

        // Validate total weight sums to 100
        $totalWeight = array_sum(array_column($criteria, 'weight_pct'));
        if ($totalWeight !== 100) {
            throw new DomainException(
                "Criteria weights must sum to 100%. Current total: {$totalWeight}%.",
                'HR_APPRAISAL_INVALID_WEIGHTS',
                422,
                ['total_weight' => $totalWeight],
            );
        }

        return DB::transaction(function () use ($data, $criteria, $actor): PerformanceAppraisal {
            $appraisal = PerformanceAppraisal::create([
                'employee_id' => $data['employee_id'],
                'reviewer_id' => $data['reviewer_id'],
                'review_type' => $data['review_type'],
                'review_period_start' => $data['review_period_start'],
                'review_period_end' => $data['review_period_end'],
                'status' => 'draft',
                'employee_comments' => $data['employee_comments'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            foreach ($criteria as $criterion) {
                PerformanceAppraisalCriteria::create([
                    'appraisal_id' => $appraisal->id,
                    'criteria_name' => $criterion['criteria_name'],
                    'description' => $criterion['description'] ?? null,
                    'weight_pct' => $criterion['weight_pct'],
                    'rating_pct' => null, // Filled during review
                    'comments' => null,
                ]);
            }

            return $appraisal->load(['employee', 'reviewer', 'criteria']);
        });
    }

    /**
     * Submit appraisal for manager review.
     */
    public function submit(PerformanceAppraisal $appraisal, User $actor): PerformanceAppraisal
    {
        if ($appraisal->status !== 'draft') {
            throw new DomainException('Appraisal must be in draft to submit.', 'HR_APPRAISAL_NOT_DRAFT', 422);
        }

        $appraisal->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $appraisal->fresh(['employee', 'reviewer', 'criteria']) ?? $appraisal;
    }

    /**
     * Manager reviews: rates each criteria and adds comments.
     *
     * @param  list<array{criteria_id: int, rating_pct: int, comments?: string}>  $ratings
     */
    public function managerReview(
        PerformanceAppraisal $appraisal,
        array $ratings,
        string $reviewerComments,
        User $reviewer,
    ): PerformanceAppraisal {
        if ($appraisal->status !== 'submitted') {
            throw new DomainException('Appraisal must be submitted for review.', 'HR_APPRAISAL_NOT_SUBMITTED', 422);
        }

        // SoD: reviewer must not be the employee
        $employee = $appraisal->employee;
        if ($employee !== null && $employee->user_id === $reviewer->id) {
            throw new DomainException(
                'Reviewer cannot review their own performance.',
                'HR_APPRAISAL_SELF_REVIEW',
                422,
            );
        }

        return DB::transaction(function () use ($appraisal, $ratings, $reviewerComments, $reviewer): PerformanceAppraisal {
            // Update each criteria rating
            foreach ($ratings as $rating) {
                PerformanceAppraisalCriteria::where('id', $rating['criteria_id'])
                    ->where('appraisal_id', $appraisal->id)
                    ->update([
                        'rating_pct' => min(100, max(0, $rating['rating_pct'])),
                        'comments' => $rating['comments'] ?? null,
                    ]);
            }

            // Compute overall rating from weighted criteria
            $appraisal->refresh();
            $overallRating = $appraisal->computeOverallRating();

            $appraisal->update([
                'status' => 'manager_reviewed',
                'reviewer_id' => $reviewer->id,
                'reviewer_comments' => $reviewerComments,
                'overall_rating_pct' => $overallRating,
                'reviewed_at' => now(),
            ]);

            return $appraisal->fresh(['employee', 'reviewer', 'criteria']) ?? $appraisal;
        });
    }

    /**
     * HR approves the reviewed appraisal.
     */
    public function hrApprove(
        PerformanceAppraisal $appraisal,
        string $hrComments,
        User $hrApprover,
    ): PerformanceAppraisal {
        if ($appraisal->status !== 'manager_reviewed') {
            throw new DomainException('Appraisal must be manager-reviewed for HR approval.', 'HR_APPRAISAL_NOT_REVIEWED', 422);
        }

        $appraisal->update([
            'status' => 'hr_approved',
            'hr_comments' => $hrComments,
            'hr_approved_by_id' => $hrApprover->id,
            'hr_approved_at' => now(),
        ]);

        return $appraisal->fresh(['employee', 'reviewer', 'criteria']) ?? $appraisal;
    }

    /**
     * Complete the appraisal (final step after HR approval).
     */
    public function complete(PerformanceAppraisal $appraisal): PerformanceAppraisal
    {
        if ($appraisal->status !== 'hr_approved') {
            throw new DomainException('Appraisal must be HR-approved to complete.', 'HR_APPRAISAL_NOT_APPROVED', 422);
        }

        $appraisal->update(['status' => 'completed']);

        return $appraisal->fresh(['employee', 'reviewer', 'criteria']) ?? $appraisal;
    }

    /**
     * Get performance history for an employee.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PerformanceAppraisal>
     */
    public function employeeHistory(int $employeeId): \Illuminate\Database\Eloquent\Collection
    {
        return PerformanceAppraisal::where('employee_id', $employeeId)
            ->whereIn('status', ['completed', 'hr_approved'])
            ->with('criteria')
            ->orderByDesc('review_period_end')
            ->get();
    }

    /**
     * Department performance summary — average ratings per department.
     *
     * @return \Illuminate\Support\Collection<int, array{department_id: int, department_name: string, avg_rating: float, total_reviews: int}>
     */
    public function departmentSummary(?int $year = null): \Illuminate\Support\Collection
    {
        $year ??= (int) now()->format('Y');

        return DB::table('performance_appraisals as pa')
            ->join('employees as e', 'pa.employee_id', '=', 'e.id')
            ->join('departments as d', 'e.department_id', '=', 'd.id')
            ->whereIn('pa.status', ['completed', 'hr_approved'])
            ->whereYear('pa.review_period_end', $year)
            ->whereNull('pa.deleted_at')
            ->select(
                'd.id as department_id',
                'd.name as department_name',
                DB::raw('ROUND(AVG(pa.overall_rating_pct), 1) as avg_rating'),
                DB::raw('COUNT(*) as total_reviews'),
            )
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('avg_rating')
            ->get()
            ->map(fn ($row) => [
                'department_id' => (int) $row->department_id,
                'department_name' => $row->department_name,
                'avg_rating' => (float) $row->avg_rating,
                'total_reviews' => (int) $row->total_reviews,
            ]);
    }
}
