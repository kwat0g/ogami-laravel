#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/frontend"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="$ROOT_DIR/storage/logs/thesis-validation/$TIMESTAMP"
mkdir -p "$OUT_DIR"

FRONTEND_LOG="$OUT_DIR/frontend-module-suite.log"
BACKEND_LOG="$OUT_DIR/backend-workflow-tests.log"
SUMMARY_FILE="$OUT_DIR/summary.txt"

check_servers() {
  if ! curl -s http://localhost:5173 >/dev/null; then
    echo "ERROR: Frontend server is not running at http://localhost:5173"
    echo "Start with: cd frontend && pnpm dev"
    exit 1
  fi

  if ! curl -s http://localhost:8000 >/dev/null; then
    echo "ERROR: Backend server is not running at http://localhost:8000"
    echo "Start with: php artisan serve"
    exit 1
  fi
}

prepare_auth_data() {
  echo "[prep] Seeding RBAC and test accounts for Playwright auth setup..."
  (
    cd "$ROOT_DIR"
    php artisan db:seed --class=RolePermissionSeeder >/dev/null
    php artisan db:seed --class=SampleAccountsSeeder >/dev/null
    php artisan db:seed --class=TestAccountsSeeder >/dev/null
  )
}

run_frontend_suite() {
  echo "[1/2] Running frontend module suite (Lightpanda)..."
  (
    cd "$FRONTEND_DIR"
    bash e2e/run-module-suite.sh --project lightpanda
  ) | tee "$FRONTEND_LOG"
}

run_backend_workflow_tests() {
  echo "[2/2] Running backend full workflow integration chains..."
  (
    cd "$ROOT_DIR"
    ./vendor/bin/pest \
      tests/Integration/ClientOrderToDeliveryWorkflowTest.php \
      tests/Integration/ProcurementToInventoryTest.php \
      tests/Integration/ProductionToInventoryTest.php \
      tests/Integration/LeaveAttendancePayrollTest.php
  ) | tee "$BACKEND_LOG"
}

write_summary() {
  {
    echo "Thesis Validation Summary"
    echo "Timestamp: $TIMESTAMP"
    echo ""
    echo "Frontend Suite Log: $FRONTEND_LOG"
    echo "Backend Workflow Log: $BACKEND_LOG"
    echo ""
    echo "Result: PASS"
  } > "$SUMMARY_FILE"

  echo ""
  echo "Validation complete."
  echo "Summary: $SUMMARY_FILE"
}

check_servers
prepare_auth_data
run_frontend_suite
run_backend_workflow_tests
write_summary
