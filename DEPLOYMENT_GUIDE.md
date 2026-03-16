# Production Deployment & Verification Guide

## 📋 Pre-Deployment Checklist

Before deploying any updates, ensure:
- [ ] All integration tests passing locally
- [ ] Code committed and pushed to repository
- [ ] Database backup available
- [ ] Server has sufficient disk space (>20% free)
- [ ] Maintenance window scheduled (if applicable)

---

## 🔧 Scripts Overview

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `production-health-check.sh` | Full system health verification | Weekly, or before/after updates |
| `backup-and-verify.sh` | Pre-update backup with verification | Before every deployment |
| `deploy-update.sh` | Safe deployment with verification | To deploy updates |
| `rollback.sh` | Emergency rollback | If deployment fails |

---

## 📥 Installation on VPS

### 1. Copy Scripts to Server

```bash
# From your local machine
scp production-health-check.sh backup-and-verify.sh deploy-update.sh rollback.sh root@YOUR_SERVER_IP:/usr/local/bin/

# SSH into server and make executable
ssh root@YOUR_SERVER_IP
chmod +x /usr/local/bin/*.sh
```

### 2. Create Backup Directory

```bash
mkdir -p /backup
chmod 755 /backup
```

### 3. Set Environment Variables (Optional)

Edit `/etc/environment` or add to `~/.bashrc`:

```bash
export APP_DIR=/var/www/ogami
export DOMAIN=ogamiph.dev
```

---

## 🏥 Weekly Health Check

Run this weekly to catch issues early:

```bash
/usr/local/bin/production-health-check.sh
```

### What It Checks:
- ✅ System resources (disk, memory, CPU)
- ✅ Service status (nginx, php-fpm, postgres, redis)
- ✅ Application bootstrap
- ✅ Database connection
- ✅ Redis cache
- ✅ API endpoints
- ✅ SSL certificate expiry
- ✅ Database health
- ✅ Queue status
- ✅ Recent errors in logs
- ✅ File permissions

### Exit Codes:
- `0` - System healthy
- `1` - Functional with minor issues
- `2` - Critical issues detected

---

## 🚀 Deployment Process

### Step 1: Pre-Deployment Backup

```bash
cd /var/www/ogami
./backup-and-verify.sh
```

This creates:
- Database dump (`/backup/ogami-YYYYMMDD_HHMMSS/database.dump`)
- `.env` backup
- Storage uploads backup
- Git version record

### Step 2: Deploy Update

```bash
cd /var/www/ogami
./deploy-update.sh
```

This will:
1. Create backup
2. Enable maintenance mode
3. Pull latest code
4. Install dependencies
5. Run migrations
6. Clear/rebuild caches
7. Fix permissions
8. Restart services
9. Verify deployment
10. Disable maintenance mode

### Step 3: Post-Deployment Verification

```bash
# Health check
curl -s https://ogamiph.dev/api/health | jq

# Integration tests
./vendor/bin/pest tests/Integration --no-coverage

# Check logs for errors
tail -50 storage/logs/laravel.log
```

---

## 🚨 Emergency Rollback

If something goes wrong:

```bash
cd /var/www/ogami
./rollback.sh
```

**⚠️ Warning:** This will restore the database to the last backup point. Any data changes since the backup will be lost.

---

## 📊 Monitoring Commands

### Real-time Logs

```bash
# Application logs
tail -f /var/www/ogami/storage/logs/laravel.log

# Nginx error logs
tail -f /var/log/nginx/error.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log

# PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-16-main.log
```

### Service Status

```bash
# All services
systemctl status nginx php8.3-fpm postgresql redis-server supervisor

# Restart services
systemctl restart nginx php8.3-fpm
```

### Database

```bash
# Connection count
sudo -u postgres psql -d ogami_erp -c "SELECT count(*) FROM pg_stat_activity WHERE datname = 'ogami_erp';"

# Recent slow queries
sudo -u postgres psql -d ogami_erp -c "SELECT query, query_start FROM pg_stat_activity WHERE state = 'active' AND now() - query_start > interval '5 seconds';"

# Table sizes
sudo -u postgres psql -d ogami_erp -c "SELECT tablename, pg_size_pretty(pg_total_relation_size(tablename)) FROM pg_tables WHERE schemaname = 'public' ORDER BY pg_total_relation_size(tablename) DESC LIMIT 10;"
```

### Cache & Queue

```bash
# Clear all caches
php artisan optimize:clear

# Queue status
php artisan queue:status

# Failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

---

## 🔄 Automated Monitoring (Optional)

### Cron Job for Health Checks

```bash
# Edit crontab
crontab -e

# Add this line for daily health check at 6 AM
0 6 * * * /usr/local/bin/production-health-check.sh > /var/log/ogami-health-cron.log 2>&1

# Add this for weekly backup
0 2 * * 0 /usr/local/bin/backup-and-verify.sh > /var/log/ogami-backup-cron.log 2>&1
```

### Simple Uptime Monitor

Create `/usr/local/bin/ogami-watchdog`:

```bash
#!/bin/bash
HEALTH=$(curl -s -o /dev/null -w "%{http_code}" https://ogamiph.dev/api/health)
if [ "$HEALTH" != "200" ]; then
    echo "ALERT: Ogami ERP health check failed at $(date)" | mail -s "Ogami Alert" admin@ogamiph.dev
fi
```

```bash
chmod +x /usr/local/bin/ogami-watchdog

# Add to crontab (every 5 minutes)
*/5 * * * * /usr/local/bin/ogami-watchdog
```

---

## 🆘 Troubleshooting

### Issue: 502 Bad Gateway

```bash
# Check PHP-FPM
systemctl status php8.3-fpm
systemctl restart php8.3-fpm

# Check socket permissions
ls -la /var/run/php/php8.3-fpm.sock

# Check nginx error logs
tail -f /var/log/nginx/error.log
```

### Issue: Database Connection Failed

```bash
# Check PostgreSQL
systemctl status postgresql
sudo -u postgres psql -c "\l"

# Check .env database config
grep DB_ /var/www/ogami/.env

# Test connection
php artisan tinker --execute="DB::connection()->getPdo();"
```

### Issue: Permission Denied

```bash
# Fix storage permissions
chown -R www-data:www-data /var/www/ogami/storage /var/www/ogami/bootstrap/cache
chmod -R 775 /var/www/ogami/storage /var/www/ogami/bootstrap/cache

# Clear caches
php artisan optimize:clear
```

### Issue: Queue Not Processing

```bash
# Check queue status
php artisan queue:status

# Restart queue workers
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Check Horizon (if installed)
php artisan horizon:status
```

---

## 📞 Support

If you encounter issues:

1. Check logs: `tail -f storage/logs/laravel.log`
2. Run health check: `./production-health-check.sh`
3. Verify services: `systemctl status nginx php8.3-fpm postgresql`
4. Rollback if needed: `./rollback.sh`

---

## ✅ Deployment Sign-off

Before considering deployment complete, verify:

- [ ] `production-health-check.sh` shows all green
- [ ] Integration tests pass: `./vendor/bin/pest tests/Integration`
- [ ] API health endpoint returns 200
- [ ] No critical errors in logs
- [ ] Backup created and verified
- [ ] Site accessible via browser

**Last Updated:** 2026-03-16
