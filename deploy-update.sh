#!/bin/bash
#
# Production Update Deployment Script
# Safe deployment with rollback capability
#

set -e

APP_DIR="${APP_DIR:-/var/www/ogami}"
DOMAIN="${DOMAIN:-ogamiph.dev}"
USER="${USER:-www-data}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  Ogami ERP Production Deployment${NC}"
echo -e "${GREEN}  $(date)${NC}"
echo -e "${GREEN}==========================================${NC}"
echo ""

cd "$APP_DIR"

# Step 1: Pre-deployment backup
log "Step 1: Creating pre-deployment backup..."
if [ -f "backup-and-verify.sh" ]; then
    ./backup-and-verify.sh || { error "Backup failed"; exit 1; }
else
    warning "backup-and-verify.sh not found, creating manual backup..."
    BACKUP_DIR="/backup/manual-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    sudo -u postgres pg_dump -Fc ogami_erp > "$BACKUP_DIR/database.dump"
    cp .env "$BACKUP_DIR/.env.backup"
    success "Manual backup created at $BACKUP_DIR"
fi
echo ""

# Step 2: Enable maintenance mode
log "Step 2: Enabling maintenance mode..."
php artisan down --message="System update in progress. Please check back in a few minutes." --retry=60
success "Maintenance mode enabled"
echo ""

# Step 3: Pull latest code
log "Step 3: Pulling latest code from repository..."
git fetch origin
git pull origin main || { error "Git pull failed"; php artisan up; exit 1; }
success "Code updated to: $(git log --oneline -1)"
echo ""

# Step 4: Install/update dependencies
log "Step 4: Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
success "Dependencies installed"
echo ""

# Step 5: Run database migrations
log "Step 5: Running database migrations..."
php artisan migrate --force --step
success "Migrations completed"
echo ""

# Step 6: Clear and rebuild caches
log "Step 6: Rebuilding caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
success "Caches rebuilt"
echo ""

# Step 7: Fix permissions
log "Step 7: Fixing file permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
success "Permissions fixed"
echo ""

# Step 8: Restart services
log "Step 8: Restarting services..."
systemctl restart php8.3-fpm
systemctl restart nginx
success "Services restarted"
echo ""

# Step 9: Restart queue workers
log "Step 9: Restarting queue workers..."
php artisan queue:restart
if systemctl is-active --quiet horizon 2>/dev/null; then
    systemctl restart horizon
fi
success "Queue workers restarted"
echo ""

# Step 10: Post-deployment verification
log "Step 10: Running post-deployment verification..."
sleep 2  # Give services time to start

HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/health" || echo "000")
if [ "$HEALTH_STATUS" = "200" ]; then
    success "Health check: OK (HTTP 200)"
else
    error "Health check failed: HTTP $HEALTH_STATUS"
    error "Keeping maintenance mode ON for safety"
    echo ""
    echo -e "${YELLOW}To disable maintenance mode manually when fixed:${NC}"
    echo "  php artisan up"
    exit 1
fi

# Check for errors in log
sleep 2
ERROR_COUNT=$(grep -c "ERROR\|CRITICAL" storage/logs/laravel.log 2>/dev/null | tail -50 || echo "0")
if [ "$ERROR_COUNT" -eq 0 ]; then
    success "No recent errors in logs"
else
    warning "$ERROR_COUNT errors found in recent logs"
fi
echo ""

# Step 11: Disable maintenance mode
log "Step 11: Disabling maintenance mode..."
php artisan up
success "Maintenance mode disabled - Site is LIVE!"
echo ""

# Final summary
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  Deployment Complete!${NC}"
echo -e "${GREEN}==========================================${NC}"
echo ""
echo "Version deployed: $(git log --oneline -1)"
echo "Deployment time: $(date)"
echo "Health check: https://$DOMAIN/api/health"
echo ""
echo -e "${GREEN}✅ Your update is now live!${NC}"
