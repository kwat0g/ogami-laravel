<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\EmployeeShiftAssignment;
use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\HR\Events\EmployeeActivated;
use App\Domains\HR\Events\EmployeeResigned;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Infrastructure\Scopes\DepartmentScope;
use App\Models\User;
use App\Shared\Traits\HasDepartmentScope;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Core Employee aggregate root.
 *
 * Design notes:
 *  - Government IDs (SSS, TIN, PhilHealth, Pag-IBIG) are stored BOTH as
 *    *_encrypted (model-layer encryption via encrypt()/decrypt()) AND
 *    *_hash (SHA-256 for DB-level uniqueness checks — EMP-009).
 *  - daily_rate / hourly_rate are PostgreSQL stored computed columns;
 *    they are never written by the application layer (EMP-S2).
 *  - Employment state machine: draft → active → on_leave|suspended → resigned|terminated
 *
 * @property int $id
 * @property string $employee_code e.g. EMP-2025-001
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $suffix
 * @property Carbon $date_of_birth
 * @property string $gender male|female|other
 * @property string|null $civil_status
 * @property string|null $citizenship
 * @property string|null $present_address
 * @property string|null $permanent_address
 * @property string|null $personal_email
 * @property string|null $personal_phone
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $salary_grade_id
 * @property int|null $reports_to FK employees.id
 * @property string $employment_type regular|contractual|project_based|seasonal|probationary
 * @property string $employment_status active|on_leave|suspended|resigned|terminated
 * @property string $pay_basis monthly|daily|hourly
 * @property int $basic_monthly_rate centavos
 * @property int $daily_rate centavos (stored computed: monthly/22)
 * @property int $hourly_rate centavos (stored computed: daily/8)
 * @property Carbon $date_hired
 * @property Carbon|null $regularization_date
 * @property Carbon|null $separation_date
 * @property string $onboarding_status draft|documents_pending|active|offboarding|offboarded
 * @property bool $is_active
 * @property string|null $sss_no_encrypted
 * @property string|null $sss_no_hash
 * @property string|null $tin_encrypted
 * @property string|null $tin_hash
 * @property string|null $philhealth_no_encrypted
 * @property string|null $philhealth_no_hash
 * @property string|null $pagibig_no_encrypted
 * @property string|null $pagibig_no_hash
 * @property string|null $bir_status raw|registered
 * @property string|null $bank_name
 * @property string|null $bank_account_no
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read SalaryGrade|null $salaryGrade
 * @property-read Collection<int, EmployeeDocument> $documents
 * @property-read Collection<int, LeaveBalance> $leaveBalances
 * @property-read Collection<int, LeaveRequest> $leaveRequests
 * @property-read Collection<int, AttendanceLog> $attendanceLogs
 * @property-read Collection<int, OvertimeRequest> $overtimeRequests
 * @property-read Collection<int, EmployeeShiftAssignment> $shiftAssignments
 */
final class Employee extends Model implements Auditable
{
    use AuditableTrait, HasDepartmentScope, HasFactory, HasPublicUlid, SoftDeletes;

    /**
     * Override the trait's resolveRouteBindingQuery so that route model binding
     * always resolves by ULID across ALL departments.
     *
     * Why: DepartmentScope adds "WHERE department_id = X" to every query on this
     * model.  For list endpoints that is intentional, but for single-record
     * lookups (show / update / transition) it causes 404s whenever the target
     * employee is in a different department than the requesting user.
     * Authorization is enforced at the controller level via Policy gates
     * ($this->authorize()), so bypassing the scope here is safe.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return $query
            ->withTrashed()
            ->withoutGlobalScope(DepartmentScope::class)
            ->where('ulid', $value);
    }

    /** @internal Point HasFactory to the explicit factory class. */
    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected $table = 'employees';

    // ── Event dispatch on state transitions ───────────────────────────────────

    /** Ephemeral flag: set by EmployeeStateMachine, consumed by the saved hook. */
    public bool $pendingActivated = false;

    /** Ephemeral flag: set by EmployeeStateMachine, consumed by the saved hook. */
    public bool $pendingResigned = false;

    protected static function booted(): void
    {
        // Strip ephemeral signal attributes out of the attributes bag BEFORE
        // the SQL runs — Eloquent would otherwise try to persist them as columns.
        self::saving(static function (Employee $employee): void {
            $attrs = $employee->getAttributes();

            if ($attrs['_fire_activated_event'] ?? false) {
                $employee->pendingActivated = true;
                $employee->offsetUnset('_fire_activated_event');
            }

            if ($attrs['_fire_resigned_event'] ?? false) {
                $employee->pendingResigned = true;
                $employee->offsetUnset('_fire_resigned_event');
            }
        });

        self::saved(static function (Employee $employee): void {
            if ($employee->pendingActivated) {
                $employee->pendingActivated = false;
                EmployeeActivated::dispatch($employee);
            }

            if ($employee->pendingResigned) {
                $employee->pendingResigned = false;
                EmployeeResigned::dispatch(
                    $employee,
                    $employee->employment_status,
                    (string) ($employee->separation_date ?? now()->toDateString()),
                );
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'date_of_birth',
        'gender',
        'civil_status',
        'citizenship',
        'present_address',
        'permanent_address',
        'personal_email',
        'personal_phone',
        'department_id',
        'position_id',
        'salary_grade_id',
        'reports_to',
        'employment_type',
        'employment_status',
        'pay_basis',
        'basic_monthly_rate',
        'date_hired',
        'regularization_date',
        'separation_date',
        'onboarding_status',
        'is_active',
        'bir_status',
        'bank_name',
        'bank_account_no',
        'notes',
    ];

    /** @var list<string> Government ID columns excluded from mass-assignment; set via dedicated methods. */
    protected $guarded = [
        'sss_no_encrypted', 'sss_no_hash',
        'tin_encrypted', 'tin_hash',
        'philhealth_no_encrypted', 'philhealth_no_hash',
        'pagibig_no_encrypted', 'pagibig_no_hash',
        // Stored-computed columns — never written by the app.
        'daily_rate',
        'hourly_rate',
    ];

    /** @var list<string> */
    protected $hidden = [
        'sss_no_encrypted',
        'tin_encrypted',
        'philhealth_no_encrypted',
        'pagibig_no_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_hired' => 'date',
            'regularization_date' => 'date',
            'separation_date' => 'date',
            'basic_monthly_rate' => 'integer',
            'daily_rate' => 'integer',
            'hourly_rate' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /** Full name helper: "Juan A. Dela Cruz Jr." */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? ' '.strtoupper($this->middle_name[0]).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';

        return "{$this->first_name}{$middle} {$this->last_name}{$suffix}";
    }

    // ── Government ID encrypted getters/setters ───────────────────────────────

    /** Retrieve decrypted SSS number or null. */
    public function getSssNo(): ?string
    {
        return $this->sss_no_encrypted ? decrypt($this->sss_no_encrypted) : null;
    }

    /**
     * Normalize a government ID for hashing: strip all non-alphanumeric characters
     * and uppercase the result. This ensures "12-3456789-0" and "1234567890"
     * produce the same hash and are correctly detected as duplicates.
     */
    private static function normalizeGovId(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value));
    }

    /** Store SSS number encrypted + hash for uniqueness. */
    public function setSssNo(?string $value): self
    {
        $this->attributes['sss_no_encrypted'] = $value ? encrypt($value) : null;
        $normalized = $value ? self::normalizeGovId($value) : null;
        $this->attributes['sss_no_hash'] = $normalized ? hash('sha256', $normalized) : null;

        return $this;
    }

    /** Retrieve decrypted TIN or null. */
    public function getTin(): ?string
    {
        return $this->tin_encrypted ? decrypt($this->tin_encrypted) : null;
    }

    /** Store TIN encrypted + hash. */
    public function setTin(?string $value): self
    {
        $this->attributes['tin_encrypted'] = $value ? encrypt($value) : null;
        $normalized = $value ? self::normalizeGovId($value) : null;
        $this->attributes['tin_hash'] = $normalized ? hash('sha256', $normalized) : null;

        return $this;
    }

    /** Retrieve decrypted PhilHealth number or null. */
    public function getPhilhealthNo(): ?string
    {
        return $this->philhealth_no_encrypted ? decrypt($this->philhealth_no_encrypted) : null;
    }

    /** Store PhilHealth number encrypted + hash. */
    public function setPhilhealthNo(?string $value): self
    {
        $this->attributes['philhealth_no_encrypted'] = $value ? encrypt($value) : null;
        $normalized = $value ? self::normalizeGovId($value) : null;
        $this->attributes['philhealth_no_hash'] = $normalized ? hash('sha256', $normalized) : null;

        return $this;
    }

    /** Retrieve decrypted Pag-IBIG number or null. */
    public function getPagibigNo(): ?string
    {
        return $this->pagibig_no_encrypted ? decrypt($this->pagibig_no_encrypted) : null;
    }

    /** Store Pag-IBIG number encrypted + hash. */
    public function setPagibigNo(?string $value): self
    {
        $this->attributes['pagibig_no_encrypted'] = $value ? encrypt($value) : null;
        $normalized = $value ? self::normalizeGovId($value) : null;
        $this->attributes['pagibig_no_hash'] = $normalized ? hash('sha256', $normalized) : null;

        return $this;
    }

    // ── Business helpers ─────────────────────────────────────────────────────

    /** Whether the employee is currently employed (active or on_leave). */
    public function isCurrentlyEmployed(): bool
    {
        return in_array($this->employment_status, ['active', 'on_leave', 'suspended'], true);
    }

    /** Monthly rate in full pesos. */
    public function getBasicMonthlyRatePesosAttribute(): float
    {
        return $this->basic_monthly_rate / 100;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    /** @return BelongsTo<Employee, Employee> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'submitted_by');
    }

    /** The ERP user account linked to this employee (nullable — not every employee has a login). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when this employee already has a user account. */
    public function hasUserAccount(): bool
    {
        return $this->user_id !== null;
    }
}
