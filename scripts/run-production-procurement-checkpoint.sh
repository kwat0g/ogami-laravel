#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/frontend"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="$ROOT_DIR/storage/logs/checkpoints/$TIMESTAMP"
mkdir -p "$OUT_DIR"

BACKEND_LOG="$OUT_DIR/backend-checkpoint.log"
FRONTEND_UNIT_LOG="$OUT_DIR/frontend-bom-history-unit.log"
FRONTEND_TYPECHECK_LOG="$OUT_DIR/frontend-typecheck.log"
SUMMARY_FILE="$OUT_DIR/summary.txt"

run_backend_checkpoint() {
  echo "[1/3] Running deterministic backend checkpoint (fresh seed + focused suite)..."
  (
    cd "$ROOT_DIR"
    php artisan migrate:fresh --seed
    ./vendor/bin/pest \
      tests/Integration/ProcurementToBomCostingTest.php \
      tests/Feature/Enhancement/BomCostingTest.php \
      tests/Feature/Production \
      tests/Feature/Procurement/GoodsReceiptQcWorkflowTest.php
  ) | tee "$BACKEND_LOG"
}

run_frontend_unit_checkpoint() {
  echo "[2/3] Running frontend BOM history unit checkpoint..."
  (
    cd "$FRONTEND_DIR"
    pnpm test src/pages/production/BomDetailPage.test.tsx
  ) | tee "$FRONTEND_UNIT_LOG"
}

run_frontend_typecheck() {
  echo "[3/3] Running frontend typecheck checkpoint..."
  (
    cd "$FRONTEND_DIR"
    pnpm typecheck
  ) | tee "$FRONTEND_TYPECHECK_LOG"
}

write_summary() {
  {
    echo "Production/Procurement/BOM Checkpoint Summary"
    echo "Timestamp: $TIMESTAMP"
    echo ""
    echo "Backend log: $BACKEND_LOG"
    echo "Frontend unit log: $FRONTEND_UNIT_LOG"
    echo "Frontend typecheck log: $FRONTEND_TYPECHECK_LOG"
    echo ""
    echo "Result: PASS"
  } > "$SUMMARY_FILE"

  echo ""
  echo "Checkpoint complete."
  echo "Summary: $SUMMARY_FILE"
}

run_backend_checkpoint
run_frontend_unit_checkpoint
run_frontend_typecheck
write_summary
