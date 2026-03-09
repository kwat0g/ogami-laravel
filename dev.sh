#!/usr/bin/env bash
set -e

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$ROOT/storage/logs"

# ── Flags ────────────────────────────────────────────────────────────────────
# --minimal    : skip Reverb (WebSocket) and queue worker (fastest startup)
# --with-reverb: start Reverb WebSocket server (needed for real-time features)
MINIMAL=false
WITH_REVERB=false
for arg in "$@"; do
  case $arg in
    --minimal)    MINIMAL=true ;;
    --with-reverb) WITH_REVERB=true ;;
  esac
done

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

# ── Cleanup on exit ───────────────────────────────────────────────────────────
cleanup() {
  echo -e "\n${YELLOW}Shutting down...${RESET}"
  kill "$LARAVEL_PID" "$VITE_PID" "$KEEPALIVE_PID" 2>/dev/null
  [[ -n "${QUEUE_PID:-}" ]]  && kill "$QUEUE_PID"  2>/dev/null
  [[ -n "${REVERB_PID:-}" ]] && kill "$REVERB_PID" 2>/dev/null
  wait "$LARAVEL_PID" "$VITE_PID" 2>/dev/null
  echo -e "${GREEN}Done.${RESET}"
}
trap cleanup EXIT INT TERM

# ── Check Docker containers ───────────────────────────────────────────────────
echo -e "${BOLD}${CYAN}Ogami ERP — Dev Server${RESET}"
echo -e "────────────────────────────────────────"
$MINIMAL    && echo -e "${YELLOW}Mode: minimal (no queue, no Reverb)${RESET}"
$WITH_REVERB && echo -e "${CYAN}Reverb: enabled${RESET}"

# Each entry: "container-name|host-port|service-label"
# If the host port is already bound (e.g. by ogami_postgres from docker-compose)
# we skip starting the named container so there's no bind conflict.
declare -A SERVICE_PORT=( [ogami_postgres]=5432 [ogami_redis]=6379 )

for container in ogami_postgres ogami_redis; do
  port="${SERVICE_PORT[$container]}"
  # Check if ANY container is already binding this port
  if docker ps --format '{{.Ports}}' | grep -q ":${port}->"; then
    running_name=$(docker ps --format '{{.Names}}\t{{.Ports}}' | awk -v p=":${port}->" '$0 ~ p {print $1}')
    echo -e "${GREEN}✓ port ${port} already served by ${running_name} (skipping ${container})${RESET}"
  elif docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
    echo -e "${GREEN}✓ ${container} is running${RESET}"
  else
    echo -e "${YELLOW}Starting container: ${container}${RESET}"
    docker start "$container"
  fi
done

# ── Start Laravel ─────────────────────────────────────────────────────────────
echo -e "\n${CYAN}Starting Laravel on http://127.0.0.1:8000 (${BOLD}4 workers${RESET}${CYAN})${RESET}"
cd "$ROOT"
php artisan config:clear -q
# Cache config to avoid re-reading 50+ config files on every cold worker boot
php artisan config:cache -q
# Cache event→listener map so auto-discovered listeners in app/Listeners/ are
# always registered. Without this, queued listeners (e.g. LinkGoodsReceiptToInventory,
# CreateApInvoiceOnThreeWayMatch) silently do nothing after a config:clear.
php artisan event:cache -q
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1 --port=8000 > "$LOG_DIR/serve.log" 2>&1 &
LARAVEL_PID=$!

# Wait for Laravel to be ready
for i in {1..20}; do
  if curl -sf http://127.0.0.1:8000/up >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Laravel ready (PID $LARAVEL_PID)${RESET}"
    break
  fi
  sleep 0.5
done

# Pre-warm all PHP_CLI_SERVER_WORKERS worker processes so users don't see
# the ~500ms cold-start on the first few requests after login.
echo -e "${CYAN}Pre-warming PHP workers…${RESET}"
WARM_PIDS=()
for i in {1..4}; do
  curl -s http://127.0.0.1:8000/api/v1/auth/me >/dev/null 2>&1 &
  WARM_PIDS+=($!)
done
wait "${WARM_PIDS[@]}" || true
echo -e "${GREEN}✓ Workers warmed${RESET}"

# Background keepalive — ping workers every 45 s to prevent DB/Redis
# TCP connections from timing out. 4 pings per interval instead of 8.
keep_workers_alive() {
  while true; do
    sleep 45
    for i in {1..4}; do
      curl -s http://127.0.0.1:8000/api/v1/auth/me >/dev/null 2>&1 &
    done
    wait
  done
}
keep_workers_alive &
KEEPALIVE_PID=$!
echo -e "${GREEN}✓ Worker keepalive started (PID $KEEPALIVE_PID, every 45s)${RESET}"

# ── Start Queue Worker ───────────────────────────────────────────────────────
if ! $MINIMAL; then
  echo -e "${CYAN}Starting queue worker${RESET}"
  cd "$ROOT"
  php artisan queue:work redis --queue=notifications,default,payroll,computations --tries=3 --sleep=3 --timeout=600 > "$LOG_DIR/queue.log" 2>&1 &
  QUEUE_PID=$!
  echo -e "${GREEN}✓ Queue worker started (PID $QUEUE_PID)${RESET}"
else
  echo -e "${YELLOW}⚠ Queue worker skipped (--minimal)${RESET}"
fi

# ── Start Reverb (WebSocket server) ──────────────────────────────────────────
if $WITH_REVERB && ! $MINIMAL; then
  echo -e "${CYAN}Starting Reverb on ws://localhost:8080${RESET}"
  cd "$ROOT"
  php artisan reverb:start --host=0.0.0.0 --port=8080 > "$LOG_DIR/reverb.log" 2>&1 &
  REVERB_PID=$!
  echo -e "${GREEN}✓ Reverb started (PID $REVERB_PID)${RESET}"
else
  echo -e "${YELLOW}⚠ Reverb skipped (use --with-reverb to enable WebSockets)${RESET}"
fi

# ── Start Vite ────────────────────────────────────────────────────────────────
echo -e "${CYAN}Starting Vite on http://localhost:5173${RESET}"
cd "$ROOT/frontend"
pnpm dev > "$LOG_DIR/vite.log" 2>&1 &
VITE_PID=$!

# Wait for Vite to be ready
for i in {1..20}; do
  if curl -sf http://localhost:5173 >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Vite ready (PID $VITE_PID)${RESET}"
    break
  fi
  sleep 0.5
done

# ── Summary ───────────────────────────────────────────────────────────────────
echo -e "\n${BOLD}────────────────────────────────────────${RESET}"
echo -e "  ${GREEN}Frontend:${RESET}  http://localhost:5173"
echo -e "  ${GREEN}Backend:${RESET}   http://127.0.0.1:8000"
$WITH_REVERB && ! $MINIMAL && echo -e "  ${GREEN}WebSocket:${RESET} ws://localhost:8080"
echo -e "  ${GREEN}Logs:${RESET}      storage/logs/serve.log"
! $MINIMAL && echo -e "            storage/logs/queue.log"
$WITH_REVERB && ! $MINIMAL && echo -e "            storage/logs/reverb.log"
echo -e "            storage/logs/vite.log"
echo -e "${BOLD}────────────────────────────────────────${RESET}"
echo -e "  ${YELLOW}Ctrl+C to stop all servers${RESET}"
echo -e "  Tip: ${CYAN}npm run dev -- --minimal${RESET} for a lighter session\n"

# ── Tail logs ────────────────────────────────────────────────────────────────
LOG_FILES=("$LOG_DIR/serve.log" "$LOG_DIR/vite.log")
! $MINIMAL && LOG_FILES+=("$LOG_DIR/queue.log")
$WITH_REVERB && ! $MINIMAL && LOG_FILES+=("$LOG_DIR/reverb.log")
tail -f "${LOG_FILES[@]}" &
TAIL_PID=$!

# Keep script alive — wait for main processes to die
WAIT_PIDS=("$LARAVEL_PID" "$VITE_PID")
! $MINIMAL && [[ -n "${QUEUE_PID:-}" ]]  && WAIT_PIDS+=("$QUEUE_PID")
$WITH_REVERB && [[ -n "${REVERB_PID:-}" ]] && WAIT_PIDS+=("$REVERB_PID")
wait "${WAIT_PIDS[@]}"
kill "$TAIL_PID" 2>/dev/null
