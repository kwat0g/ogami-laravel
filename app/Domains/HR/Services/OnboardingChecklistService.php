<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Shared\Contracts\ServiceContract;

/**
 * OnboardingChecklistService
 *
 * Manages employee onboarding checklist items. Each new employee (status = 'draft')
 * has a set of required documents and steps that must be completed before
 * they can transition to 'active' status.
 *
 * Checklist items are stored in the `employee_onboarding_items` table.
 * When all required items are checked, the employee can be auto-activated.
 *
 * Usage:
 *   $service->initializeChecklist($employee)   // Create default items for new employee
 *   $service->checkItem($employee, $itemId)    // Mark an item as completed
 *   $service->getProgress($employee)           // Get completion percentage
 *   $service->canActivate($employee)           // Check if all required items are done
 */
final class OnboardingChecklistService implements ServiceContract
{
    /**
     * Default checklist template.
     * Each item has: label, category, is_required, sort_order
     *
     * @var list<array{label: string, category: string, is_required: bool, sort_order: int}>
     */
    private const DEFAULT_CHECKLIST = [
        // Personal Documents
        ['label' => 'Valid government ID (1 primary)', 'category' => 'documents', 'is_required' => true, 'sort_order' => 1],
        ['label' => 'Valid government ID (1 secondary)', 'category' => 'documents', 'is_required' => true, 'sort_order' => 2],
        ['label' => 'SSS ID / number', 'category' => 'documents', 'is_required' => true, 'sort_order' => 3],
        ['label' => 'PhilHealth ID / number', 'category' => 'documents', 'is_required' => true, 'sort_order' => 4],
        ['label' => 'Pag-IBIG MID number', 'category' => 'documents', 'is_required' => true, 'sort_order' => 5],
        ['label' => 'TIN (Tax Identification Number)', 'category' => 'documents', 'is_required' => true, 'sort_order' => 6],
        ['label' => 'NBI Clearance', 'category' => 'documents', 'is_required' => true, 'sort_order' => 7],
        ['label' => 'Barangay Clearance', 'category' => 'documents', 'is_required' => false, 'sort_order' => 8],
        ['label' => 'Police Clearance', 'category' => 'documents', 'is_required' => false, 'sort_order' => 9],
        ['label' => 'Birth Certificate (PSA)', 'category' => 'documents', 'is_required' => true, 'sort_order' => 10],
        ['label' => '2x2 ID Photo (4 pcs)', 'category' => 'documents', 'is_required' => true, 'sort_order' => 11],

        // Medical
        ['label' => 'Pre-employment Medical Examination', 'category' => 'medical', 'is_required' => true, 'sort_order' => 20],
        ['label' => 'Drug Test Result', 'category' => 'medical', 'is_required' => true, 'sort_order' => 21],

        // Employment
        ['label' => 'Signed Employment Contract', 'category' => 'employment', 'is_required' => true, 'sort_order' => 30],
        ['label' => 'Confidentiality / NDA Agreement', 'category' => 'employment', 'is_required' => true, 'sort_order' => 31],
        ['label' => 'Company Policy Acknowledgment', 'category' => 'employment', 'is_required' => true, 'sort_order' => 32],
        ['label' => 'Emergency Contact Form', 'category' => 'employment', 'is_required' => true, 'sort_order' => 33],
        ['label' => 'Bank Account Details (for payroll)', 'category' => 'employment', 'is_required' => true, 'sort_order' => 34],

        // IT / Access
        ['label' => 'ERP System Account Created', 'category' => 'access', 'is_required' => true, 'sort_order' => 40],
        ['label' => 'Biometric Fingerprint Enrolled', 'category' => 'access', 'is_required' => true, 'sort_order' => 41],
        ['label' => 'ID Badge Issued', 'category' => 'access', 'is_required' => false, 'sort_order' => 42],
        ['label' => 'Uniform Issued', 'category' => 'access', 'is_required' => false, 'sort_order' => 43],

        // Orientation
        ['label' => 'HR Orientation Completed', 'category' => 'orientation', 'is_required' => true, 'sort_order' => 50],
        ['label' => 'Department Orientation Completed', 'category' => 'orientation', 'is_required' => true, 'sort_order' => 51],
        ['label' => 'Safety Briefing Completed', 'category' => 'orientation', 'is_required' => true, 'sort_order' => 52],
    ];

    /**
     * Initialize the onboarding checklist for a new employee.
     * Typically called when an employee record is first created.
     *
     * @return int Number of checklist items created
     */
    public function initializeChecklist(Employee $employee): int
    {
        // Don't re-initialize if items already exist
        $existingCount = DB::table('employee_onboarding_items')
            ->where('employee_id', $employee->id)
            ->count();

        if ($existingCount > 0) {
            return 0;
        }

        $items = [];
        $now = now();

        foreach (self::DEFAULT_CHECKLIST as $item) {
            $items[] = [
                'employee_id' => $employee->id,
                'label' => $item['label'],
                'category' => $item['category'],
                'is_required' => $item['is_required'],
                'is_completed' => false,
                'completed_at' => null,
                'completed_by_id' => null,
                'sort_order' => $item['sort_order'],
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('employee_onboarding_items')->insert($items);

        Log::info('[Onboarding] Checklist initialized', [
            'employee_id' => $employee->id,
            'item_count' => count($items),
        ]);

        return count($items);
    }

    /**
     * Get the full checklist for an employee grouped by category.
     *
     * @return array<string, list<object>>
     */
    public function getChecklist(Employee $employee): array
    {
        $items = DB::table('employee_onboarding_items')
            ->where('employee_id', $employee->id)
            ->orderBy('sort_order')
            ->get();

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item->category][] = $item;
        }

        return $grouped;
    }

    /**
     * Get completion progress.
     *
     * @return array{total: int, completed: int, required_total: int, required_completed: int, percentage: float, can_activate: bool}
     */
    public function getProgress(Employee $employee): array
    {
        $items = DB::table('employee_onboarding_items')
            ->where('employee_id', $employee->id)
            ->get();

        $total = $items->count();
        $completed = $items->where('is_completed', true)->count();
        $requiredTotal = $items->where('is_required', true)->count();
        $requiredCompleted = $items->where('is_required', true)->where('is_completed', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'can_activate' => $requiredTotal > 0 && $requiredCompleted >= $requiredTotal,
        ];
    }

    /**
     * Mark a checklist item as completed.
     */
    public function checkItem(Employee $employee, int $itemId, int $actorId, ?string $notes = null): bool
    {
        $updated = DB::table('employee_onboarding_items')
            ->where('id', $itemId)
            ->where('employee_id', $employee->id)
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
                'completed_by_id' => $actorId,
                'notes' => $notes,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info('[Onboarding] Item checked', [
                'employee_id' => $employee->id,
                'item_id' => $itemId,
                'actor_id' => $actorId,
            ]);
        }

        return $updated > 0;
    }

    /**
     * Uncheck a checklist item (undo completion).
     */
    public function uncheckItem(Employee $employee, int $itemId): bool
    {
        $updated = DB::table('employee_onboarding_items')
            ->where('id', $itemId)
            ->where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->update([
                'is_completed' => false,
                'completed_at' => null,
                'completed_by_id' => null,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Check if employee can be activated (all required items completed).
     */
    public function canActivate(Employee $employee): bool
    {
        return $this->getProgress($employee)['can_activate'];
    }
}
