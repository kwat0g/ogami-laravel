<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Accounting\Models\BankAccount;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Policies\BankAccountPolicy;
use App\Domains\Accounting\Policies\BankReconciliationPolicy;
use App\Domains\Accounting\Policies\ChartOfAccountPolicy;
use App\Domains\Accounting\Policies\FiscalPeriodPolicy;
use App\Domains\Accounting\Policies\JournalEntryPolicy;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Policies\VendorInvoicePolicy;
use App\Domains\AP\Policies\VendorPolicy;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Policies\CustomerInvoicePolicy;
use App\Domains\AR\Policies\CustomerPolicy;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Policies\GoodsReceiptPolicy;
use App\Domains\Procurement\Policies\PurchaseOrderPolicy;
use App\Domains\Procurement\Policies\PurchaseRequestPolicy;
use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\Attendance\Policies\AttendanceLogPolicy;
use App\Domains\Attendance\Policies\OvertimeRequestPolicy;
use App\Domains\HR\Events\EmployeeActivated;
use App\Domains\HR\Listeners\CreateLeaveBalances;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Events\ISO\AuditFindingCreated;
use App\Events\Production\ProductionOrderCompleted;
use App\Events\QC\InspectionFailed;
use App\Events\QC\InspectionPassed;
use App\Listeners\Procurement\CreateApInvoiceOnThreeWayMatch;
use App\Listeners\Delivery\CreateDeliveryReceiptOnProductionComplete;
use App\Listeners\Production\HoldProductionOrderOnInspectionFail;
use App\Listeners\Production\ResumeProductionOrderOnInspectionPass;
use App\Listeners\QC\CreateCapaOnAuditFinding;
use App\Events\Leave\LeaveRequestDecided;
use App\Listeners\Attendance\RecordLeaveAttendanceCorrection;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Policies\EmployeePolicy;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Policies\LoanPolicy;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Tax\Models\VatLedger;
use App\Infrastructure\Observers\PayrollRunObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Policy registrations ─────────────────────────────────────────────
        // Laravel auto-discovery does not resolve policies in custom domain
        // namespaces, so we register them explicitly here.
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceLog::class, AttendanceLogPolicy::class);
        Gate::policy(OvertimeRequest::class, OvertimeRequestPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(ChartOfAccount::class, ChartOfAccountPolicy::class);
        Gate::policy(FiscalPeriod::class, FiscalPeriodPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(VendorInvoice::class, VendorInvoicePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerInvoice::class, CustomerInvoicePolicy::class);
        Gate::policy(VatLedger::class, \App\Domains\Tax\Policies\VatLedgerPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(BankReconciliation::class, BankReconciliationPolicy::class);
        Gate::policy(PayrollRun::class, \App\Domains\Payroll\Policies\PayrollRunPolicy::class);
        Gate::policy(LeaveRequest::class, \App\Domains\Leave\Policies\LeaveRequestPolicy::class);
        Gate::policy(LeaveBalance::class, \App\Domains\Leave\Policies\LeaveBalancePolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);

        // ── Inventory + Production + QC + Maintenance + Mold + Delivery + ISO policies
        Gate::policy(\App\Domains\Inventory\Models\ItemMaster::class, \App\Domains\Inventory\Policies\ItemMasterPolicy::class);
        Gate::policy(\App\Domains\Inventory\Models\MaterialRequisition::class, \App\Domains\Inventory\Policies\MaterialRequisitionPolicy::class);
        Gate::policy(\App\Domains\Production\Models\BillOfMaterials::class, \App\Domains\Production\Policies\ProductionOrderPolicy::class);
        Gate::policy(\App\Domains\Production\Models\ProductionOrder::class, \App\Domains\Production\Policies\ProductionOrderPolicy::class);
        Gate::policy(\App\Domains\QC\Models\Inspection::class, \App\Domains\QC\Policies\InspectionPolicy::class);
        Gate::policy(\App\Domains\QC\Models\NonConformanceReport::class, \App\Domains\QC\Policies\NcrPolicy::class);
        Gate::policy(\App\Domains\Maintenance\Models\Equipment::class, \App\Domains\Maintenance\Policies\MaintenancePolicy::class);
        Gate::policy(\App\Domains\Maintenance\Models\MaintenanceWorkOrder::class, \App\Domains\Maintenance\Policies\MaintenancePolicy::class);
        Gate::policy(\App\Domains\Mold\Models\MoldMaster::class, \App\Domains\Mold\Policies\MoldPolicy::class);
        Gate::policy(\App\Domains\Delivery\Models\DeliveryReceipt::class, \App\Domains\Delivery\Policies\DeliveryPolicy::class);
        Gate::policy(\App\Domains\ISO\Models\ControlledDocument::class, \App\Domains\ISO\Policies\ISOPolicy::class);
        Gate::policy(\App\Domains\ISO\Models\InternalAudit::class, \App\Domains\ISO\Policies\ISOPolicy::class);

        // ── Observer registrations ───────────────────────────────────────────
        // PayrollRun → GL auto-post when status transitions to 'approved'.
        PayrollRun::observe(PayrollRunObserver::class);

        // ── Event → Listener bindings ────────────────────────────────────────
        Event::listen(EmployeeActivated::class, CreateLeaveBalances::class);
        Event::listen(ThreeWayMatchPassed::class, CreateApInvoiceOnThreeWayMatch::class);
        Event::listen(ThreeWayMatchPassed::class, \App\Listeners\Inventory\LinkGoodsReceiptToInventory::class);
        Event::listen(AuditFindingCreated::class, CreateCapaOnAuditFinding::class);
        Event::listen(ProductionOrderCompleted::class, CreateDeliveryReceiptOnProductionComplete::class);
        Event::listen(InspectionFailed::class, HoldProductionOrderOnInspectionFail::class);
        Event::listen(InspectionPassed::class, ResumeProductionOrderOnInspectionPass::class);
        Event::listen(LeaveRequestDecided::class, RecordLeaveAttendanceCorrection::class);

        // ── Super Admin — bypass ALL gate / policy checks for testing ────────
        // Users with the 'super_admin' role skip every Gate::check(), authorize(),
        // @can directive, and policy method.  SoD and dept-scope are handled
        // separately in their respective middleware.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // ── Monitoring dashboards ────────────────────────────────────────────
        // Pulse dashboard: restricted to Admin role only (LAN server)
        Gate::define('viewPulse', function ($user) {
            return $user->hasRole('admin') || $user->hasRole('super_admin');
        });

        // ── API Rate Limiting ────────────────────────────────────────────────
        // 'api-health' limiter: public health check endpoint — 60/min per IP.
        // Prevents infrastructure recon via repeated polling.
        RateLimiter::for('api-health', function (Request $request) {
            return Limit::perMinute(60)->by('health:'.$request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many requests.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429));
        });

        // 'api' limiter: reads 120/min, writes 60/min — differentiated by HTTP method.
        // Both share the same throttle:api middleware applied on all domain route groups.
        RateLimiter::for('api', function (Request $request) {
            $isWrite = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            $jsonResponse = fn (string $msg) => response()->json([
                'success' => false,
                'message' => $msg,
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);

            if ($request->user()) {
                return $isWrite
                    ? Limit::perMinute(60)->by('write:'.$request->user()->id)
                        ->response(fn () => $jsonResponse('Too many requests — please slow down and try again in a moment.'))
                    : Limit::perMinute(120)->by('read:'.$request->user()->id)
                        ->response(fn () => $jsonResponse('Too many requests — please slow down and try again in a moment.'));
            }

            return $isWrite
                ? Limit::perMinute(10)->by('write:'.$request->ip())
                    ->response(fn () => $jsonResponse('Too many requests.'))
                : Limit::perMinute(30)->by('read:'.$request->ip())
                    ->response(fn () => $jsonResponse('Too many requests.'));
        });

        // 'api-action' limiter: for critical state-change endpoints (approve / reject / endorse / disburse).
        // 20 actions per 5 minutes per user — prevents button-spam even when blocked by validation (422).
        RateLimiter::for('api-action', function (Request $request) {
            return $request->user()
                ? Limit::perMinutes(5, 20)->by('action:'.$request->user()->id)
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many action requests. Please wait a few minutes before trying again.',
                        'error_code' => 'ACTION_RATE_LIMIT_EXCEEDED',
                    ], 429))
                : Limit::perMinute(5)->by('action:'.$request->ip())
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many requests.',
                        'error_code' => 'ACTION_RATE_LIMIT_EXCEEDED',
                    ], 429));
        });

        // Login: 10 attempts/min per IP (defence-in-depth layer on top of AuthService lockout)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again later.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });
    }
}
