#!/bin/bash

# Ogami ERP E2E Test Runner
# 
# Usage:
#   ./scripts/run-e2e-tests.sh              # Run all tests
#   ./scripts/run-e2e-tests.sh auth         # Run auth tests only
#   ./scripts/run-e2e-tests.sh procurement  # Run procurement tests only
#   ./scripts/run-e2e-tests.sh --ui         # Run with UI mode

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
FRONTEND_DIR="$PROJECT_ROOT/frontend"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Ogami ERP E2E Test Runner${NC}"
echo "========================================"

# Check if servers are running
echo -n "Checking backend (localhost:8000)... "
if curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${RED}✗ Not running${NC}"
    echo -e "${YELLOW}Please start the backend server:${NC}"
    echo "  php artisan serve --port=8000"
    exit 1
fi

echo -n "Checking frontend (localhost:5173)... "
if curl -s http://localhost:5173 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${RED}✗ Not running${NC}"
    echo -e "${YELLOW}Please start the frontend server:${NC}"
    echo "  cd frontend && pnpm dev"
    exit 1
fi

echo ""
echo "Both servers are running. Starting E2E tests..."
echo ""

cd "$FRONTEND_DIR"

# Check for test file argument
if [ -n "$1" ]; then
    case "$1" in
        --ui)
            echo "Running tests in UI mode..."
            pnpm e2e:ui
            ;;
        --headed)
            echo "Running tests in headed mode..."
            pnpm e2e -- --headed
            ;;
        auth|accounting|ap-workflow|hr-onboarding|leave|payroll|procurement|inventory-workflow|production-workflow|qc-workflow|sod)
            echo "Running tests for: $1"
            pnpm e2e "$1.spec.ts"
            ;;
        *)
            echo -e "${RED}Unknown test suite: $1${NC}"
            echo "Available options:"
            echo "  auth, accounting, ap-workflow, hr-onboarding, leave, payroll"
            echo "  procurement, inventory-workflow, production-workflow, qc-workflow, sod"
            echo ""
            echo "Special modes:"
            echo "  --ui      - Run with Playwright UI"
            echo "  --headed  - Run with visible browser"
            exit 1
            ;;
    esac
else
    echo "Running all E2E tests..."
    pnpm e2e
fi

echo ""
echo -e "${GREEN}Test run complete!${NC}"
echo "View report: pnpm e2e:report"
