# Pre-Testing Checklist - Ogami ERP

Complete these steps before starting manual testing to ensure a smooth testing experience.

---

## Phase 1: Environment Setup

### 1.1 Start Development Services

```bash
# Navigate to project directory
cd /home/kwat0g/Desktop/ogamiPHP

# Start all services (Laravel + Vite + Queue + Reverb)
npm run dev

# OR start minimal (without queue/reverb)
npm run dev:minimal
```

**Verify services are running:**
- Laravel: http://localhost:8000 (should show API welcome)
- Vite: http://localhost:5173 (should load login page)

### 1.2 Verify Database Connection

```bash
# Test database connection
php artisan db:monitor

# Expected: "Database connection successful"
```

---

## Phase 2: Database Preparation

### 2.1 Fresh Database with Seed Data

```bash
# Complete reset with all seeders (RECOMMENDED)
php artisan migrate:fresh --seed
```

**This will:**
- Drop all tables
- Run all migrations
- Execute all seeders in order:
  1. Rate tables (SSS, PhilHealth, Pag-IBIG, Tax, etc.)
  2. RBAC (RolePermissionSeeder)
  3. Reference data (Departments, Positions)
  4. Test accounts (ComprehensiveTestAccountsSeeder, TestAccountsSeeder, SampleDataSeeder, ExtraAccountsSeeder)

### 2.2 Verify Test Accounts Exist

```bash
# Run verification script
php scripts/verify-test-accounts.php
```

**Expected output:**
```
✅ VERIFIED: hr.manager@ogamierp.local (manager) (EMP-HR-001)
✅ VERIFIED: acctg.officer@ogamierp.local (officer) (EMP-ACCT-002)
✅ VERIFIED: vp@ogamierp.local (vice_president) (EMP-EXEC-002)
...
✓ 23 accounts verified
```

**If accounts missing:**
```bash
# Run individual seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=ComprehensiveTestAccountsSeeder
php artisan db:seed --class=TestAccountsSeeder
php artisan db:seed --class=SampleDataSeeder
php artisan db:seed --class=ExtraAccountsSeeder
```

### 2.3 Verify API Health

```bash
# Check API health endpoint
curl http://localhost:8000/api/health

# Expected: {"status":"ok","timestamp":"..."}
```

---

## Phase 3: Frontend Verification

### 3.1 Login Page Loads

1. Open browser: http://localhost:5173
2. Should see login form with:
   - Email input field
   - Password input field
   - Login button
   - Company logo

### 3.2 Test Login (Smoke Test)

**Test 1: HR Manager Login**
- Email: `hr.manager@ogamierp.local`
- Password: `Manager@Test1234!`
- Expected: Redirect to dashboard, sidebar visible

**Test 2: Logout and Test Another**
- Click logout (top right menu)
- Login as `vp@ogamierp.local` / `Vp@Test1234!`
- Expected: VP dashboard with "VP Approvals" menu

### 3.3 Check Browser Console

Open browser DevTools (F12):
- **Console tab:** Should have no red errors
- **Network tab:** API calls should return 200 status

---

## Phase 4: Cache and Storage

### 4.1 Clear All Caches

```bash
# Laravel caches
php artisan optimize:clear

# Rebuild optimized cache
php artisan optimize

# View cache
php artisan view:clear
```

### 4.2 Verify Storage Permissions

```bash
# Check storage is writable
ls -la storage/logs
ls -la storage/framework/cache
ls -la storage/app/public

# Fix permissions if needed
chmod -R 775 storage bootstrap/cache
```

### 4.3 Create Storage Link (if not exists)

```bash
# Create symbolic link for public storage
php artisan storage:link
```

---

## Phase 5: Test Data Verification

### 5.1 Verify Key Reference Data

```bash
php artisan tinker

# Check departments exist
>>> App\Domains\HR\Models\Department::count();
# Expected: > 0

# Check roles exist
>>> Spatie\Permission\Models\Role::count();
# Expected: > 0

# Check test users exist
>>> App\Models\User::where('email', 'hr.manager@ogamierp.local')->exists();
# Expected: true

# Check employee links
>>> App\Domains\HR\Models\Employee::whereNotNull('user_id')->count();
# Expected: > 0 (employees with linked users)

exit
```

### 5.2 Verify Permission System

```bash
php artisan tinker

# Check HR manager has correct role
>>> $user = App\Models\User::where('email', 'hr.manager@ogamierp.local')->first();
>>> $user->roles->pluck('name');
# Expected: ['manager']

# Check VP has vice_president role
>>> $vp = App\Models\User::where('email', 'vp@ogamierp.local')->first();
>>> $vp->roles->pluck('name');
# Expected: ['vice_president']

exit
```

---

## Phase 6: Quick Functionality Tests

### 6.1 Test API Endpoints

```bash
# Get auth token (optional - for API testing)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"hr.manager@ogamierp.local","password":"Manager@Test1234!"}'

# Test employees endpoint (requires auth)
```

### 6.2 Test Database Transactions

```bash
php artisan tinker

# Test read/write
>>> $test = App\Domains\HR\Models\Department::first();
>>> echo $test->name;
# Should output department name

exit
```

---

## Phase 7: Browser Setup

### 7.1 Recommended Browser Settings

**Chrome/Edge:**
- Enable DevTools (F12)
- Enable "Preserve log" in Network tab
- Disable cache (DevTools > Network > Disable cache checkbox)

**Firefox:**
- Enable DevTools (F12)
- Enable persistent logs

### 7.2 Multiple Browser Windows

For SoD testing, you'll need multiple browser contexts:

**Option 1: Incognito Windows**
- Main window: Login as User A
- Incognito 1: Login as User B
- Incognito 2: Login as User C

**Option 2: Different Browsers**
- Chrome: User A
- Firefox: User B
- Edge: User C

### 7.3 Browser Extensions to Disable

Temporarily disable:
- Ad blockers (may block API calls)
- Privacy extensions
- Password managers (use test credentials)

---

## Phase 8: Testing Tools Ready

### 8.1 Have These Documents Open

1. **COMPLETE_TESTING_GUIDE.md** - Main testing guide
2. **TEST_ACCOUNTS_QUICKREF.md** - Quick credential lookup
3. This checklist

### 8.2 Create Testing Notes Document

Create a file to track your testing:
```bash
touch testing-notes-$(date +%Y%m%d).md
```

**Track:**
- Tests completed
- Issues found
- SoD violations verified
- Questions

---

## Phase 9: Optional - Production Health Check

If testing on production/staging:

```bash
# Run production health check script
bash scripts/production-health-check.sh
```

**Checks:**
- System resources
- Service status
- Database connectivity
- SSL certificates
- API endpoints

---

## Quick Start Command Summary

```bash
# 1. Navigate to project
cd /home/kwat0g/Desktop/ogamiPHP

# 2. Fresh database
php artisan migrate:fresh --seed

# 3. Verify accounts
php scripts/verify-test-accounts.php

# 4. Clear caches
php artisan optimize:clear

# 5. Start services
npm run dev

# 6. Open browser to
open http://localhost:5173
# or
start http://localhost:5173  # Windows
```

---

## Troubleshooting Pre-Test Issues

### Issue: "Failed to connect to database"
```bash
# Check PostgreSQL running
sudo systemctl status postgresql

# Or Docker
docker ps | grep postgres

# Check .env DB settings
cat .env | grep DB_
```

### Issue: "Vite connection refused"
```bash
# Check if Vite started
curl http://localhost:5173

# Restart Vite
cd frontend && npm run dev
```

### Issue: "Permission denied" on storage
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage  # If using www-data
```

### Issue: "Test accounts not found"
```bash
# Re-run seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=TestAccountsSeeder
php artisan db:seed --class=SampleDataSeeder
```

### Issue: "SoD not working"
```bash
# Check user roles in database
php artisan tinker
>>> App\Models\User::where('email', 'hr.manager@ogamierp.local')->first()->roles->pluck('name');
# Should NOT include 'admin' or 'super_admin'
```

---

## Pre-Test Verification Checklist

Tick these off before starting:

- [ ] Laravel running on http://localhost:8000
- [ ] Vite running on http://localhost:5173
- [ ] Database seeded successfully
- [ ] Test accounts verified (php scripts/verify-test-accounts.php)
- [ ] Login page loads without errors
- [ ] Can login as hr.manager@ogamierp.local
- [ ] Can login as vp@ogamierp.local
- [ ] Can login as acctg.officer@ogamierp.local
- [ ] Sidebar navigation visible after login
- [ ] Browser DevTools open (no console errors)
- [ ] Testing guide document open
- [ ] Test notes document ready
- [ ] Multiple browser windows ready (for SoD testing)

---

## Time Estimate

| Task | Estimated Time |
|------|----------------|
| Database reset & seed | 2-3 minutes |
| Verify test accounts | 30 seconds |
| Clear caches | 30 seconds |
| Start services | 1 minute |
| Quick smoke tests | 2 minutes |
| **Total** | **~5-7 minutes** |

---

Once all checks pass, you're ready to start testing with the **COMPLETE_TESTING_GUIDE.md**!

**Start with:** Module 1 → Test 1.1 (Employee Creation with SoD verification)
