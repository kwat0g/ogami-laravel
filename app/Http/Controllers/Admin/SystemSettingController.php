<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingRequest;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * System Settings Management Controller (Admin-only).
 *
 * Delegates all DB operations to SystemSettingService (ARCH-001 compliance).
 *
 * @permission system.edit_settings
 */
final class SystemSettingController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $service,
    ) {}

    /**
     * List all system settings grouped by category.
     *
     * GET /api/v1/admin/settings
     */
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()->can('system.edit_settings'), 403, 'Insufficient permissions.');

        $settings = $this->service->listAll();

        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->map(fn ($item) => $this->formatSetting($item))->values();
        });

        return response()->json(['data' => $grouped]);
    }

    /**
     * Get settings by group.
     *
     * GET /api/v1/admin/settings/{group}
     */
    public function byGroup(string $group): JsonResponse
    {
        abort_unless(Auth::user()->can('system.edit_settings'), 403, 'Insufficient permissions.');

        $settings = $this->service->listByGroup($group);

        $formatted = $settings->map(fn ($item) => $this->formatSetting($item));

        return response()->json(['data' => $formatted]);
    }

    /**
     * Get a single setting by key.
     *
     * GET /api/v1/admin/settings/key/{key}
     */
    public function show(string $key): JsonResponse
    {
        abort_unless(Auth::user()->can('system.edit_settings'), 403, 'Insufficient permissions.');

        $setting = $this->service->getByKey($key);

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
                'error_code' => 'SETTING_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSetting($setting),
        ]);
    }

    /**
     * Update a system setting value.
     *
     * PATCH /api/v1/admin/settings/{key}
     *
     * @permission system.edit_settings
     */
    public function update(UpdateSystemSettingRequest $request, string $key): JsonResponse
    {
        $updated = $this->service->updateByKey($key, $request->input('value'), Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully.',
            'data' => [
                'id' => $updated->id,
                'key' => $updated->key,
                'label' => $updated->label,
                'value' => $updated->is_sensitive ? null : json_decode($updated->value, true),
                'data_type' => $updated->data_type,
                'group' => $updated->group,
                'is_sensitive' => (bool) $updated->is_sensitive,
                'editable_by_role' => $updated->editable_by_role,
                'updated_by' => $updated->updated_by,
                'updated_at' => $updated->updated_at,
            ],
        ]);
    }

    /**
     * Bulk update multiple settings (for save-all functionality).
     *
     * POST /api/v1/admin/settings/bulk-update
     *
     * @permission system.edit_settings
     */
    public function bulkUpdate(UpdateSystemSettingRequest $request): JsonResponse
    {
        $result = $this->service->batchUpdate(
            $request->input('settings', []),
            Auth::id()
        );

        return response()->json([
            'success' => count($result['errors']) === 0,
            'message' => count($result['updated']) > 0
                ? sprintf('%d setting(s) updated successfully.', count($result['updated']))
                : 'No settings were updated.',
            'data' => $result,
        ]);
    }

    // ── Formatting Helpers ──────────────────────────────────────────────

    private function formatSetting(\stdClass $item): array
    {
        return [
            'id' => $item->id,
            'key' => $item->key,
            'label' => $item->label,
            'value' => $item->is_sensitive ? null : json_decode($item->value, true),
            'data_type' => $item->data_type,
            'group' => $item->group,
            'is_sensitive' => (bool) $item->is_sensitive,
            'editable_by_role' => $item->editable_by_role,
            'updated_by' => $item->updated_by,
            'updated_at' => $item->updated_at,
            'created_at' => $item->created_at,
            'input_type' => $this->getInputType($item->data_type),
            'validation_rules' => $this->getValidationRules($item->data_type),
        ];
    }

    private function getInputType(string $dataType): string
    {
        return match ($dataType) {
            'string' => 'text',
            'integer' => 'number',
            'decimal' => 'number',
            'boolean' => 'checkbox',
            'json' => 'textarea',
            default => 'text',
        };
    }

    private function getValidationRules(string $dataType): array
    {
        return match ($dataType) {
            'string' => ['type' => 'string', 'min' => 0, 'max' => 255],
            'integer' => ['type' => 'integer', 'step' => 1],
            'decimal' => ['type' => 'number', 'step' => 0.01],
            'boolean' => ['type' => 'boolean'],
            'json' => ['type' => 'json'],
            default => ['type' => 'string'],
        };
    }
}
