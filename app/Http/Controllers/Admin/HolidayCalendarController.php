<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\HolidayCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for Holiday Calendar management.
 */
final class HolidayCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HolidayCalendar::query()
            ->orderBy('holiday_date', 'desc');

        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('region')) {
            $query->where('region', $request->input('region'));
        }

        $holidays = $query->get();

        return response()->json([
            'data' => $holidays,
            'by_year' => $holidays->groupBy('year'),
            'years' => $holidays->pluck('year')->unique()->sort()->values()->values(),
            'types' => $holidays->pluck('type')->unique()->values()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:holiday_calendars,holiday_date',
            'name' => 'required|string|max:150',
            'type' => 'required|string|in:REGULAR,SPECIAL_NON_WORKING,SPECIAL_WORKING',
            'is_nationwide' => 'boolean',
            'region' => 'nullable|string|max:20',
            'proclamation_reference' => 'nullable|string|max:200',
        ]);

        $validated['year'] = date('Y', strtotime($validated['holiday_date']));
        $validated['is_nationwide'] = $validated['is_nationwide'] ?? true;

        $holiday = HolidayCalendar::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => HolidayCalendar::class,
            'auditable_id' => $holiday->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Holiday created successfully',
            'data' => $holiday,
        ], 201);
    }

    public function show(HolidayCalendar $holiday): JsonResponse
    {
        return response()->json(['data' => $holiday]);
    }

    public function update(Request $request, HolidayCalendar $holiday): JsonResponse
    {
        $validated = $request->validate([
            'holiday_date' => 'sometimes|required|date|unique:holiday_calendars,holiday_date,'.$holiday->id,
            'name' => 'sometimes|required|string|max:150',
            'type' => 'sometimes|required|string|in:REGULAR,SPECIAL_NON_WORKING,SPECIAL_WORKING',
            'is_nationwide' => 'boolean',
            'region' => 'nullable|string|max:20',
            'proclamation_reference' => 'nullable|string|max:200',
        ]);

        if (isset($validated['holiday_date'])) {
            $validated['year'] = date('Y', strtotime($validated['holiday_date']));
        }

        $oldValues = $holiday->toArray();
        $holiday->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => HolidayCalendar::class,
            'auditable_id' => $holiday->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Holiday updated successfully',
            'data' => $holiday,
        ]);
    }

    public function destroy(HolidayCalendar $holiday): JsonResponse
    {
        $oldValues = $holiday->toArray();
        $holiday->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => HolidayCalendar::class,
            'auditable_id' => $holiday->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Holiday deleted successfully']);
    }

    /**
     * Get holidays for a specific year.
     */
    public function byYear(int $year): JsonResponse
    {
        $holidays = HolidayCalendar::where('year', $year)
            ->orderBy('holiday_date')
            ->get();

        return response()->json([
            'year' => $year,
            'count' => $holidays->count(),
            'data' => $holidays,
        ]);
    }

    /**
     * Bulk import holidays for a year.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'holidays' => 'required|array',
            'holidays.*.holiday_date' => 'required|date',
            'holidays.*.name' => 'required|string|max:150',
            'holidays.*.type' => 'required|string|in:REGULAR,SPECIAL_NON_WORKING,SPECIAL_WORKING',
            'holidays.*.is_nationwide' => 'boolean',
            'holidays.*.region' => 'nullable|string|max:20',
            'holidays.*.proclamation_reference' => 'nullable|string|max:200',
        ]);

        $created = [];
        foreach ($validated['holidays'] as $holidayData) {
            $holidayData['year'] = $validated['year'];
            $holidayData['is_nationwide'] = $holidayData['is_nationwide'] ?? true;

            $holiday = HolidayCalendar::create($holidayData);
            $created[] = $holiday;
        }

        Audit::create([
            'event' => 'bulk_created',
            'auditable_type' => HolidayCalendar::class,
            'auditable_id' => 0,
            'old_values' => [],
            'new_values' => ['count' => count($created), 'year' => $validated['year']],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => count($created).' holidays created successfully',
            'data' => $created,
        ], 201);
    }
}
