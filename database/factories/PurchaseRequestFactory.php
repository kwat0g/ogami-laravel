<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PurchaseRequestFactory — provides sensible test defaults for procurement PRs.
 *
 * @extends Factory<PurchaseRequest>
 */
final class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        $year = date('Y');
        $month = date('m');

        return [
            'pr_reference' => "PR-{$year}-{$month}-".str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            'department_id' => Department::factory(),
            'requested_by_id' => User::factory(),
            'urgency' => 'normal',
            'justification' => $this->faker->sentence(10),
            'notes' => null,
            'status' => 'draft',
            'total_estimated_cost' => '0.00',
            'submitted_by_id' => null,
            'submitted_at' => null,
            'noted_by_id' => null,
            'noted_at' => null,
            'noted_comments' => null,
            'checked_by_id' => null,
            'checked_at' => null,
            'checked_comments' => null,
            'reviewed_by_id' => null,
            'reviewed_at' => null,
            'reviewed_comments' => null,
            'vp_approved_by_id' => null,
            'vp_approved_at' => null,
            'vp_comments' => null,
            'budget_checked_by_id' => null,
            'budget_checked_at' => null,
            'budget_checked_comments' => null,
            'returned_by_id' => null,
            'returned_at' => null,
            'return_reason' => null,
            'rejected_by_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'rejection_stage' => null,
            'converted_to_po_id' => null,
            'converted_at' => null,
        ];
    }

    /**
     * Set PR as submitted.
     */
    public function submitted(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_by_id' => $userId ?? $attributes['requested_by_id'],
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set PR as approved (all stages complete).
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_by_id' => $attributes['requested_by_id'] ?? 1,
            'submitted_at' => now()->subDays(5),
            'noted_by_id' => 1,
            'noted_at' => now()->subDays(4),
            'checked_by_id' => 1,
            'checked_at' => now()->subDays(3),
            'reviewed_by_id' => 1,
            'reviewed_at' => now()->subDays(2),
            'vp_approved_by_id' => 1,
            'vp_approved_at' => now()->subDay(),
        ]);
    }

    /**
     * Set PR urgency to urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => 'urgent',
        ]);
    }

    /**
     * Set PR urgency to critical.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => 'critical',
        ]);
    }

    /**
     * Set PR as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set PR as returned.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returned',
            'returned_by_id' => 1,
            'returned_at' => now(),
            'return_reason' => 'Please revise and resubmit.',
        ]);
    }
}
