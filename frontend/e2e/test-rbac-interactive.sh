#!/bin/bash
# Interactive RBAC Test Runner
# Run tests section by section with automatic rate limit clearing

set -e

BASE_DIR="/home/kwat0g/Desktop/ogamiPHP"
FRONTEND_DIR="$BASE_DIR/frontend"

clear_rate_limits() {
    echo "🧹 Clearing rate limits..."
    cd "$BASE_DIR"
    php artisan cache:clear 2>/dev/null || true
    php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush();" 2>/dev/null || true
}

unlock_accounts() {
    echo "🔓 Unlocking manufacturing accounts..."
    cd "$BASE_DIR"
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
}

run_test() {
    local name="$1"
    local filter="$2"
    
    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "🧪 Running: $name"
    echo "═══════════════════════════════════════════════════════════════"
    
    cd "$FRONTEND_DIR"
    if pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
        -g "$filter" --reporter=line; then
        echo "✅ $name - PASSED"
        return 0
    else
        echo "❌ $name - FAILED"
        return 1
    fi
}

show_menu() {
    clear
    echo "═══════════════════════════════════════════════════════════════"
    echo "🧪 Ogami ERP RBAC Test Runner"
    echo "═══════════════════════════════════════════════════════════════"
    echo ""
    echo "Select test section to run:"
    echo ""
    echo "  1) HR Department (4 tests)"
    echo "  2) Accounting Department (4 tests)"
    echo "  3) Production Department (6 tests) - CLEARS RATE LIMITS"
    echo "  4) Warehouse Department (3 tests)"
    echo "  5) QC Department (3 tests) - CLEARS RATE LIMITS"
    echo "  6) Procurement Department (2 tests)"
    echo "  7) Executive Roles (2 tests) - CLEARS RATE LIMITS"
    echo "  8) Admin Role (3 tests)"
    echo "  9) Cross-Cutting Forbidden (11 tests) - CLEARS RATE LIMITS"
    echo "  10) Action Button Visibility (5 tests)"
    echo "  11) Summary - All Roles Login (1 test) - CLEARS RATE LIMITS"
    echo "  12) Critical Tests Only (6 tests) - CLEARS RATE LIMITS"
    echo "  13) Full Suite (40 tests) - CLEARS RATE LIMITS"
    echo "  14) Exit"
    echo ""
    echo -n "Enter choice [1-14]: "
}

run_critical_tests() {
    clear_rate_limits
    unlock_accounts
    
    local passed=0
    local failed=0
    
    echo ""
    echo "🎯 Running Critical RBAC Tests..."
    echo ""
    
    # Critical tests
    cd "$FRONTEND_DIR"
    
    # Test 3.1
    if pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
        -g "Production Manager - No Payroll Access" --reporter=line 2>/dev/null; then
        echo "✅ Production blocked from Payroll"
        ((passed++))
    else
        echo "❌ Production blocked from Payroll - FAILED"
        ((failed++))
    fi
    
    # Test 3.2
    if pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
        -g "Production Manager - No Inventory Categories Access" --reporter=line 2>/dev/null; then
        echo "✅ Production blocked from Inventory Categories"
        ((passed++))
    else
        echo "❌ Production blocked from Inventory Categories - FAILED"
        ((failed++))
    fi
    
    # Test 4.1
    if pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
        -g "Warehouse Head - Full Inventory Access" --reporter=line 2>/dev/null; then
        echo "✅ Warehouse can access Inventory"
        ((passed++))
    else
        echo "❌ Warehouse can access Inventory - FAILED"
        ((failed++))
    fi
    
    # Cross-cutting tests
    for test in "Production accessing Payroll should be BLOCKED" \
                "Production accessing Inventory Categories should be BLOCKED" \
                "HR accessing Inventory should be BLOCKED"; do
        if pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
            -g "$test" --reporter=line 2>/dev/null; then
            echo "✅ $test"
            ((passed++))
        else
            echo "❌ $test - FAILED"
            ((failed++))
        fi
    done
    
    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "Critical Tests Results: $passed passed, $failed failed"
    echo "═══════════════════════════════════════════════════════════════"
}

# Main loop
while true; do
    show_menu
    read -r choice
    
    case $choice in
        1)
            run_test "HR Department" "HR Department"
            ;;
        2)
            run_test "Accounting Department" "Accounting Department"
            ;;
        3)
            clear_rate_limits
            unlock_accounts
            run_test "Production Department" "Production Department"
            ;;
        4)
            run_test "Warehouse Department" "Warehouse Department"
            ;;
        5)
            clear_rate_limits
            unlock_accounts
            run_test "QC Department" "QC Department"
            ;;
        6)
            run_test "Procurement Department" "Procurement Department"
            ;;
        7)
            clear_rate_limits
            unlock_accounts
            run_test "Executive Roles" "Executive Roles"
            ;;
        8)
            run_test "Admin Role" "Admin Role"
            ;;
        9)
            clear_rate_limits
            unlock_accounts
            run_test "Cross-Cutting Forbidden Access" "Cross-Cutting"
            ;;
        10)
            run_test "Action Button Visibility" "Action Button"
            ;;
        11)
            clear_rate_limits
            unlock_accounts
            run_test "Summary - All Roles Login" "Verify all roles"
            ;;
        12)
            run_critical_tests
            ;;
        13)
            clear_rate_limits
            unlock_accounts
            echo ""
            echo "🚀 Running full RBAC suite (40 tests)..."
            echo "This will take 10-15 minutes..."
            echo ""
            cd "$FRONTEND_DIR"
            pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 --reporter=line
            ;;
        14)
            echo "Goodbye!"
            exit 0
            ;;
        *)
            echo "Invalid choice. Press Enter to continue..."
            read -r
            ;;
    esac
    
    echo ""
    echo "Press Enter to continue..."
    read -r
done
