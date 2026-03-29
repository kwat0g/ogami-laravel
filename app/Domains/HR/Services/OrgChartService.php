<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Org Chart Service — generates hierarchical organization data for visualization.
 *
 * Provides both department hierarchy and employee reporting-line data
 * suitable for rendering with d3-org-chart or similar frontend libraries.
 */
final class OrgChartService implements ServiceContract
{
    /**
     * Get the full organization tree (departments + employees).
     *
     * @return Collection<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     parent_id: string|null,
     *     department: string|null,
     *     position: string|null,
     *     employee_code: string|null,
     *     avatar_url: string|null,
     *     children_count: int,
     * }>
     */
    public function fullTree(): Collection
    {
        $departments = Department::orderBy('name')->get();
        $employees = Employee::query()
            ->whereIn('employment_status', ['active', 'on_leave'])
            ->with(['department', 'position'])
            ->orderBy('last_name')
            ->get();

        $nodes = collect();

        // Add department nodes
        foreach ($departments as $dept) {
            $nodes->push([
                'id' => 'dept-' . $dept->id,
                'name' => $dept->name,
                'type' => 'department',
                'parent_id' => $dept->parent_id ? 'dept-' . $dept->parent_id : null,
                'department' => null,
                'position' => null,
                'employee_code' => null,
                'avatar_url' => null,
                'children_count' => $employees->where('department_id', $dept->id)->count(),
            ]);
        }

        // Add employee nodes under their department
        foreach ($employees as $emp) {
            $parentId = $emp->reports_to
                ? 'emp-' . $emp->reports_to
                : ($emp->department_id ? 'dept-' . $emp->department_id : null);

            $nodes->push([
                'id' => 'emp-' . $emp->id,
                'name' => $emp->last_name . ', ' . $emp->first_name,
                'type' => 'employee',
                'parent_id' => $parentId,
                'department' => $emp->department?->name,
                'position' => $emp->position?->title ?? $emp->position?->name ?? null,
                'employee_code' => $emp->employee_code,
                'avatar_url' => null,
                'children_count' => $employees->where('reports_to', $emp->id)->count(),
            ]);
        }

        return $nodes;
    }

    /**
     * Get reporting line for a specific employee (upward chain to top).
     *
     * @return Collection<int, array{employee_id: int, name: string, position: string|null, department: string|null, level: int}>
     */
    public function reportingLine(Employee $employee): Collection
    {
        $chain = collect();
        $current = $employee;
        $level = 0;
        $visited = []; // prevent infinite loops

        while ($current !== null && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            $chain->push([
                'employee_id' => $current->id,
                'name' => $current->last_name . ', ' . $current->first_name,
                'position' => $current->position?->title ?? $current->position?->name ?? null,
                'department' => $current->department?->name,
                'level' => $level,
            ]);

            $level++;
            $current = $current->reports_to
                ? Employee::with(['position', 'department'])->find($current->reports_to)
                : null;
        }

        return $chain;
    }

    /**
     * Get direct reports for a specific employee.
     *
     * @return Collection<int, array{employee_id: int, name: string, position: string|null, department: string|null, employee_code: string}>
     */
    public function directReports(Employee $employee): Collection
    {
        return Employee::query()
            ->where('reports_to', $employee->id)
            ->whereIn('employment_status', ['active', 'on_leave'])
            ->with(['position', 'department'])
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $emp) => [
                'employee_id' => $emp->id,
                'name' => $emp->last_name . ', ' . $emp->first_name,
                'position' => $emp->position?->title ?? $emp->position?->name ?? null,
                'department' => $emp->department?->name,
                'employee_code' => $emp->employee_code,
            ]);
    }

    /**
     * Headcount analytics per department.
     *
     * @return Collection<int, array{department_id: int, department_name: string, headcount: int, active: int, on_leave: int}>
     */
    public function headcountByDepartment(): Collection
    {
        return Employee::query()
            ->whereIn('employment_status', ['active', 'on_leave'])
            ->with('department')
            ->get()
            ->groupBy('department_id')
            ->map(function (Collection $employees) {
                $dept = $employees->first()->department;

                return [
                    'department_id' => $dept?->id ?? 0,
                    'department_name' => $dept?->name ?? 'Unassigned',
                    'headcount' => $employees->count(),
                    'active' => $employees->where('employment_status', 'active')->count(),
                    'on_leave' => $employees->where('employment_status', 'on_leave')->count(),
                ];
            })
            ->sortByDesc('headcount')
            ->values();
    }
}
