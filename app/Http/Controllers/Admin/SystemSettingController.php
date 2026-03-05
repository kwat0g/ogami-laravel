<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingRequest;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * System Settings Management Controller (Admin-only).
 *
 * Provides CRUD operations for operational settings seeded by SystemSettingsSeeder.
 * All changes are audited via the owen-it/laravel-auditing package.
 *
 * @permission system.edit_settings
 */
final class SystemSettingController extends Controller
{
    /**
     * List all system settings grouped by category.
     *
     * GET /api/v1/admin/settings
     */
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()->can('system.edit_settings'), 403, 'Insufficient permissions.');

        $settings = DB::table('system_settings')
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        // Group by category for frontend
        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->map(function ($item) {
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
            })->values();
        });

        return response()->json(['data' => $grouped]);
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

    /**
     * Get settings by group.
     *
     * GET /api/v1/admin/settings/{group}
     */
    public function byGroup(string $group): JsonResponse
    {
        abort_unless(Auth::user()->can('system.edit_settings'), 403, 'Insufficient permissions.');

        $settings = DB::table('system_settings')
            ->where('group', $group)
            ->orderBy('key')
            ->get();

        $formatted = $settings->map(function ($item) {
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
        });

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

        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
                'error_code' => 'SETTING_NOT_FOUND',
            ], 404);
        }

        $formatted = [
            'id' => $setting->id,
            'key' => $setting->key,
            'label' => $setting->label,
            'value' => $setting->is_sensitive ? null : json_decode($setting->value, true),
            'data_type' => $setting->data_type,
            'group' => $setting->group,
            'is_sensitive' => (bool) $setting->is_sensitive,
            'editable_by_role' => $setting->editable_by_role,
            'updated_by' => $setting->updated_by,
            'updated_at' => $setting->updated_at,
            'created_at' => $setting->created_at,
            'input_type' => $this->getInputType($setting->data_type),
            'validation_rules' => $this->getValidationRules($setting->data_type),
        ];

        return response()->json([
            'success' => true,
            'data' => $formatted,
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
        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
                'error_code' => 'SETTING_NOT_FOUND',
            ], 404);
        }

        // Validate value based on data_type
        $validatedValue = $this->validateAndCastValue(
            $request->input('value'),
            $setting->data_type
        );

        // Update the setting
        DB::table('system_settings')
            ->where('key', $key)
            ->update([
                'value' => json_encode($validatedValue),
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

        // Record audit log
        $this->recordAudit($setting, $validatedValue);

        // Get updated setting
        $updated = DB::table('system_settings')
            ->where('key', $key)
            ->first();

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
        $settings = $request->input('settings', []);
        $updated = [];
        $errors = [];

        foreach ($settings as $item) {
            $key = $item['key'] ?? null;
            $value = $item['value'] ?? null;

            if (! $key) {
                $errors[] = ['key' => null, 'error' => 'Key is required'];

                continue;
            }

            $setting = DB::table('system_settings')
                ->where('key', $key)
                ->first();

            if (! $setting) {
                $errors[] = ['key' => $key, 'error' => 'Setting not found'];

                continue;
            }

            try {
                $validatedValue = $this->validateAndCastValue($value, $setting->data_type);

                DB::table('system_settings')
                    ->where('key', $key)
                    ->update([
                        'value' => json_encode($validatedValue),
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ]);

                $this->recordAudit($setting, $validatedValue);
                $updated[] = $key;
            } catch (\Throwable $e) {
                $errors[] = ['key' => $key, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($updated) > 0
                ? sprintf('%d setting(s) updated successfully.', count($updated))
                : 'No settings were updated.',
            'data' => ['updated' => $updated, 'errors' => $errors],
        ]);
    }

    /**
     * Validate and cast value based on data_type.
     *
     * @throws \InvalidArgumentException
     */
    private function validateAndCastValue(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'string' => is_string($value) ? $value : (string) $value,
            'integer' => is_int($value) ? $value : (int) $value,
            'decimal' => is_numeric($value) ? (float) $value : throw new DomainException('Value must be numeric.', 'SETTING_INVALID_VALUE', 422),
            'boolean' => is_bool($value) ? $value : in_array($value, [1, '1', 'true', true], true),
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => throw new DomainException("Unknown data type: {$dataType}", 'SETTING_UNKNOWN_TYPE', 422),
        };
    }

    /**
     * Record audit log for setting changes.
     */
    private function recordAudit(\stdClass $setting, mixed $newValue): void
    {
        DB::table('audits')->insert([
            'user_type' => 'App\Models\User',
            'user_id' => Auth::id(),
            'event' => 'updated',
            'auditable_type' => 'system_setting',
            'auditable_id' => $setting->id,
            'old_values' => json_encode(['value' => $setting->value]),
            'new_values' => json_encode(['value' => json_encode($newValue)]),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tags' => 'settings,admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
