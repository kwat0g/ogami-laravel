<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \stdClass
 */
final class SystemSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \stdClass $setting */
        $setting = $this->resource;

        // Decode JSON value based on data_type
        $decodedValue = json_decode($setting->value, true);

        return [
            'id' => $setting->id,
            'key' => $setting->key,
            'label' => $setting->label,
            'value' => $decodedValue,
            'data_type' => $setting->data_type,
            'group' => $setting->group,
            'is_sensitive' => $setting->is_sensitive,
            'editable_by_role' => $setting->editable_by_role,
            'updated_by' => $setting->updated_by,
            'updated_at' => $setting->updated_at,
            'created_at' => $setting->created_at,
            // UI hints
            'input_type' => $this->getInputType($setting->data_type),
            'validation_rules' => $this->getValidationRules($setting->data_type),
        ];
    }

    /**
     * Map data_type to HTML input type.
     */
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

    /**
     * Get validation rules hint for frontend.
     */
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
