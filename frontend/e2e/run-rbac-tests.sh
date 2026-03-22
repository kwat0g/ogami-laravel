#!/bin/bash
# Run RBAC E2E Tests Only
# 
# This script runs only the RBAC comprehensive tests

set -e

echo "═══════════════════════════════════════════════════════════════"
echo "🧪 Ogami ERP RBAC Test Runner"
echo "═══════════════════════════════════════════════════════════════"

# Check if servers are running
echo ""
echo "📡 Checking if servers are running..."

if ! curl -s http://localhost:5173 > /dev/null; then
    echo "❌ Frontend server not running on port 5173"
    echo "   Run: cd frontend && pnpm dev"
    exit 1
fi

if ! curl -s http://localhost:8000 > /dev/null; then
    echo "❌ Backend server not running on port 8000"
    echo "   Run: php artisan serve"
    exit 1
fi

echo "✅ Both servers are running"

# Clear rate limits and unlock accounts
echo ""
echo "🧹 Clearing rate limits and unlocking accounts..."
cd ../..

# Clear all caches (Laravel rate limiter + app cache)
php artisan cache:clear 2>/dev/null || true
php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush();" 2>/dev/null || true

# Unlock manufacturing accounts
php artisan tinker --execute="
\$emails = [
    'prod.manager@ogamierp.local',
    'qc.manager@ogamierp.local',
    'mold.manager@ogamierp.local',
    'plant.manager@ogamierp.local',
    'vp@ogamierp.local',
    'warehouse.head@ogamierp.local',
    'production.head@ogamierp.local',
    'qcqa.head@ogamierp.local',
    'maintenance.head@ogamierp.local',
];
App\Models\User::whereIn('email', \$emails)
    ->update(['failed_login_attempts' => 0, 'locked_until' => null]);
echo 'Accounts unlocked' . PHP_EOL;
" 2>/dev/null || true

cd frontend/e2e
echo "✅ Setup complete"

# Change to frontend directory
cd ..

# Parse arguments
FILTER=""
HEADED=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --production)
            FILTER="-g \"Production Department\""
            shift
            ;;
        --hr)
            FILTER="-g \"HR Department\""
            shift
            ;;
        --accounting)
            FILTER="-g \"Accounting Department\""
            shift
            ;;
        --cross)
            FILTER="-g \"Cross-Cutting\""
            shift
            ;;
        --headed)
            HEADED="--headed"
            shift
            ;;
        --help)
            echo "Usage: ./run-rbac-tests.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --production  Run only Production department tests"
            echo "  --hr          Run only HR department tests"
            echo "  --accounting  Run only Accounting department tests"
            echo "  --cross       Run only cross-cutting forbidden access tests"
            echo "  --headed      Run with visible browser"
            echo "  --help        Show this help"
            echo ""
            echo "Examples:"
            echo "  ./run-rbac-tests.sh                    # Run all RBAC tests"
            echo "  ./run-rbac-tests.sh --production       # Run Production tests only"
            echo "  ./run-rbac-tests.sh --cross --headed   # Run cross-cutting tests with UI"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Run tests
echo ""
echo "🚀 Running RBAC tests..."
echo "═══════════════════════════════════════════════════════════════"
echo "⚠️  Note: Tests run slowly due to rate limiting protection"
echo "   Estimated time: 10-15 minutes for full suite"
echo "═══════════════════════════════════════════════════════════════"
echo ""

if [ -n "$FILTER" ]; then
    echo "Running with filter: $FILTER"
    eval pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 $FILTER $HEADED
else
    echo "Running all 40 RBAC tests"
    pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 $HEADED
fi

# Show results
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "✅ Tests completed!"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "📊 To view HTML report:"
echo "   pnpm exec playwright show-report e2e/playwright-report"
echo ""
echo "📸 Screenshots of failures (if any):"
echo "   ls e2e/test-results/"
