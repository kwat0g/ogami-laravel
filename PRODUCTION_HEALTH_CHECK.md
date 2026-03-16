# Production Health Check Guide

## Quick Verification Commands

### 1. Server Connectivity
```bash
# SSH into your VPS
ssh root@YOUR_SERVER_IP

# Check system resources
htop                    # CPU/Memory usage
df -h                   # Disk space
free -m                 # Memory usage
```

### 2. Application Health Check
```bash
cd /var/www/ogami  # or your app directory

# Check Laravel is responding
curl -s http://localhost/api/health | jq

# Expected: {"status":"ok","timestamp":"2026-03-16T..."}
```

### 3. Service Status
```bash
# Check all services are running
systemctl status nginx
systemctl status php8.3-fpm
systemctl status postgresql
systemctl status redis-server
systemctl status supervisor

# Restart if needed
systemctl restart php8.3-fpm
systemctl restart nginx
```

### 4. Database Verification
```bash
# Connect to PostgreSQL
sudo -u postgres psql -d ogami_erp -c "\dt"

# Check recent migrations
php artisan migrate:status

# Verify connection
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'DB OK' : 'DB FAIL';"
```

### 5. Cache & Storage
```bash
# Clear and rebuild caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check storage permissions
ls -la storage/
chown -R www-data:www-data storage/ bootstrap/cache/
chmod -R 775 storage/ bootstrap/cache/
```

### 6. Queue Workers
```bash
# Check queue status
php artisan queue:status

# Restart queue workers
php artisan queue:restart

# Check Horizon (if installed)
php artisan horizon:status

# View failed jobs
php artisan queue:failed
```

### 7. SSL Certificate
```bash
# Check certificate expiration
echo | openssl s_client -servername ogamiph.dev -connect ogamiph.dev:443 2>/dev/null | openssl x509 -noout -dates

# Renew if needed
certbot renew --dry-run
```

---

## API Endpoint Verification

### Health Endpoint
```bash
curl -s https://ogamiph.dev/api/health | jq
```

### Auth Endpoints
```bash
# Login
curl -s -X POST https://ogamiph.dev/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' | jq

# Expected: {"token":"...","user":{"id":...}}
```

### New Approvals Endpoint
```bash
# Get pending approvals (requires auth token)
curl -s https://ogamiph.dev/api/v1/approvals/pending \
  -H "Authorization: Bearer YOUR_TOKEN" | jq '.summary'

# Expected: {"summary":{"hr":{...},"procurement":{...},"total_pending":X}}
```

### Dashboard Endpoints
```bash
# Admin dashboard
curl -s https://ogamiph.dev/api/v1/dashboard/admin \
  -H "Authorization: Bearer YOUR_TOKEN" | jq '.system_health'

# Manager dashboard  
curl -s https://ogamiph.dev/api/v1/dashboard/manager \
  -H "Authorization: Bearer YOUR_TOKEN" | jq '.headcount'
```

---

## Log Monitoring

### Application Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx error logs
tail -f /var/log/nginx/error.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log

# PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-16-main.log
```

### Queue/Horizon Logs
```bash
tail -f storage/logs/horizon.log
tail -f storage/logs/worker.log
```

---

## Database Health Queries

### Connection Count
```sql
SELECT count(*) FROM pg_stat_activity WHERE datname = 'ogami_erp';
```

### Table Sizes
```sql
SELECT schemaname, tablename, 
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables 
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 20;
```

### Long Running Queries
```sql
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE state = 'active' AND now() - query_start > interval '5 minutes';
```

---

## Performance Checks

### Response Time Test
```bash
# API response time
time curl -s https://ogamiph.dev/api/health

# Should be < 500ms for health endpoint
```

### Database Query Performance
```bash
# Enable query log temporarily
php artisan tinker --execute="DB::enableQueryLog(); \App\Models\User::first(); dd(DB::getQueryLog());"
```

### Cache Hit Rate
```bash
# Redis info
redis-cli INFO stats | grep keyspace

# Expected: high hit rate, low evictions
```

---

## Security Verification

### File Permissions
```bash
# Check for world-writable files
find /var/www/ogami -type f -perm -002 ! -path "*/storage/*" ! -path "*/bootstrap/cache/*"

# Check .env is not readable by others
ls -la /var/www/ogami/.env
# Should be: -rw-r--r-- 1 www-data www-data
```

### Firewall Status
```bash
ufw status
# Should show: Nginx Full, OpenSSH, 80/tcp, 443/tcp
```

---

## Pre-Update Checklist

Before running any updates, verify:

- [ ] **Backup Database**: `pg_dump -U postgres ogami_erp > backup_$(date +%Y%m%d).sql`
- [ ] **Backup .env**: `cp .env .env.backup.$(date +%Y%m%d)`
- [ ] **Check Disk Space**: `df -h` (ensure > 20% free)
- [ ] **Maintenance Mode**: `php artisan down --message="Maintenance in progress"`
- [ ] **Git Status**: `git status` (ensure clean working tree)
- [ ] **Run Tests**: `./vendor/bin/pest tests/Integration --no-coverage`

---

## Post-Update Verification

After deployment:

- [ ] **Health Check**: `curl -s https://ogamiph.dev/api/health`
- [ ] **Database Migrations**: `php artisan migrate --force`
- [ ] **Cache Clear**: `php artisan optimize:clear && php artisan optimize`
- [ ] **Queue Restart**: `php artisan queue:restart`
- [ ] **Permission Fix**: `chown -R www-data:www-data storage bootstrap/cache`
- [ ] **Log Check**: `tail -n 50 storage/logs/laravel.log`
- [ ] **Exit Maintenance**: `php artisan up`

---

## Automated Health Check Script

Save this as `/usr/local/bin/ogami-health`:

```bash
#!/bin/bash
APP_DIR="/var/www/ogami"
DOMAIN="ogamiph.dev"

echo "=== Ogami ERP Health Check ==="
echo "Date: $(date)"
echo ""

# Check services
echo "--- Service Status ---"
systemctl is-active --quiet nginx && echo "✅ Nginx" || echo "❌ Nginx"
systemctl is-active --quiet php8.3-fpm && echo "✅ PHP-FPM" || echo "❌ PHP-FPM"
systemctl is-active --quiet postgresql && echo "✅ PostgreSQL" || echo "❌ PostgreSQL"
systemctl is-active --quiet redis-server && echo "✅ Redis" || echo "❌ Redis"

# Check health endpoint
echo ""
echo "--- API Health ---"
HEALTH=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/api/health)
[ "$HEALTH" = "200" ] && echo "✅ API Health (200)" || echo "❌ API Health ($HEALTH)"

# Check disk space
echo ""
echo "--- Disk Space ---"
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
[ "$DISK_USAGE" -lt 90 ] && echo "✅ Disk: ${DISK_USAGE}%" || echo "⚠️  Disk: ${DISK_USAGE}%"

# Check memory
echo ""
echo "--- Memory ---"
FREE_MEM=$(free | grep Mem | awk '{printf "%.0f", $4/$2 * 100}')
echo "Free Memory: ${FREE_MEM}%"

echo ""
echo "=== Check Complete ==="
```

Make executable: `chmod +x /usr/local/bin/ogami-health`

Run: `ogami-health`
