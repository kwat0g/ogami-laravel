<?php

declare(strict_types=1);

namespace App\Services;

use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * System Settings Service — CRUD + audit for system_settings table.
 *
 * Extracted from SystemSettingController to comply with ARCH-001
 * (no DB:: facade usage in controllers).
 */
final class SystemSettingService implements ServiceContract
{
    // ── Read ─────────────────────────────────────────────────────────────

    /** Get all settings ordered by group and key. */
    public function listAll(): Collection
    {
        return DB::table('system_settings')
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    /** Get settings for a specific group. */
    public function listByGroup(string $group): Collection
    {
        return DB::table('system_settings')
            ->where('group', $group)
            ->orderBy('key')
            ->get();
    }

    /** Get a single setting by key. Returns null if not found. */
    public function getByKey(string $key): ?\stdClass
    {
        return DB::table('system_settings')
            ->where('key', $key)
            ->first();
    }

    /**
     * Get company info settings (company_name, company_address, etc.).
     * Used by payslip PDF, AR statements, and other reports.
     */
    public function getCompanyInfo(): array
    {
        $settings = DB::table('system_settings')
            ->whereIn('key', ['company_name', 'company_address', 'company_tin'])
            ->pluck('value', 'key');

        return [
            'company_name' => json_decode($settings->get('company_name', '"Ogami Manufacturing Corp."'), true),
            'company_address' => json_decode($settings->get('company_address', '""'), true),
            'company_tin' => json_decode($settings->get('company_tin', '""'), true),
        ];
    }

    // ── Write ────────────────────────────────────────────────────────────

    /**
     * Update a single setting by key.
     *
     * @return \stdClass The updated setting record.
     *
     * @throws DomainException If setting not found or invalid value.
     */
    public function updateByKey(string $key, mixed $rawValue, ?int $userId = null): \stdClass
    {
        $setting = $this->getByKey($key);

        if (! $setting) {
            throw new DomainException('Setting not found.', 'SETTING_NOT_FOUND', 404);
        }

        $validatedValue = $this->validateAndCastValue($rawValue, $setting->data_type);

        DB::table('system_settings')
            ->where('key', $key)
            ->update([
                'value' => json_encode($validatedValue),
                'updated_by' => $userId ?? Auth::id(),
                'updated_at' => now(),
            ]);

        $this->recordAudit($setting, $validatedValue, $userId);

        return DB::table('system_settings')
            ->where('key', $key)
            ->first();
    }

    /**
     * Bulk update multiple settings.
     *
     * @param  array<int, array{key: string, value: mixed}>  $settings
     * @return array{updated: list<string>, errors: list<array{key: string|null, error: string}>}
     */
    public function batchUpdate(array $settings, ?int $userId = null): array
    {
        $updated = [];
        $errors = [];

        foreach ($settings as $item) {
            $key = $item['key'] ?? null;
            $value = $item['value'] ?? null;

            if (! $key) {
                $errors[] = ['key' => null, 'error' => 'Key is required'];

                continue;
            }

            try {
                $this->updateByKey($key, $value, $userId);
                $updated[] = $key;
            } catch (\Throwable $e) {
                $errors[] = ['key' => $key, 'error' => $e->getMessage()];
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Validate and cast value based on data_type.
     *
     * @throws DomainException
     */
    public function validateAndCastValue(mixed $value, string $dataType): mixed
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

    /** Record audit log for setting changes. */
    private function recordAudit(\stdClass $setting, mixed $newValue, ?int $userId = null): void
    {
        DB::table('audits')->insert([
            'user_type' => 'App\Models\User',
            'user_id' => $userId ?? Auth::id(),
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
