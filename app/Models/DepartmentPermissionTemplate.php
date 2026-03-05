<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Permission templates that can be assigned to any department.
 * Decouples permissions from hardcoded department codes.
 */
class DepartmentPermissionTemplate extends Model
{
    protected $fillable = [
        'template_key',
        'label',
        'manager_permissions',
        'supervisor_permissions',
        'is_active',
    ];

    protected $casts = [
        'manager_permissions' => 'array',
        'supervisor_permissions' => 'array',
        'is_active' => 'boolean',
    ];
}
