<?php

declare(strict_types=1);

namespace App\Domains\AR\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $level
 * @property int $days_overdue
 * @property string $name
 * @property string $template_text
 * @property bool $is_active
 */
final class DunningLevel extends Model
{
    protected $table = 'dunning_levels';

    protected $fillable = [
        'level',
        'days_overdue',
        'name',
        'template_text',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'days_overdue' => 'integer',
        'is_active' => 'boolean',
    ];
}
