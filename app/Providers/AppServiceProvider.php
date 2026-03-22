<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Accounting\Models\BankAccount;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\RecurringJournalTemplate;
use App\Domains\Accounting\Policies\BankAccountPolicy;
use App\Domains\Accounting\Policies\BankReconciliationPolicy;
use App\Domains\Accounting\Policies\ChartOfAccountPolicy;
use App\Domains\Accounting\Policies\FiscalPeriodPolicy;
use App\Domains\Accounting\Policies\JournalEntryPolicy;
use App\Domains\Accounting\Policies\RecurringJournalTemplatePolicy;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorCreditNote;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Policies\VendorCreditNotePolicy;
use App\Domains\AP\Policies\VendorInvoicePolicy;
use App\Domains\AP\Policies\VendorPolicy;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Policies\CustomerCreditNotePolicy;
use App\Domains\AR\Policies\CustomerInvoicePolicy;
use App\Domains\AR\Policies\CustomerPolicy;
use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\Attendance\Policies\AttendanceLogPolicy;
use App\Domains\Attendance\Policies\OvertimeRequestPolicy;
use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Domains\Budget\Policies\BudgetPolicy;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\Ticket;
use App\Domains\CRM\Policies\TicketPolicy;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Policies\DeliveryPolicy;
use App\Domains\FixedAssets\Models\FixedAsset;
use App\Domains\FixedAssets\Policies\FixedAssetPolicy;
use App\Domains\HR\Events\EmployeeActivated;
use App\Domains\HR\Listeners\CreateLeaveBalances;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Policies\EmployeePolicy;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Policies\ItemMasterPolicy;
use App\Domains\Inventory\Policies\MaterialRequisitionPolicy;
use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\InternalAudit;
use App\Domains\ISO\Policies\ISOPolicy;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Policies\LeaveBalancePolicy;
use App\Domains\Leave\Policies\LeaveRequestPolicy;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Policies\LoanPolicy;
use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Policies\MaintenancePolicy;
use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Policies\MoldPolicy;
use App\Domains\Payroll\Models\PayPeriod;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Policies\PayPeriodPolicy;
use App\Domains\Payroll\Policies\PayrollRunPolicy;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\VendorRfq;
use App\Domains\Procurement\Policies\GoodsReceiptPolicy;
use App\Domains\Procurement\Policies\PurchaseOrderPolicy;
use App\Domains\Procurement\Policies\PurchaseRequestPolicy;
use App\Domains\Procurement\Policies\VendorRfqPolicy;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Policies\ProductionOrderPolicy;
use App\Domains\QC\Models\Inspection;
use App\Domains\QC\Models\InspectionTemplate;
use App\Domains\QC\Models\NonConformanceReport;
use App\Domains\QC\Policies\InspectionPolicy;
use App\Domains\QC\Policies\InspectionTemplatePolicy;
use App\Domains\QC\Policies\NcrPolicy;
use App\Domains\Tax\Models\BirFiling;
use App\Domains\Tax\Models\VatLedger;
use App\Domains\Tax\Policies\BirFilingPolicy;
use App\Domains\Tax\Policies\VatLedgerPolicy;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Infrastructure\Boot\ValidateEnvironment;
use App\Infrastructure\Observers\PayrollRunObserver;
use App\Listeners\UpdateStockOnThreeWayMatch;
use App\Domains\CRM\Policies\ClientOrderPolicy;
use App\Services\DepartmentModuleService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // ── Environment validation — fail early on missing config ────────────
        ValidateEnvironment::check();
        // ── Production safety: block destructive DB commands ─────────────────
        // Prevents `migrate:fresh`, `migrate:reset`, and `db:wipe` from ever
        // running against the production database. This is the final hard stop
        // in a layered defence:
        //   1. phpunit.xml        — force="true" pins DB_DATABASE=ogami_erp_test
        //   2. phpunit-backup-verify.xml — force="true" + ogami_erp_restore_test
        //   3. VerifyBackupCommand — dual pre-flight guards before running tests
        //   4. HERE              — framework-level prohibition (this block)
        //
        // We check the actual DB name (not only APP_ENV) because accidents most
        // often happen when APP_ENV=local but DB_DATABASE accidentally points at
        // the real production database.
        $activeConnection = config('database.default', 'pgsql');
        $activeDatabase = config("database.connections.{$activeConnection}.database");
        // Block only when BOTH the DB name is the production DB AND the env is
        // production. Checking DB name alone would block local dev since local
        // also uses ogami_erp as the database name.
        $isProductionDb = ($activeDatabase === 'ogami_erp') && $this->app->isProduction();
        DB::prohibitDestructiveCommands($isProductionDb || $this->app->isProduction());

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
        Gate::policy(RecurringJournalTemplate::class, RecurringJournalTemplatePolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(VendorInvoice::class, VendorInvoicePolicy::class);
        Gate::policy(VendorCreditNote::class, VendorCreditNotePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerInvoice::class, CustomerInvoicePolicy::class);
        Gate::policy(CustomerCreditNote::class, CustomerCreditNotePolicy::class);
        Gate::policy(VatLedger::class, VatLedgerPolicy::class);
        Gate::policy(BirFiling::class, BirFilingPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(BankReconciliation::class, BankReconciliationPolicy::class);
        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);
        Gate::policy(PayPeriod::class, PayPeriodPolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(LeaveBalance::class, LeaveBalancePolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(VendorRfq::class, VendorRfqPolicy::class);
        Gate::policy(ClientOrder::class, ClientOrderPolicy::class);

        // ── Inventory + Production + QC + Maintenance + Mold + Delivery + ISO policies
        Gate::policy(ItemMaster::class, ItemMasterPolicy::class);
        Gate::policy(MaterialRequisition::class, MaterialRequisitionPolicy::class);
        Gate::policy(BillOfMaterials::class, ProductionOrderPolicy::class);
        Gate::policy(ProductionOrder::class, ProductionOrderPolicy::class);
        Gate::policy(Inspection::class, InspectionPolicy::class);
        Gate::policy(InspectionTemplate::class, InspectionTemplatePolicy::class);
        Gate::policy(NonConformanceReport::class, NcrPolicy::class);
        Gate::policy(Equipment::class, MaintenancePolicy::class);
        Gate::policy(MaintenanceWorkOrder::class, MaintenancePolicy::class);
        Gate::policy(MoldMaster::class, MoldPolicy::class);
        Gate::policy(DeliveryReceipt::class, DeliveryPolicy::class);
        Gate::policy(ControlledDocument::class, ISOPolicy::class);
        Gate::policy(InternalAudit::class, ISOPolicy::class);
        Gate::policy(FixedAsset::class, FixedAssetPolicy::class);
        Gate::policy(CostCenter::class, BudgetPolicy::class);
        Gate::policy(AnnualBudget::class, BudgetPolicy::class);

        // ── Observer registrations ───────────────────────────────────────────
        // PayrollRun → GL auto-post when status transitions to 'approved'.
        PayrollRun::observe(PayrollRunObserver::class);

        // ── Event → Listener bindings ────────────────────────────────────────
        // NOTE: Listeners under app/Listeners/ are auto-discovered by Laravel's
        // event discovery (and cached in bootstrap/cache/events.php). Do NOT
        // register those here — it would cause every listener to fire twice.
        //
        // Only register listeners that live outside the auto-discovered path:
        Event::listen(EmployeeActivated::class, CreateLeaveBalances::class);
        // UpdateStockOnThreeWayMatch is in app/Listeners/ — auto-discovered by Laravel.
        // Do NOT register it here; that would cause it to fire twice per GR confirmation.

        // ── RBAC v2: Dynamic permission resolution via DepartmentModuleService ──
        // This integrates our module-based permission system with Laravel's Gate.
        // We use Gate::after to override Spatie's permission check for department-assigned users.
        Gate::after(function ($user, string $ability, ?bool $result) {
            // Super admin bypass (already handled by Spatie, but double-check)
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // For users WITH department assignments, ONLY use module permissions.
            // Override Spatie's result if it differs from module permissions.
            if ($user->departments()->exists()) {
                return DepartmentModuleService::userHasPermission($user, $ability);
            }

            // For users without departments, use Spatie's result (pass through)
            return $result;
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
            // Disable rate limiting in test environment
            if (app()->environment('testing')) {
                return Limit::none();
            }

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
            // Disable rate limiting in test environment
            if (app()->environment('testing')) {
                return Limit::none();
            }

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

        // Client order actions: approve, reject, negotiate, respond, cancel
        // 10 requests per minute per user
        RateLimiter::for('client-order-actions', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return $request->user()
                ? Limit::perMinute(10)->by('client-order-actions:'.$request->user()->id)
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many client order requests. Please wait a moment.',
                        'error_code' => 'CLIENT_ORDER_RATE_LIMIT_EXCEEDED',
                    ], 429))
                : Limit::perMinute(5)->by('client-order-actions:'.$request->ip())
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many requests.',
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                    ], 429));
        });
    }
}
