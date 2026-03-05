/**
 * Ogami ERP — Payroll Batch Stress Test (k6)
 *
 * Simulates payroll computation under concurrent read traffic.
 * Does NOT submit new payroll runs — reads only, to avoid DB side effects.
 *
 * Run separately from main.js:
 *   k6 run tests/load/payroll-stress.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';

const BASE_URL = __ENV.K6_BASE_URL || 'http://127.0.0.1:8000';
const ADMIN_EMAIL = __ENV.K6_ADMIN_EMAIL || 'admin@ogamierp.local';
const ADMIN_PASSWORD = __ENV.K6_ADMIN_PASSWORD || 'Admin@1234567890!';

export const options = {
    scenarios: {
        payroll_readers: {
            executor: 'constant-vus',
            vus: 20,
            duration: '30s',
        },
    },
    thresholds: {
        'http_req_duration{endpoint:run_detail}': ['p(95)<2500'],
        'http_req_duration{endpoint:payslip_list}': ['p(95)<2500'],
        http_req_failed: ['rate<0.01'],
    },
};

function authenticate() {
    http.get(`${BASE_URL}/sanctum/csrf-cookie`);
    const res = http.post(
        `${BASE_URL}/api/v1/auth/login`,
        JSON.stringify({ email: ADMIN_EMAIL, password: ADMIN_PASSWORD }),
        { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } },
    );
    if (res.status !== 200) return null;
    return res.json('data.token');
}

export default function () {
    const token = authenticate();
    if (!token) return;

    const headers = {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
    };

    group('run_detail', function () {
        // List runs and pick the first one
        const listRes = http.get(`${BASE_URL}/api/v1/payroll/runs?per_page=5`, { headers });
        if (listRes.status !== 200) return;

        const runs = listRes.json('data');
        if (!runs || runs.length === 0) return;

        const runId = runs[0].id;
        const detailRes = http.get(
            `${BASE_URL}/api/v1/payroll/runs/${runId}`,
            { headers, tags: { endpoint: 'run_detail' } },
        );
        check(detailRes, { 'run detail 200': (r) => r.status === 200 });
    });

    sleep(0.5);

    group('payslip_list', function () {
        const res = http.get(
            `${BASE_URL}/api/v1/employee/me/payslips?page=1`,
            { headers, tags: { endpoint: 'payslip_list' } },
        );
        check(res, { 'payslip list 200 or 404': (r) => r.status === 200 || r.status === 404 });
    });

    sleep(1);
}
