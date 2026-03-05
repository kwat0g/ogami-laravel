#!/bin/sh
set -e

# ── Rebuild caches with runtime environment variables ────────────────────────
# config:cache, route:cache, view:cache must run at container start so they
# pick up actual DB_HOST, REDIS_HOST etc. injected by docker run / compose.
php artisan optimize

# ── Storage link (idempotent) ─────────────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

# ── Fix permissions ───────────────────────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ── Hand off to supervisord ───────────────────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisord.conf
