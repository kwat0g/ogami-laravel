#!/bin/bash
#
# Emergency Rollback Script
# Restores previous version from backup
#

set -e

APP_DIR="${APP_DIR:-/var/www/ogami}"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${RED}==========================================${NC}"
echo -e "${RED}  EMERGENCY ROLLBACK${NC}"
echo -e "${RED}  $(date)${NC}"
echo -e "${RED}==========================================${NC}"
echo ""

# Find latest backup
LATEST_BACKUP=$(ls -td /backup/ogami-* 2>/dev/null | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo -e "${RED}No backup found!${NC}"
    exit 1
fi

echo "Latest backup: $LATEST_BACKUP"
echo ""

# Show backup contents
echo "Backup contents:"
ls -lh "$LATEST_BACKUP/"
echo ""

# Confirm
read -p "Are you sure you want to rollback to this backup? (type 'yes' to confirm) " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Rollback cancelled."
    exit 0
fi

echo ""
echo -e "${YELLOW}Starting rollback...${NC}"

cd "$APP_DIR"

# 1. Enable maintenance mode
echo -e "${YELLOW}>>> Enabling maintenance mode...${NC}"
php artisan down --message="System maintenance in progress"

# 2. Restore database
echo -e "${YELLOW}>>> Restoring database...${NC}"
if [ -f "$LATEST_BACKUP/database.dump" ]; then
    # Terminate existing connections
    sudo -u postgres psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'ogami_erp' AND pid <> pg_backend_pid();" || true
    
    # Restore
    sudo -u postgres pg_restore --clean --if-exists -d ogami_erp "$LATEST_BACKUP/database.dump" || {
        echo -e "${RED}Database restore failed!${NC}"
        php artisan up
        exit 1
    }
    echo -e "${GREEN}✅ Database restored${NC}"
else
    echo -e "${RED}Database backup not found!${NC}"
    php artisan up
    exit 1
fi

# 3. Restore .env
echo -e "${YELLOW}>>> Restoring .env...${NC}"
if [ -f "$LATEST_BACKUP/.env.backup" ]; then
    cp "$LATEST_BACKUP/.env.backup" .env
    echo -e "${GREEN}✅ .env restored${NC}"
fi

# 4. Restore git to backup version
echo -e "${YELLOW}>>> Restoring code version...${NC}"
if [ -f "$LATEST_BACKUP/version.txt" ]; then
    BACKUP_COMMIT=$(cat "$LATEST_BACKUP/version.txt" | awk '{print $1}')
    git reset --hard "$BACKUP_COMMIT"
    echo -e "${GREEN}✅ Code restored to: $BACKUP_COMMIT${NC}"
fi

# 5. Reinstall dependencies
echo -e "${YELLOW}>>> Reinstalling dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Clear caches
echo -e "${YELLOW}>>> Clearing caches...${NC}"
php artisan optimize:clear
php artisan optimize

# 7. Fix permissions
echo -e "${YELLOW}>>> Fixing permissions...${NC}"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 8. Restart services
echo -e "${YELLOW}>>> Restarting services...${NC}"
systemctl restart php8.3-fpm nginx
php artisan queue:restart

# 9. Verify
echo -e "${YELLOW}>>> Verifying rollback...${NC}"
sleep 2
if curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/health" | grep -q "200"; then
    echo -e "${GREEN}✅ Health check passed${NC}"
else
    echo -e "${RED}⚠️  Health check failed - manual intervention needed${NC}"
fi

# 10. Disable maintenance
echo -e "${YELLOW}>>> Disabling maintenance mode...${NC}"
php artisan up

echo ""
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  Rollback Complete!${NC}"
echo -e "${GREEN}==========================================${NC}"
echo ""
echo "Rolled back to: $LATEST_BACKUP"
echo "Current version: $(git log --oneline -1)"
