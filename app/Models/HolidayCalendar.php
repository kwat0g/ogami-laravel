<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HolidayCalendar — Philippines public & special holiday reference.
 *
 * @property int $id
 * @property int $year
 * @property string $holiday_date (date)
 * @property string $name
 * @property string $holiday_type regular_holiday|special_non_working|special_working
 * @property string|null $region
 */
class HolidayCalendar extends Model
{
    use SoftDeletes;

    protected $table = 'holiday_calendars';

    public $timestamps = false;

    protected $fillable = [
        'year',
        'holiday_date',
        'name',
        'holiday_type',
        'region',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'year' => 'integer',
    ];
}
