<?php

declare(strict_types=1);

/**
 * Automated smoke test -- hits every registered API route.
 * Run: ./vendor/bin/pest tests/Feature/AutoSmokeTest.php -v
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// -- Helpers ------------------------------------------------------------------

function seedAndGetUser(string $role = 'admin'): \App\Models\User
{
    \Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder']);
    \Artisan::call('db:seed', ['--class' => 'SampleAccountsSeeder']);
    \Artisan::call('db:seed', ['--class' => 'SampleDataSeeder']);

    $map = [
        'admin'       => 'admin@ogamierp.local',
        'hr_manager'  => 'hr.manager@ogamierp.local',
        'vp'          => 'vp@ogamierp.local',
        'acctg'       => 'accounting@ogamierp.local',
        'purchasing'  => 'purchasing.officer@ogamierp.local',
    ];

    return \App\Models\User::where('email', $map[$role])->firstOrFail();
}

function firstUlid(string $modelClass): ?string
{
    try {
        return $modelClass::withoutGlobalScopes()->value('ulid');
    } catch (\Throwable) {
        return null;
    }
}

// Map route name segments to model classes for ULID resolution
function resolveUlid(string $uri): string
{
    $modelMap = [
        'employees'           => \App\Domains\HR\Models\Employee::class,
        'departments'         => \App\Domains\HR\Models\Department::class,
        'positions'           => \App\Domains\HR\Models\Position::class,
        'purchase-orders'     => \App\Domains\Procurement\Models\PurchaseOrder::class,
        'purchase-requests'   => \App\Domains\Procurement\Models\PurchaseRequest::class,
        'vendors'             => \App\Domains\Procurement\Models\Vendor::class,
        'vendor-invoices'     => \App\Domains\AP\Models\VendorInvoice::class,
        'customer-invoices'   => \App\Domains\AR\Models\CustomerInvoice::class,
        'sales-orders'        => \App\Domains\Sales\Models\SalesOrder::class,
        'quotations'          => \App\Domains\Sales\Models\Quotation::class,
        'customers'           => \App\Domains\Sales\Models\Customer::class,
        'payroll-runs'        => \App\Domains\Payroll\Models\PayrollRun::class,
        'payslips'            => \App\Domains\Payroll\Models\Payslip::class,
        'leave-requests'      => \App\Domains\Leave\Models\LeaveRequest::class,
        'leave-types'         => \App\Domains\Leave\Models\LeaveType::class,
        'loan-requests'       => \App\Domains\Loan\Models\LoanRequest::class,
        'production-orders'   => \App\Domains\Production\Models\ProductionOrder::class,
        'boms'                => \App\Domains\Production\Models\BillOfMaterials::class,
        'inspections'         => \App\Domains\QC\Models\Inspection::class,
        'work-orders'         => \App\Domains\Maintenance\Models\WorkOrder::class,
        'fixed-assets'        => \App\Domains\FixedAssets\Models\FixedAsset::class,
        'annual-budgets'      => \App\Domains\Budget\Models\AnnualBudget::class,
        'clients'             => \App\Domains\CRM\Models\Client::class,
        'items'               => \App\Domains\Inventory\Models\Item::class,
        'warehouses'          => \App\Domains\Inventory\Models\Warehouse::class,
        'molds'               => \App\Domains\Mold\Models\Mold::class,
        'attendance-logs'     => \App\Domains\Attendance\Models\AttendanceLog::class,
        'delivery-receipts'   => \App\Domains\Delivery\Models\DeliveryReceipt::class,
    ];

    foreach ($modelMap as $segment => $class) {
        if (str_contains($uri, $segment)) {
            $ulid = firstUlid($class);
            if ($ulid) {
                return $ulid;
            }
        }
    }

    // Fallback: fake ULID -- will produce 404 which is acceptable for param routes
    return '01HZK9FAKE0000000000000000';
}

// -- Collect all API routes ---------------------------------------------------

function collectApiRoutes(): array
{
    $skip = ['login', 'logout', 'password', 'sanctum', 'telescope', 'horizon',
             'pulse', '_ignition', 'export', 'download', 'backup'];

    return collect(Route::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
        ->filter(fn ($r) => ! collect($skip)->contains(fn ($s) => str_contains($r->uri(), $s)))
        ->map(fn ($r) => [
            'method' => collect($r->methods())->first(fn ($m) => $m !== 'HEAD'),
            'uri'    => $r->uri(),
            'name'   => $r->getName() ?? '',
        ])
        ->values()
        ->toArray();
}

// -- The actual smoke test ----------------------------------------------------

it('all API routes return non-500 and non-403 responses', function () {
    $user   = seedAndGetUser('admin');
    $routes = collectApiRoutes();
    $failed = [];

    foreach ($routes as $route) {
        $method = strtolower($route['method']);
        $uri    = $route['uri'];

        // Resolve {ulid} parameters
        $resolved = preg_replace_callback(
            '/\{([a-zA-Z_]+)\}/',
            fn ($m) => str_contains(strtolower($m[1]), 'ulid')
                ? resolveUlid($uri)
                : '01HZK9FAKE0000000000000000',
            $uri
        );

        $payload = in_array($method, ['post', 'put', 'patch'])
            ? ['_smoke' => true, 'name' => 'Smoke Test']
            : [];

        try {
            $response = test()->actingAs($user)->{$method.'Json'}('/'.$resolved, $payload);
            $status   = $response->status();

            // These are always failures -- we never accept them
            if ($status === 500) {
                $body     = $response->json();
                $failed[] = [
                    'method' => strtoupper($method),
                    'uri'    => $resolved,
                    'status' => 500,
                    'error'  => $body['message'] ?? $body['exception'] ?? 'Unknown 500',
                    'trace'  => isset($body['trace'][0])
                        ? ($body['trace'][0]['file'] ?? '').':'.($body['trace'][0]['line'] ?? '')
                        : '',
                ];
            }

            if ($status === 403) {
                $failed[] = [
                    'method' => strtoupper($method),
                    'uri'    => $resolved,
                    'status' => 403,
                    'error'  => 'Forbidden -- policy or permission missing for admin user',
                    'trace'  => '',
                ];
            }

            if ($status === 401) {
                $failed[] = [
                    'method' => strtoupper($method),
                    'uri'    => $resolved,
                    'status' => 401,
                    'error'  => 'Unauthenticated -- auth middleware missing on this route',
                    'trace'  => '',
                ];
            }
        } catch (\Throwable $e) {
            $failed[] = [
                'method' => strtoupper($method),
                'uri'    => $resolved,
                'status' => 'EXCEPTION',
                'error'  => get_class($e).': '.$e->getMessage(),
                'trace'  => $e->getFile().':'.$e->getLine(),
            ];
        }
    }

    // Write detailed report
    $report = [
        'generated_at'  => now()->toISOString(),
        'total_routes'  => count($routes),
        'failures'      => $failed,
        'failure_count' => count($failed),
    ];

    file_put_contents(
        storage_path('logs/smoke-failures.json'),
        json_encode($report, JSON_PRETTY_PRINT)
    );

    if (! empty($failed)) {
        $msg = "\n\nSMOKE TEST FAILURES (".count($failed)."):\n";
        foreach ($failed as $f) {
            $msg .= "\n  [{$f['status']}] {$f['method']} /{$f['uri']}";
            $msg .= "\n          -> {$f['error']}";
            if ($f['trace']) {
                $msg .= "\n          -> {$f['trace']}";
            }
        }
        $msg .= "\n\nFull report: storage/logs/smoke-failures.json\n";
        expect(true)->toBeFalse($msg); // force failure with message
    }

    expect($failed)->toBeEmpty();
});
