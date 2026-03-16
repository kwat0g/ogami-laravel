#!/bin/bash
#
# Ogami ERP Production Health Check Script
# Run this on your VPS to verify everything is working 100%
#

set -e  # Exit on error

# Configuration
APP_DIR="${APP_DIR:-/var/www/ogami}"
DOMAIN="${DOMAIN:-ogamiph.dev}"
LOG_FILE="/var/log/ogami-health-$(date +%Y%m%d_%H%M).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
PASS=0
FAIL=0
WARN=0

# Logging function
log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Test result functions
pass() {
    log "${GREEN}✅ PASS:${NC} $1"
    ((PASS++))
}

fail() {
    log "${RED}❌ FAIL:${NC} $1"
    ((FAIL++))
}

warn() {
    log "${YELLOW}⚠️  WARN:${NC} $1"
    ((WARN++))
}

# Header
log "=========================================="
log "  Ogami ERP Production Health Check"
log "  Started: $(date)"
log "  Server: $(hostname)"
log "=========================================="
log ""

# 1. System Resources
log "--- 1. System Resources ---"

# Disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -lt 80 ]; then
    pass "Disk usage: ${DISK_USAGE}%"
elif [ "$DISK_USAGE" -lt 90 ]; then
    warn "Disk usage: ${DISK_USAGE}% (getting full)"
else
    fail "Disk usage: ${DISK_USAGE}% (critical)"
fi

# Memory
MEM_INFO=$(free | grep Mem)
MEM_TOTAL=$(echo $MEM_INFO | awk '{print $2}')
MEM_AVAIL=$(echo $MEM_INFO | awk '{print $7}')
MEM_PCT=$((100 * MEM_AVAIL / MEM_TOTAL))
if [ "$MEM_PCT" -gt 20 ]; then
    pass "Memory available: ${MEM_PCT}% ($((MEM_AVAIL/1024))MB / $((MEM_TOTAL/1024))MB)"
else
    warn "Memory available: ${MEM_PCT}% (low)"
fi

# CPU load (1-min average)
LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
CPU_CORES=$(nproc)
LOAD_PCT=$(echo "scale=0; $LOAD * 100 / $CPU_CORES" | bc -l 2>/dev/null || echo "0")
if [ "$LOAD_PCT" -lt 70 ]; then
    pass "CPU load: ${LOAD} (on ${CPU_CORES} cores)"
else
    warn "CPU load: ${LOAD} (high)"
fi

log ""

# 2. Service Status
log "--- 2. Service Status ---"

services=("nginx" "php8.3-fpm" "postgresql" "redis-server" "supervisor")
for service in "${services[@]}"; do
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        pass "Service $service is running"
    else
        fail "Service $service is NOT running"
    fi
done

log ""

# 3. Application Health
log "--- 3. Application Health ---"

cd "$APP_DIR" || { fail "Cannot access $APP_DIR"; exit 1; }

# Check Laravel can bootstrap
if php artisan --version >/dev/null 2>&1; then
    LARAVEL_VERSION=$(php artisan --version 2>/dev/null)
    pass "Laravel bootstrap: $LARAVEL_VERSION"
else
    fail "Laravel bootstrap failed"
fi

# Check database connection
if php artisan tinker --execute="DB::connection()->getPdo();" >/dev/null 2>&1; then
    pass "Database connection: OK"
else
    fail "Database connection: FAILED"
fi

# Check cache stores
if php artisan tinker --execute="Cache::store('redis')->put('health_check', 'ok', 10);" >/dev/null 2>&1; then
    pass "Redis cache: OK"
else
    warn "Redis cache: Issues detected"
fi

# Check storage permissions
if [ "$(stat -c '%U:%G' storage/logs 2>/dev/null)" = "www-data:www-data" ]; then
    pass "Storage permissions: Correct"
else
    warn "Storage permissions: May need fixing (run: chown -R www-data:www-data storage)"
fi

log ""

# 4. API Endpoints
log "--- 4. API Endpoints ---"

# Health endpoint
HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/health" 2>/dev/null || echo "000")
if [ "$HEALTH_STATUS" = "200" ]; then
    HEALTH_RESPONSE=$(curl -s "https://$DOMAIN/api/health" 2>/dev/null)
    pass "Health endpoint: 200 OK"
    log "    Response: $HEALTH_RESPONSE"
else
    fail "Health endpoint: HTTP $HEALTH_STATUS"
fi

# Test a few key endpoints (without auth)
ENDPOINTS=(
    "/api/health:200"
)

for endpoint in "${ENDPOINTS[@]}"; do
    IFS=':' read -r path expected <<< "$endpoint"
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN$path" 2>/dev/null || echo "000")
    if [ "$STATUS" = "$expected" ]; then
        pass "Endpoint $path: HTTP $STATUS"
    else
        fail "Endpoint $path: Expected $expected, got $STATUS"
    fi
done

log ""

# 5. SSL Certificate
log "--- 5. SSL Certificate ---"

SSL_EXP=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
if [ -n "$SSL_EXP" ]; then
    EXP_EPOCH=$(date -d "$SSL_EXP" +%s 2>/dev/null || echo "0")
    NOW_EPOCH=$(date +%s)
    DAYS_LEFT=$(( (EXP_EPOCH - NOW_EPOCH) / 86400 ))
    
    if [ "$DAYS_LEFT" -gt 30 ]; then
        pass "SSL certificate: Valid for $DAYS_LEFT days"
    elif [ "$DAYS_LEFT" -gt 7 ]; then
        warn "SSL certificate: Expires in $DAYS_LEFT days"
    else
        fail "SSL certificate: Expires in $DAYS_LEFT days (critical!)"
    fi
else
    fail "SSL certificate: Could not retrieve expiry"
fi

log ""

# 6. Database Health
log "--- 6. Database Health ---"

# Connection count
CONN_COUNT=$(sudo -u postgres psql -d ogami_erp -t -c "SELECT count(*) FROM pg_stat_activity WHERE datname = 'ogami_erp';" 2>/dev/null | xargs)
if [ -n "$CONN_COUNT" ]; then
    if [ "$CONN_COUNT" -lt 50 ]; then
        pass "DB connections: $CONN_COUNT (healthy)"
    else
        warn "DB connections: $CONN_COUNT (high)"
    fi
else
    warn "DB connections: Could not check"
fi

# Migration status
MIGRATION_STATUS=$(php artisan migrate:status --pending 2>/dev/null | grep -c "Pending" || echo "0")
if [ "$MIGRATION_STATUS" = "0" ]; then
    pass "Migrations: All up to date"
else
    fail "Migrations: $MIGRATION_STATUS pending"
fi

log ""

# 7. Queue & Jobs
log "--- 7. Queue & Jobs ---"

# Check failed jobs
FAILED_JOBS=$(php artisan queue:failed 2>/dev/null | wc -l)
if [ "$FAILED_JOBS" -lt 10 ]; then
    pass "Failed jobs: $FAILED_JOBS"
else
    warn "Failed jobs: $FAILED_JOBS (consider retrying or clearing)"
fi

# Horizon status (if installed)
if php artisan horizon:status >/dev/null 2>&1; then
    HORIZON_STATUS=$(php artisan horizon:status 2>/dev/null | grep -i "running" || echo "")
    if [ -n "$HORIZON_STATUS" ]; then
        pass "Horizon: Running"
    else
        warn "Horizon: Not running or issues detected"
    fi
else
    log "    Horizon: Not installed (optional)"
fi

log ""

# 8. Recent Errors
log "--- 8. Recent Log Errors ---"

ERROR_COUNT=$(grep -c "ERROR\|CRITICAL" storage/logs/laravel.log 2>/dev/null || echo "0")
if [ "$ERROR_COUNT" -eq 0 ]; then
    pass "Recent errors: None found"
else
    warn "Recent errors: $ERROR_COUNT found in last 24h"
    log "    Last 3 errors:"
    grep "ERROR\|CRITICAL" storage/logs/laravel.log 2>/dev/null | tail -3 | sed 's/^/    /'
fi

log ""

# 9. File Integrity
log "--- 9. File Integrity ---"

# Check .env exists and is readable
if [ -f ".env" ] && [ -r ".env" ]; then
    ENV_PERMS=$(stat -c '%a' .env)
    if [ "$ENV_PERMS" = "644" ] || [ "$ENV_PERMS" = "640" ]; then
        pass ".env file: Exists with correct permissions"
    else
        warn ".env file: Permissions are $ENV_PERMS (expected 644 or 640)"
    fi
else
    fail ".env file: Missing or not readable"
fi

# Check critical directories
for dir in "storage" "bootstrap/cache" "public"; do
    if [ -d "$dir" ] && [ -w "$dir" ]; then
        pass "Directory $dir: Writable"
    else
        fail "Directory $dir: Not writable"
    fi
done

log ""

# Summary
log "=========================================="
log "  Health Check Summary"
log "=========================================="
log ""
log "${GREEN}Passed:  $PASS${NC}"
log "${YELLOW}Warnings: $WARN${NC}"
log "${RED}Failed:  $FAIL${NC}"
log ""

if [ $FAIL -eq 0 ]; then
    log "${GREEN}✅ OVERALL: System is HEALTHY${NC}"
    EXIT_CODE=0
elif [ $FAIL -le 2 ]; then
    log "${YELLOW}⚠️  OVERALL: System is functional with minor issues${NC}"
    EXIT_CODE=1
else
    log "${RED}❌ OVERALL: System has CRITICAL issues${NC}"
    EXIT_CODE=2
fi

log ""
log "Full log saved to: $LOG_FILE"
log "Check completed: $(date)"
log "=========================================="

exit $EXIT_CODE
