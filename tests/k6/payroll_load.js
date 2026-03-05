/**
 * k6 Load Test — Ogami ERP Payroll & Journal Entry Endpoints
 *
 * Target: 50 concurrent virtual users, 30-second duration.
 * Thresholds:
 *   - 95th-percentile response time < 3,000 ms  (p(95)<3000)
 *   - Error rate < 1%                             (rate<0.01)
 *
 * Usage:
 *   # Obtain a bearer token first (e.g. via /api/v1/auth/login) then:
 *   BASE_URL=http://127.0.0.1:8000 API_TOKEN=<token> k6 run tests/k6/payroll_load.js
 *
 * Required environment variables:
 *   BASE_URL   — server base URL  (default: http://127.0.0.1:8000)
 *   API_TOKEN  — Sanctum bearer token for an authenticated user with
 *                payroll.view and journal_entries.view permissions
 *
 * Optional:
 *   VUS        — number of virtual users  (default: 50)
 *   DURATION   — test duration string     (default: '30s')
 */

import http           from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// ---------------------------------------------------------------------------
// Custom metrics
// ---------------------------------------------------------------------------

const payrollErrors  = new Rate('payroll_errors');
const journalErrors  = new Rate('journal_errors');
const payrollTrend   = new Trend('payroll_duration_ms', true);
const journalTrend   = new Trend('journal_duration_ms', true);

// ---------------------------------------------------------------------------
// Test options
// ---------------------------------------------------------------------------

export const options = {
  vus:      parseInt(__ENV.VUS      || '50', 10),
  duration: __ENV.DURATION          || '30s',

  thresholds: {
    // Overall latency — p95 must be under 3 seconds
    'http_req_duration':     ['p(95)<3000'],

    // Overall HTTP failure rate < 1%
    'http_req_failed':        ['rate<0.01'],

    // Per-endpoint latency budgets
    'payroll_duration_ms':   ['p(95)<3000'],
    'journal_duration_ms':   ['p(95)<3000'],

    // Per-endpoint error rates
    'payroll_errors':        ['rate<0.01'],
    'journal_errors':        ['rate<0.01'],
  },
};

// ---------------------------------------------------------------------------
// Shared setup
// ---------------------------------------------------------------------------

const BASE_URL = __ENV.BASE_URL  || 'http://127.0.0.1:8000';
const TOKEN    = __ENV.API_TOKEN || '';

const AUTH_HEADERS = {
  Authorization:  `Bearer ${TOKEN}`,
  'Content-Type': 'application/json',
  Accept:         'application/json',
};

// ---------------------------------------------------------------------------
// Endpoints under test
// ---------------------------------------------------------------------------

const ENDPOINTS = [
  {
    name:    'GET /payroll/runs',
    url:     `${BASE_URL}/api/v1/payroll/runs`,
    method:  'GET',
    errRate: payrollErrors,
    trend:   payrollTrend,
    checks: {
      'payroll/runs — status 200': r => r.status === 200,
      'payroll/runs — has data key': r => {
        try {
          const body = JSON.parse(r.body);
          return typeof body.data !== 'undefined';
        } catch {
          return false;
        }
      },
    },
  },
  {
    name:    'GET /finance/journal-entries',
    url:     `${BASE_URL}/api/v1/finance/journal-entries`,
    method:  'GET',
    errRate: journalErrors,
    trend:   journalTrend,
    checks: {
      'journal-entries — status 200': r => r.status === 200,
      'journal-entries — has data key': r => {
        try {
          const body = JSON.parse(r.body);
          return typeof body.data !== 'undefined';
        } catch {
          return false;
        }
      },
    },
  },
  {
    name:    'GET /hr/employees',
    url:     `${BASE_URL}/api/v1/hr/employees`,
    method:  'GET',
    errRate: null,
    trend:   null,
    checks: {
      'hr/employees — status 200': r => r.status === 200,
    },
  },
  {
    name:    'GET /payroll/runs (paginated page 2)',
    url:     `${BASE_URL}/api/v1/payroll/runs?page=2&per_page=20`,
    method:  'GET',
    errRate: payrollErrors,
    trend:   payrollTrend,
    checks: {
      'payroll/runs page 2 — status 200 or 404': r => r.status === 200 || r.status === 404,
    },
  },
];

// ---------------------------------------------------------------------------
// Main VU function — executed once per VU per iteration
// ---------------------------------------------------------------------------

export default function () {
  // Rotate through endpoints to distribute load evenly
  const endpoint = ENDPOINTS[__VU % ENDPOINTS.length];

  const res = http.request(endpoint.method, endpoint.url, null, {
    headers: AUTH_HEADERS,
    tags:    { endpoint: endpoint.name },
  });

  // Record to endpoint-specific metric if available
  if (endpoint.trend) {
    endpoint.trend.add(res.timings.duration);
  }

  // Check assertions
  const passed = check(res, endpoint.checks);

  // Record per-endpoint error rate
  if (endpoint.errRate) {
    endpoint.errRate.add(!passed);
  }

  // Polite think time — 1 second between requests per VU
  sleep(1);
}

// ---------------------------------------------------------------------------
// Setup: verify server is reachable before load begins
// ---------------------------------------------------------------------------

export function setup() {
  const healthRes = http.get(`${BASE_URL}/up`, {
    headers: { Accept: 'application/json' },
  });

  if (healthRes.status !== 200) {
    throw new Error(
      `Server health check failed: ${BASE_URL}/up returned ${healthRes.status}. ` +
      `Ensure the Laravel server is running and API_TOKEN is set.`
    );
  }

  console.log(`[k6] Server is up: ${BASE_URL}`);
  console.log(`[k6] Bearer token: ${TOKEN ? 'SET (length=' + TOKEN.length + ')' : 'NOT SET — requests will return 401'}`);

  return { baseUrl: BASE_URL };
}

// ---------------------------------------------------------------------------
// Teardown: print summary guidance
// ---------------------------------------------------------------------------

export function teardown(data) {
  console.log('[k6] Load test complete.');
  console.log('[k6] Review thresholds:');
  console.log('      p(95) < 3,000 ms  →  http_req_duration');
  console.log('      error rate < 1%   →  http_req_failed');
  console.log(`[k6] Tested against: ${data.baseUrl}`);
}
