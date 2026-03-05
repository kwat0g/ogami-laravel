/**
 * Ogami ERP — k6 Load Test Suite
 * Sprint 18 | Target: p95 < 3s for all endpoints, 50 concurrent users
 *
 * Prerequisites:
 *   npm install -g k6   (or: brew install k6 / apt install k6)
 *
 * Run (from project root):
 *   k6 run tests/load/main.js
 *   k6 run --out json=tests/load/results.json tests/load/main.js
 *   k6 run --vus 50 --duration 60s tests/load/main.js   # override concurrency
 *
 * Environment variables:
 *   K6_BASE_URL   default: http://127.0.0.1:8000
 *   K6_ADMIN_EMAIL    default: admin@ogamierp.local
 *   K6_ADMIN_PASSWORD default: Admin@1234567890!
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// ─── Config ──────────────────────────────────────────────────────────────────

const BASE_URL = __ENV.K6_BASE_URL || 'http://127.0.0.1:8000';
const ADMIN_EMAIL = __ENV.K6_ADMIN_EMAIL || 'admin@ogamierp.local';
const ADMIN_PASSWORD = __ENV.K6_ADMIN_PASSWORD || 'Admin@1234567890!';

export const options = {
    scenarios: {
        // Ramp up to 50 VUs, hold for 60 s, ramp down
        sustained_load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '15s', target: 20 },  // warm-up
                { duration: '60s', target: 50 },  // sustained peak
                { duration: '10s', target: 0 },   // ramp-down
            ],
        },
    },
    thresholds: {
        // Global: 95th percentile response time under 3 s
        http_req_duration: ['p(95)<3000'],
        // Endpoint-specific thresholds
        'http_req_duration{endpoint:employees_list}': ['p(95)<2000'],
        'http_req_duration{endpoint:payroll_runs_list}': ['p(95)<2000'],
        'http_req_duration{endpoint:trial_balance}': ['p(95)<3000'],
        'http_req_duration{endpoint:leave_list}': ['p(95)<2000'],
        'http_req_duration{endpoint:dashboard_kpis}': ['p(95)<2000'],
        // Error rate: fewer than 1% failures
        http_req_failed: ['rate<0.01'],
    },
};

// ─── Custom metrics ───────────────────────────────────────────────────────────

const errorCount = new Counter('errors');
const successRate = new Rate('success_rate');

// ─── Auth helper — runs once per VU (setup phase) ────────────────────────────

function authenticate() {
    // 1. Get CSRF cookie (Sanctum SPA requirement)
    http.get(`${BASE_URL}/sanctum/csrf-cookie`);

    // 2. Login
    const loginResponse = http.post(
        `${BASE_URL}/api/v1/auth/login`,
        JSON.stringify({ email: ADMIN_EMAIL, password: ADMIN_PASSWORD }),
        { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } },
    );

    const ok = check(loginResponse, {
        'login status 200': (r) => r.status === 200,
        'login returns token': (r) => r.json('data.token') !== undefined,
    });

    if (!ok) {
        errorCount.add(1);
        return null;
    }

    return loginResponse.json('data.token');
}

// ─── Main VU script ───────────────────────────────────────────────────────────

export default function () {
    const token = authenticate();
    if (!token) return;

    const headers = {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    // ── 1. Employee list ──────────────────────────────────────────────────────
    group('employees_list', function () {
        const res = http.get(
            `${BASE_URL}/api/v1/hr/employees?per_page=25&page=1`,
            { headers, tags: { endpoint: 'employees_list' } },
        );
        const ok = check(res, {
            'employees list 200': (r) => r.status === 200,
            'employees list has data': (r) => Array.isArray(r.json('data')),
        });
        successRate.add(ok);
        if (!ok) errorCount.add(1);
    });

    sleep(0.5);

    // ── 2. Payroll runs list ──────────────────────────────────────────────────
    group('payroll_runs_list', function () {
        const res = http.get(
            `${BASE_URL}/api/v1/payroll/runs?per_page=15`,
            { headers, tags: { endpoint: 'payroll_runs_list' } },
        );
        const ok = check(res, {
            'payroll runs 200': (r) => r.status === 200,
            'payroll runs has data': (r) => r.json('data') !== undefined,
        });
        successRate.add(ok);
        if (!ok) errorCount.add(1);
    });

    sleep(0.5);

    // ── 3. Trial balance (most expensive report query) ────────────────────────
    group('trial_balance', function () {
        const currentYear = new Date().getFullYear();
        const res = http.get(
            `${BASE_URL}/api/v1/finance/reports/trial-balance?as_of_date=${currentYear}-12-31`,
            { headers, tags: { endpoint: 'trial_balance' } },
        );
        const ok = check(res, {
            'trial balance 200': (r) => r.status === 200,
        });
        successRate.add(ok);
        if (!ok) errorCount.add(1);
    });

    sleep(0.5);

    // ── 4. Leave requests list ────────────────────────────────────────────────
    group('leave_list', function () {
        const res = http.get(
            `${BASE_URL}/api/v1/leave/requests?per_page=25&status=pending`,
            { headers, tags: { endpoint: 'leave_list' } },
        );
        const ok = check(res, {
            'leave list 200': (r) => r.status === 200,
        });
        successRate.add(ok);
        if (!ok) errorCount.add(1);
    });

    sleep(0.5);

    // ── 5. Dashboard KPIs ─────────────────────────────────────────────────────
    group('dashboard_kpis', function () {
        const res = http.get(
            `${BASE_URL}/api/v1/admin/dashboard/stats`,
            { headers, tags: { endpoint: 'dashboard_kpis' } },
        );
        const ok = check(res, {
            'dashboard kpis 200': (r) => r.status === 200,
        });
        successRate.add(ok);
        if (!ok) errorCount.add(1);
    });

    sleep(1);
}

// ─── Teardown ─────────────────────────────────────────────────────────────────

export function teardown() {
    console.log('Load test complete. Check thresholds above for pass/fail.');
}
