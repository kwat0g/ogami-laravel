#!/bin/bash
#
# Pre-Update: Backup & Verify Script
# Run this BEFORE deploying any updates
#

set -e

APP_DIR="${APP_DIR:-/var/www/ogami}"
BACKUP_DIR="/backup/ogami-$(date +%Y%m%d_%H%M%S)"
DOMAIN="${DOMAIN:-ogamiph.dev}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  Pre-Update Backup & Verification${NC}"
echo -e "${GREEN}  $(date)${NC}"
echo -e "${GREEN}==========================================${NC}"
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"
echo "Backup directory: $BACKUP_DIR"
echo ""

# 1. Database Backup
echo -e "${YELLOW}>>> Backing up database...${NC}"
if sudo -u postgres pg_dump -Fc ogami_erp > "$BACKUP_DIR/database.dump" 2>/dev/null; then
    DB_SIZE=$(du -h "$BACKUP_DIR/database.dump" | cut -f1)
    echo -e "${GREEN}✅ Database backed up: $DB_SIZE${NC}"
else
    echo -e "${RED}❌ Database backup failed!${NC}"
    exit 1
fi

# 2. .env Backup
echo -e "${YELLOW}>>> Backing up .env...${NC}"
cp "$APP_DIR/.env" "$BACKUP_DIR/.env.backup"
echo -e "${GREEN}✅ .env backed up${NC}"

# 3. Storage Backup (uploads only, exclude logs)
echo -e "${YELLOW}>>> Backing up storage uploads...${NC}"
if [ -d "$APP_DIR/storage/app/public" ]; then
    tar -czf "$BACKUP_DIR/storage-uploads.tar.gz" -C "$APP_DIR/storage/app" public/ 2>/dev/null || true
    echo -e "${GREEN}✅ Storage uploads backed up${NC}"
fi

# 4. Git Status Check
echo ""
echo -e "${YELLOW}>>> Checking git status...${NC}"
cd "$APP_DIR"
if [ -z "$(git status --porcelain)" ]; then
    echo -e "${GREEN}✅ Git working tree is clean${NC}"
else
    echo -e "${YELLOW}⚠️  Uncommitted changes detected:${NC}"
    git status --short
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

# 5. Current Version Info
echo ""
echo -e "${YELLOW}>>> Recording current version...${NC}"
git log --oneline -1 > "$BACKUP_DIR/version.txt"
echo "Current commit: $(git log --oneline -1)"

# 6. Pre-Update Health Check
echo ""
echo -e "${YELLOW}>>> Running pre-update health check...${NC}"
if curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/health" | grep -q "200"; then
    echo -e "${GREEN}✅ Application is responding${NC}"
else
    echo -e "${RED}❌ Application is not responding correctly!${NC}"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# 7. Test Suite Verification
echo ""
echo -e "${YELLOW}>>> Running integration tests...${NC}"
cd "$APP_DIR"
if ./vendor/bin/pest tests/Integration --no-coverage >/dev/null 2>&1; then
    echo -e "${GREEN}✅ All integration tests passing${NC}"
else
    echo -e "${RED}❌ Some integration tests failed!${NC}"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Summary
echo ""
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  Backup Complete!${NC}"
echo -e "${GREEN}==========================================${NC}"
echo ""
echo "Backup location: $BACKUP_DIR"
echo "Contents:"
ls -lh "$BACKUP_DIR/"
echo ""
echo -e "${YELLOW}To restore from this backup:${NC}"
echo "  Database: sudo -u postgres pg_restore -d ogami_erp $BACKUP_DIR/database.dump"
echo "  .env:     cp $BACKUP_DIR/.env.backup $APP_DIR/.env"
echo ""
echo -e "${GREEN}✅ Safe to proceed with deployment${NC}"
