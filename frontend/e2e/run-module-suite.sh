#!/usr/bin/env bash

set -u

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
E2E_DIR="$ROOT_DIR/e2e"
REPORTS_DIR="$E2E_DIR/module-reports"
RUN_ID="$(date +%Y%m%d-%H%M%S)"
RUN_DIR="$REPORTS_DIR/$RUN_ID"
BACKEND_ROOT_DIR="$(cd "$ROOT_DIR/.." && pwd)"

mkdir -p "$RUN_DIR"

declare -A MODULE_FILES
MODULE_FILES[auth]="e2e/specs/01-auth.spec.ts"
MODULE_FILES[hr]="e2e/specs/80-hr.spec.ts"
MODULE_FILES[payroll]="e2e/specs/90-payroll.spec.ts"
MODULE_FILES[accounting]="e2e/specs/50-accounting.spec.ts"
MODULE_FILES[procurement]="e2e/specs/40-procurement.spec.ts"
MODULE_FILES[inventory]="e2e/specs/20-inventory.spec.ts"
MODULE_FILES[production]="e2e/specs/30-production.spec.ts"
MODULE_FILES[qc]="e2e/specs/70-qc.spec.ts"
MODULE_FILES[mold]="e2e/specs/100-mold.spec.ts"
MODULE_FILES[crm]="e2e/specs/110-crm.spec.ts"
MODULE_FILES[delivery]="e2e/specs/60-delivery.spec.ts"
MODULE_FILES[rbac]="e2e/specs/10-rbac.spec.ts"

ALL_MODULES=(auth hr payroll accounting procurement inventory production qc mold crm delivery rbac)

usage() {
  cat <<EOF
Usage: bash e2e/run-module-suite.sh [options]

Options:
  --module <name>      Run only one module.
  --retry-failed       Re-run only modules listed in e2e/module-reports/last-failed-modules.txt.
  --headed             Run tests in headed mode.
  --project <name>     Run using a single Playwright project (for example chromium or lightpanda).
  --all-projects       Run every configured Playwright project.
  --list               Print available modules and exit.
  --help               Show this help.

Examples:
  bash e2e/run-module-suite.sh
  bash e2e/run-module-suite.sh --module inventory
  bash e2e/run-module-suite.sh --retry-failed
  bash e2e/run-module-suite.sh --module payroll --headed
  bash e2e/run-module-suite.sh --module inventory --project lightpanda
  bash e2e/run-module-suite.sh --module rbac --all-projects
EOF
}

list_modules() {
  echo "Available modules:"
  for module in "${ALL_MODULES[@]}"; do
    echo "  - $module"
  done
}

check_servers() {
  if ! curl -s http://localhost:5173 >/dev/null; then
    echo "Frontend server is not running at http://localhost:5173"
    echo "Run: cd frontend && pnpm dev"
    exit 1
  fi

  if ! curl -s http://localhost:8000 >/dev/null; then
    echo "Backend server is not running at http://localhost:8000"
    echo "Run: php artisan serve"
    exit 1
  fi
}

MODULE_ARG=""
RETRY_FAILED=0
HEADED=0
ALL_PROJECTS=0
PROJECT_NAME="chromium"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --module)
      MODULE_ARG="${2:-}"
      shift 2
      ;;
    --retry-failed)
      RETRY_FAILED=1
      shift
      ;;
    --headed)
      HEADED=1
      shift
      ;;
    --project)
      PROJECT_NAME="${2:-}"
      shift 2
      ;;
    --all-projects)
      ALL_PROJECTS=1
      shift
      ;;
    --list)
      list_modules
      exit 0
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ -n "$MODULE_ARG" && $RETRY_FAILED -eq 1 ]]; then
  echo "Use either --module or --retry-failed, not both."
  exit 1
fi

if [[ $ALL_PROJECTS -eq 1 && -n "$PROJECT_NAME" ]]; then
  # Keep compatible behavior: all projects ignores single project selection.
  PROJECT_NAME=""
fi

MODULES_TO_RUN=()

if [[ $RETRY_FAILED -eq 1 ]]; then
  FAILED_FILE="$REPORTS_DIR/last-failed-modules.txt"
  if [[ ! -f "$FAILED_FILE" ]]; then
    echo "No failed module list found at $FAILED_FILE"
    exit 1
  fi
  mapfile -t MODULES_TO_RUN < "$FAILED_FILE"
elif [[ -n "$MODULE_ARG" ]]; then
  if [[ -z "${MODULE_FILES[$MODULE_ARG]:-}" ]]; then
    echo "Unknown module: $MODULE_ARG"
    list_modules
    exit 1
  fi
  MODULES_TO_RUN=("$MODULE_ARG")
else
  MODULES_TO_RUN=("${ALL_MODULES[@]}")
fi

check_servers

cd "$ROOT_DIR" || exit 1

echo "Bootstrapping Playwright auth setup..."
if ! pnpm exec playwright test --project=setup >"$RUN_DIR/00-setup.log" 2>&1; then
  echo "Setup project failed. See: $RUN_DIR/00-setup.log"
  exit 1
fi

echo "Running module suite: ${MODULES_TO_RUN[*]}"
echo "Run artifacts: $RUN_DIR"

SUMMARY_FILE="$RUN_DIR/summary.md"
FAILED_MODULES_FILE="$RUN_DIR/failed-modules.txt"
LAST_FAILED_FILE="$REPORTS_DIR/last-failed-modules.txt"

touch "$FAILED_MODULES_FILE"

echo "# Frontend Module Suite Summary" > "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "Run ID: $RUN_ID" >> "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "| Module | Result | Specs |" >> "$SUMMARY_FILE"
echo "|---|---|---|" >> "$SUMMARY_FILE"

pass_count=0
fail_count=0
index=1

for module in "${MODULES_TO_RUN[@]}"; do
  specs="${MODULE_FILES[$module]}"
  log_file="$RUN_DIR/$(printf "%02d" "$index")-${module}.log"
  result="PASS"

  echo ""
  echo "===== Module: $module ====="
  echo "Specs: $specs"

  # Reset auth/login throttles between module runs for deterministic CI/local execution.
  (
    cd "$BACKEND_ROOT_DIR" || exit 0
    php artisan cache:clear >/dev/null 2>&1 || true
  )

  cmd=(pnpm exec playwright test --workers=1)
  if [[ $ALL_PROJECTS -eq 0 ]]; then
    cmd+=(--project="$PROJECT_NAME")
  fi
  if [[ $HEADED -eq 1 ]]; then
    cmd+=(--headed)
  fi

  # shellcheck disable=SC2206
  module_specs=($specs)
  cmd+=("${module_specs[@]}")

  if ! "${cmd[@]}" >"$log_file" 2>&1; then
    result="FAIL"
    echo "$module" >> "$FAILED_MODULES_FILE"
    fail_count=$((fail_count + 1))
  else
    pass_count=$((pass_count + 1))
  fi

  echo "| $module | $result | $specs |" >> "$SUMMARY_FILE"
  index=$((index + 1))
done

cp "$FAILED_MODULES_FILE" "$LAST_FAILED_FILE"

echo "" >> "$SUMMARY_FILE"
echo "Passed modules: $pass_count" >> "$SUMMARY_FILE"
echo "Failed modules: $fail_count" >> "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"

if [[ $fail_count -gt 0 ]]; then
  echo "## Retry Failed Modules" >> "$SUMMARY_FILE"
  echo "" >> "$SUMMARY_FILE"
  echo "bash e2e/run-module-suite.sh --retry-failed" >> "$SUMMARY_FILE"
fi

echo ""
echo "Done. Passed: $pass_count, Failed: $fail_count"
echo "Summary: $SUMMARY_FILE"
echo "Failed modules list: $FAILED_MODULES_FILE"

if [[ $fail_count -gt 0 ]]; then
  exit 1
fi

exit 0
