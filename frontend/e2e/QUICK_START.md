# RBAC E2E Tests - Quick Start Guide

## 🚀 Fastest Way to Test

### Option 1: Interactive Menu (Recommended)
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend/e2e
./test-rbac-interactive.sh
```

Then select from the menu:
- **3** - Production Department (6 tests)
- **9** - Cross-Cutting Forbidden (11 tests)
- **12** - Critical Tests Only (6 tests)
- **13** - Full Suite (40 tests)

### Option 2: Quick Command Line

```bash
# Critical tests only (Production blocked from Payroll/Inventory)
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - No Payroll" --reporter=line
```

---

## 📋 Step-by-Step Testing Guide

### 1. Start Servers
```bash
# Terminal 1
cd /home/kwat0g/Desktop/ogamiPHP && php artisan serve

# Terminal 2  
cd /home/kwat0g/Desktop/ogamiPHP/frontend && pnpm dev
```

### 2. Run Tests
```bash
# Terminal 3
cd /home/kwat0g/Desktop/ogamiPHP/frontend/e2e
./test-rbac-interactive.sh
```

---

## 🎯 Critical Tests to Verify

These are the most important tests:

| Test | Command | Expected |
|------|---------|----------|
| Production → Payroll blocked | `-g "Production Manager - No Payroll"` | PASS |
| Production → Inventory blocked | `-g "Production Manager - No Inventory"` | PASS |
| Warehouse → Inventory allowed | `-g "Warehouse Head - Full Inventory"` | PASS |
| Cross-cutting Payroll block | `-g "Production accessing Payroll"` | PASS |
| Cross-cutting Inventory block | `-g "Production accessing Inventory"` | PASS |

---

## 📊 Test Summary

| Section | Tests | Time | Priority |
|---------|-------|------|----------|
| HR | 4 | ~30s | Medium |
| Accounting | 4 | ~30s | Medium |
| **Production** | **6** | **~60s** | **HIGH** |
| **Warehouse** | **3** | **~30s** | **HIGH** |
| QC | 3 | ~40s | Medium |
| Procurement | 2 | ~20s | Low |
| Executive | 2 | ~40s | Medium |
| Admin | 3 | ~30s | Medium |
| **Cross-Cutting** | **11** | **~3min** | **HIGH** |
| Action Buttons | 5 | ~40s | Medium |
| Summary | 1 | ~2min | Low |
| **TOTAL** | **40** | **~10min** | - |

---

## 🛠 Troubleshooting

### "Account is temporarily locked"
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan tinker --execute="App\Models\User::where('email', 'prod.manager@ogamierp.local')->update(['failed_login_attempts' => 0, 'locked_until' => null]);"
```

### "Too many login attempts"
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush();"
```

### Test Timeouts
Edit `playwright.config.ts` and increase timeouts:
```typescript
actionTimeout: 60000,
navigationTimeout: 60000,
```

---

## 📁 Documentation Files

| File | Purpose |
|------|---------|
| `QUICK_START.md` | This file - quick reference |
| `RBAC_STEP_BY_STEP_TEST_GUIDE.md` | Complete 40-test guide |
| `test-rbac-interactive.sh` | Interactive test runner |
| `run-rbac-tests.sh` | Non-interactive runner |
| `RBAC_TEST_FIXES.md` | Technical fix details |

---

## ✅ Verification Checklist

Before running tests:
- [ ] Backend server running (port 8000)
- [ ] Frontend server running (port 5173)
- [ ] Manufacturing accounts seeded
- [ ] Rate limits cleared

After running tests:
- [ ] All critical tests pass (Production blocked from Payroll/Inventory)
- [ ] Warehouse can access Inventory
- [ ] Cross-department access blocked
