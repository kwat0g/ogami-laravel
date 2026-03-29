<?php

declare(strict_types=1);

/**
 * API Routes Smoke Test — Full System Audit
 *
 * Enumerates ALL registered api/v1/* routes and fires a request to each one
 * as a superadmin. GET routes are hit directly; POST/PATCH/PUT/DELETE routes
 * are hit with an empty body (expecting 422 at worst, never 500).
 *
 * Any 500 response indicates a server-side bug that must be fixed.
 * Any 403 on a superadmin request indicates a policy/middleware bug.
 *
 * Run:
 *   ./vendor/bin/pest tests/Feature/Audit/ApiRoutesSmokeTest.php -v --no-coverage
 */

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

beforeAll(function () {
    // Suppress xdebug/coverage for speed
});

beforeEach(function () {
    $this->withoutMiddleware([ThrottleRequests::class]);
});

/**
 * Collect all api/v1 routes for the smoke test dataset.
 */
function getApiRoutes(): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        // Only v1 API routes
        if (! str_starts_with($uri, 'api/v1/')) {
            continue;
        }

        // Skip dangerous/long-running endpoints that break test flow
        if (str_contains($uri, 'backups/run') || str_contains($uri, 'backups/download')) {
            continue;
        }

        $methods = $route->methods();
        // Skip HEAD (duplicate of GET)
        $methods = array_filter($methods, fn ($m) => $m !== 'HEAD');

        foreach ($methods as $method) {
            $name = $route->getName() ?? $uri;

            // Replace route parameters with placeholder values
            $testUri = preg_replace('/\{[^}]+\}/', '1', $uri);

            $routes["{$method} {$uri}"] = [$method, '/' . $testUri, $name, $uri];
        }
    }

    return $routes;
}

test('all GET api/v1 routes return non-500 for superadmin', function () {


    $user = User::where('email', 'superadmin@ogamierp.local')->firstOrFail();

    $routes = getApiRoutes();
    $getRoutes = array_filter($routes, fn ($r) => $r[0] === 'GET');

    $failures = [];
    $results = [
        '2xx' => 0,
        '3xx' => 0,
        '4xx_expected' => 0,  // 404 for placeholder IDs is expected
        '4xx_auth' => 0,      // 403/401 should NOT happen for superadmin
        '5xx' => 0,
    ];

    foreach ($getRoutes as $label => [$method, $testUri, $name, $originalUri]) {
        \Illuminate\Support\Facades\DB::statement('SAVEPOINT smoke_test_req');
        $response = $this->actingAs($user)->getJson($testUri);
        $status = $response->getStatusCode();
        \Illuminate\Support\Facades\DB::statement('ROLLBACK TO SAVEPOINT smoke_test_req');

        if ($status >= 500) {
            $results['5xx']++;
            $body = substr($response->getContent(), 0, 500);
            $failures[] = "[500] GET {$originalUri} => {$status}: {$body}";
        } elseif ($status === 403 || $status === 401) {
            $results['4xx_auth']++;
            $body = substr($response->getContent(), 0, 300);
            $failures[] = "[AUTH] GET {$originalUri} => {$status}: {$body}";
        } elseif ($status === 404 || $status === 405) {
            // Expected for placeholder IDs
            $results['4xx_expected']++;
        } elseif ($status >= 400) {
            $results['4xx_expected']++;
        } elseif ($status >= 300) {
            $results['3xx']++;
        } else {
            $results['2xx']++;
        }
    }

    // Print summary
    echo "\n\n=== GET ROUTES SMOKE TEST RESULTS ===\n";
    echo "Total GET routes: " . count($getRoutes) . "\n";
    echo "  2xx (OK):          {$results['2xx']}\n";
    echo "  3xx (Redirect):    {$results['3xx']}\n";
    echo "  4xx (Expected):    {$results['4xx_expected']}\n";
    echo "  4xx (Auth bugs):   {$results['4xx_auth']}\n";
    echo "  5xx (Server bugs): {$results['5xx']}\n";

    if (! empty($failures)) {
        echo "\n--- FAILURES ---\n";
        foreach ($failures as $f) {
            echo "  {$f}\n";
        }
    }

    // 500 errors are bugs - fail the test
    expect($results['5xx'])->toBe(0, "Found {$results['5xx']} server errors (500) in GET routes:\n" . implode("\n", array_filter($failures, fn ($f) => str_starts_with($f, '[500]'))));
});

test('all POST/PATCH/PUT routes return non-500 for superadmin (empty body => 422 is OK)', function () {


    $user = User::where('email', 'superadmin@ogamierp.local')->firstOrFail();

    $routes = getApiRoutes();
    $writeRoutes = array_filter($routes, fn ($r) => in_array($r[0], ['POST', 'PATCH', 'PUT']));

    $failures = [];
    $results = [
        '2xx' => 0,
        '422' => 0,    // Validation error from empty body - expected and fine
        '4xx_expected' => 0,
        '4xx_auth' => 0,
        '5xx' => 0,
    ];

    foreach ($writeRoutes as $label => [$method, $testUri, $name, $originalUri]) {
        \Illuminate\Support\Facades\DB::statement('SAVEPOINT smoke_test_req');
        $response = $this->actingAs($user)->json($method, $testUri, []);
        $status = $response->getStatusCode();
        \Illuminate\Support\Facades\DB::statement('ROLLBACK TO SAVEPOINT smoke_test_req');

        if ($status >= 500) {
            $results['5xx']++;
            $body = substr($response->getContent(), 0, 500);
            $failures[] = "[500] {$method} {$originalUri} => {$status}: {$body}";
        } elseif ($status === 403 || $status === 401) {
            $results['4xx_auth']++;
            $body = substr($response->getContent(), 0, 300);
            $failures[] = "[AUTH] {$method} {$originalUri} => {$status}: {$body}";
        } elseif ($status === 422) {
            $results['422']++;
        } elseif ($status >= 400) {
            $results['4xx_expected']++;
        } else {
            $results['2xx']++;
        }
    }

    echo "\n\n=== WRITE ROUTES SMOKE TEST RESULTS ===\n";
    echo "Total write routes: " . count($writeRoutes) . "\n";
    echo "  2xx (OK):          {$results['2xx']}\n";
    echo "  422 (Validation):  {$results['422']}\n";
    echo "  4xx (Expected):    {$results['4xx_expected']}\n";
    echo "  4xx (Auth bugs):   {$results['4xx_auth']}\n";
    echo "  5xx (Server bugs): {$results['5xx']}\n";

    if (! empty($failures)) {
        echo "\n--- FAILURES ---\n";
        foreach ($failures as $f) {
            echo "  {$f}\n";
        }
    }

    expect($results['5xx'])->toBe(0, "Found {$results['5xx']} server errors (500) in write routes:\n" . implode("\n", array_filter($failures, fn ($f) => str_starts_with($f, '[500]'))));
});

test('all DELETE routes return non-500 for superadmin', function () {


    $user = User::where('email', 'superadmin@ogamierp.local')->firstOrFail();

    $routes = getApiRoutes();
    $deleteRoutes = array_filter($routes, fn ($r) => $r[0] === 'DELETE');

    $failures = [];
    $results = [
        '2xx' => 0,
        '4xx_expected' => 0,
        '4xx_auth' => 0,
        '5xx' => 0,
    ];

    foreach ($deleteRoutes as $label => [$method, $testUri, $name, $originalUri]) {
        \Illuminate\Support\Facades\DB::statement('SAVEPOINT smoke_test_req');
        $response = $this->actingAs($user)->deleteJson($testUri);
        $status = $response->getStatusCode();
        \Illuminate\Support\Facades\DB::statement('ROLLBACK TO SAVEPOINT smoke_test_req');

        if ($status >= 500) {
            $results['5xx']++;
            $body = substr($response->getContent(), 0, 500);
            $failures[] = "[500] DELETE {$originalUri} => {$status}: {$body}";
        } elseif ($status === 403 || $status === 401) {
            $results['4xx_auth']++;
            $body = substr($response->getContent(), 0, 300);
            $failures[] = "[AUTH] DELETE {$originalUri} => {$status}: {$body}";
        } elseif ($status >= 400) {
            $results['4xx_expected']++;
        } else {
            $results['2xx']++;
        }
    }

    echo "\n\n=== DELETE ROUTES SMOKE TEST RESULTS ===\n";
    echo "Total DELETE routes: " . count($deleteRoutes) . "\n";
    echo "  2xx (OK):          {$results['2xx']}\n";
    echo "  4xx (Expected):    {$results['4xx_expected']}\n";
    echo "  4xx (Auth bugs):   {$results['4xx_auth']}\n";
    echo "  5xx (Server bugs): {$results['5xx']}\n";

    if (! empty($failures)) {
        echo "\n--- FAILURES ---\n";
        foreach ($failures as $f) {
            echo "  {$f}\n";
        }
    }

    expect($results['5xx'])->toBe(0, "Found {$results['5xx']} server errors (500) in DELETE routes:\n" . implode("\n", array_filter($failures, fn ($f) => str_starts_with($f, '[500]'))));
});

test('audit: print full route inventory with status', function () {


    $user = User::where('email', 'superadmin@ogamierp.local')->firstOrFail();

    $routes = getApiRoutes();

    $report = [];
    $statusCounts = [];

    foreach ($routes as $label => [$method, $testUri, $name, $originalUri]) {
        $response = $this->actingAs($user)->json($method, $testUri, []);
        $status = $response->getStatusCode();

        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

        // Only log non-OK statuses for the detailed report
        if ($status >= 400) {
            $body = substr($response->getContent(), 0, 200);
            $report[] = [
                'status' => $status,
                'method' => $method,
                'uri' => $originalUri,
                'response' => $body,
            ];
        }
    }

    // Sort report by status descending (500s first)
    usort($report, fn ($a, $b) => $b['status'] <=> $a['status']);

    echo "\n\n╔══════════════════════════════════════════════════════════╗\n";
    echo "║        FULL API ROUTE AUDIT REPORT                     ║\n";
    echo "╠══════════════════════════════════════════════════════════╣\n";
    echo "║ Total routes: " . count($routes) . str_repeat(' ', 42 - strlen((string) count($routes))) . "║\n";

    ksort($statusCounts);
    foreach ($statusCounts as $code => $count) {
        $line = "║   HTTP {$code}: {$count}";
        echo $line . str_repeat(' ', 59 - strlen($line)) . "║\n";
    }
    echo "╚══════════════════════════════════════════════════════════╝\n";

    if (! empty($report)) {
        echo "\n--- DETAILED ERROR REPORT (4xx/5xx) ---\n\n";
        foreach ($report as $r) {
            echo "  [{$r['status']}] {$r['method']} {$r['uri']}\n";
            echo "    => {$r['response']}\n\n";
        }
    }

    // This test is informational - always passes
    expect(true)->toBeTrue();
});
