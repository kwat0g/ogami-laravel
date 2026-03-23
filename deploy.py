#!/usr/bin/env python3
"""
Ogami ERP — automated FRESH VPS deployment via pexpect SSH.

Usage:
    python3 deploy.py

Config: edit the constants at the top of this file before running.
FRESH MODE: wipes the existing app directory and database, then rebuilds
everything from scratch. Use update.py for incremental updates.
"""

import pexpect
import sys
import time

# ── Configuration ─────────────────────────────────────────────────────────────
HOST       = "103.6.168.151"
USER       = "root"
PASS       = "Ehdrian@Bungubung09"
REPO       = "https://github.com/kwat0g/ogami-laravel.git"
APP_DIR    = "/var/www/ogami"
ENV_FILE   = "/home/kwat0g/Desktop/ogamiPHP/.env.production"
DOMAIN     = "ogamiph.dev"          # bare domain (no www)
ACME_EMAIL = "admin@ogamiph.dev"    # Let's Encrypt registration email

DB_NAME    = "ogami_erp"
DB_USER    = "ogami"
DB_PASS    = "secret"
REDIS_PASS = "secret"
# ──────────────────────────────────────────────────────────────────────────────


def banner(msg: str) -> None:
    bar = "=" * 62
    print(f"\n\033[32m{bar}\n  {msg}\n{bar}\033[0m", flush=True)


def step(msg: str) -> None:
    print(f"\n\033[33m>>> {msg[:140]}\033[0m", flush=True)


class VPS:
    """Thin wrapper around a persistent pexpect SSH session."""

    def __init__(self) -> None:
        step(f"Connecting to {USER}@{HOST} ...")
        self.c = pexpect.spawn(
            f"ssh -o StrictHostKeyChecking=no "
            f"-o ServerAliveInterval=30 "
            f"-o ConnectTimeout=15 "
            f"{USER}@{HOST}",
            timeout=30,
            encoding="utf-8",
        )
        self.c.logfile_read = sys.stdout
        idx = self.c.expect(["password:", r"[#$]\s*$", pexpect.TIMEOUT], timeout=30)
        if idx == 0:
            self.c.sendline(PASS)
            self.c.expect(r"[#$]", timeout=60)
        # Set a reliable, unique prompt and global env
        self.c.sendline(
            "export PS1='READY# ' && "
            "export DEBIAN_FRONTEND=noninteractive && "
            "export COMPOSER_ALLOW_SUPERUSER=1"
        )
        self.c.expect("READY#", timeout=30)
        print("  \033[32mConnected!\033[0m", flush=True)

    def run(self, cmd: str, timeout: int = 300) -> str:
        step(cmd)
        self.c.sendline(cmd)
        self.c.expect("READY#", timeout=timeout)
        return self.c.before

    def close(self) -> None:
        self.c.sendline("exit")
        self.c.close()


# ── Nginx configs ──────────────────────────────────────────────────────────────

def _nginx_http_only(domain: str, app_dir: str) -> str:
    """Minimal HTTP config used only while certbot obtains the certificate."""
    return f"""server {{
    listen 80;
    server_name {domain} www.{domain};
    root {app_dir}/public;
    index index.php;
    client_max_body_size 50M;

    # ACME challenge — must be reachable over plain HTTP
    location ^~ /.well-known/acme-challenge/ {{
        root /var/www/certbot;
        try_files $uri =404;
    }}

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }}

    location ~ /\\.(?!well-known) {{ deny all; }}
}}
"""


def _nginx_https(domain: str, app_dir: str) -> str:
    """
    Final production nginx config (HTTP redirect + HTTPS).
    Written AFTER certbot has issued the certificate.
    Includes fastcgi_param HTTPS so Laravel/Sanctum set secure cookies.
    """
    return f"""# HTTP → HTTPS redirect
server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};

    # Keep ACME renewal working
    location ^~ /.well-known/acme-challenge/ {{
        root /var/www/certbot;
        try_files $uri =404;
    }}

    location / {{
        return 301 https://{domain}$request_uri;
    }}
}}

# HTTPS — main application
server {{
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {domain} www.{domain};

    root {app_dir}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    # TLS — managed by certbot
    ssl_certificate     /etc/letsencrypt/live/{domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{domain}/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    # Security headers
    add_header X-Frame-Options        SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy        strict-origin-when-cross-origin;

    # Vite-built frontend assets (base = /build/)
    location ^~ /build/ {{
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }}

    location = /favicon.ico {{ log_not_found off; access_log off; }}
    location = /robots.txt  {{ log_not_found off; access_log off; }}

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    # PHP-FPM — include HTTPS params so Laravel knows the connection is TLS
    location ~ \\.php$ {{
        fastcgi_pass  unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS           on;
        fastcgi_param SERVER_PORT     443;
        include       fastcgi_params;
        fastcgi_read_timeout 300;
    }}

    location ~ /\\.(?!well-known) {{ deny all; }}
}}
"""


# ── Supervisor config ──────────────────────────────────────────────────────────

def _supervisor_conf(app_dir: str) -> str:
    return f"""[program:ogami-horizon]
command=php {app_dir}/artisan horizon
directory={app_dir}
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile={app_dir}/storage/logs/horizon.log
redirect_stderr=true

[program:ogami-reverb]
command=php {app_dir}/artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
directory={app_dir}
user=www-data
autostart=true
autorestart=true
stdout_logfile={app_dir}/storage/logs/reverb.log
redirect_stderr=true

[program:ogami-scheduler]
command=php {app_dir}/artisan schedule:work
directory={app_dir}
user=www-data
autostart=true
autorestart=true
stdout_logfile={app_dir}/storage/logs/scheduler.log
redirect_stderr=true

[program:ogami-pulse]
command=php {app_dir}/artisan pulse:work
directory={app_dir}
user=www-data
autostart=true
autorestart=true
stdout_logfile={app_dir}/storage/logs/pulse.log
redirect_stderr=true
"""


# ── Main deploy ────────────────────────────────────────────────────────────────

def deploy() -> None:
    banner("Ogami ERP — FRESH VPS Deployment")
    vps = VPS()

    # ── 1. OS check ─────────────────────────────────────────────────────────
    banner("1/12 — OS check")
    vps.run("uname -a && grep -E 'PRETTY|VERSION_ID' /etc/os-release")

    # ── 2. System packages ───────────────────────────────────────────────────
    banner("2/12 — System packages")
    # Kill any stuck apt/dpkg locks from previous runs
    vps.run("killall apt apt-get dpkg 2>/dev/null || true")
    vps.run("rm -f /var/lib/apt/lists/lock /var/cache/apt/archives/lock /var/lib/dpkg/lock* 2>/dev/null || true")
    vps.run("dpkg --configure -a 2>&1 | tail -3 || true", timeout=120)
    vps.run("apt-get update -qq 2>&1 | tail -2", timeout=300)
    vps.run(
        "apt-get install -y software-properties-common curl git unzip zip "
        "gnupg2 ca-certificates lsb-release apt-transport-https 2>&1 | tail -3",
        timeout=300,
    )
    vps.run("LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php 2>&1 | tail -3 || true", timeout=120)
    vps.run("apt-get update -qq 2>&1 | tail -2", timeout=300)
    vps.run(
        "apt-get install -y "
        "nginx redis-server supervisor "
        "postgresql postgresql-contrib "
        "certbot python3-certbot-nginx "
        "php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis "
        "php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip "
        "php8.3-gd php8.3-intl php8.3-bcmath php8.3-tokenizer "
        "php8.3-ctype php8.3-fileinfo php8.3-pcov "
        "2>&1 | tail -5",
        timeout=600,
    )
    vps.run("php8.3 --version 2>/dev/null || php --version")

    # ── 2b. Node.js 20 LTS + pnpm ────────────────────────────────────────────
    banner("2b/12 — Node.js 20 LTS + pnpm")
    vps.run(
        "if ! command -v node >/dev/null 2>&1 || [ $(node -v | cut -d. -f1 | tr -d 'v') -lt 18 ]; then "
        "  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - 2>&1 | tail -5 && "
        "  apt-get install -y nodejs 2>&1 | tail -3; "
        "fi && node -v && npm -v",
        timeout=180,
    )
    vps.run(
        "npm install -g pnpm@latest 2>&1 | tail -3 && "
        "ln -sf $(npm root -g)/pnpm/bin/pnpm.cjs /usr/local/bin/pnpm 2>/dev/null || true && "
        "pnpm -v || $(npm root -g)/.bin/pnpm -v",
        timeout=120,
    )

    # ── 3. Composer ──────────────────────────────────────────────────────────
    banner("3/12 — Composer")
    vps.run(
        "[ -f /usr/local/bin/composer ] || "
        "(curl -sS https://getcomposer.org/installer | "
        "php -- --install-dir=/usr/local/bin --filename=composer 2>&1) && "
        "composer --version",
        timeout=60,
    )

    # ── 4. PostgreSQL ─────────────────────────────────────────────────────────
    banner("4/12 — PostgreSQL (fresh)")
    # Stop all workers first so they release DB connections
    vps.run("supervisorctl stop all 2>/dev/null || true")
    vps.run("service postgresql start 2>/dev/null || systemctl start postgresql 2>/dev/null || true")
    time.sleep(3)
    # Terminate all connections to the DB so we can drop it
    vps.run(
        f"sudo -u postgres psql -c \""
        f"SELECT pg_terminate_backend(pid) FROM pg_stat_activity "
        f"WHERE datname='{DB_NAME}' AND pid <> pg_backend_pid();\" 2>&1 | tail -3"
    )
    # Drop and recreate DB for a clean slate
    vps.run(f"sudo -u postgres psql -c \"DROP DATABASE IF EXISTS {DB_NAME};\" 2>&1")
    vps.run(
        f"sudo -u postgres psql -c \""
        f"DO \\$\\$ BEGIN "
        f"IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='{DB_USER}') THEN "
        f"CREATE USER {DB_USER} WITH PASSWORD '{DB_PASS}' CREATEDB; "
        f"ELSE ALTER USER {DB_USER} WITH PASSWORD '{DB_PASS}'; "
        f"END IF; END \\$\\$;\" 2>&1"
    )
    vps.run(
        f"sudo -u postgres psql -c "
        f"\"CREATE DATABASE {DB_NAME} OWNER {DB_USER} "
        f"TEMPLATE template0 ENCODING 'UTF8';\" 2>&1"
    )
    vps.run(
        f"sudo -u postgres psql -c "
        f"\"GRANT ALL PRIVILEGES ON DATABASE {DB_NAME} TO {DB_USER};\" 2>&1"
    )
    vps.run("sudo -u postgres psql -l 2>&1 | grep ogami")

    # ── 5. Redis ─────────────────────────────────────────────────────────────
    banner("5/12 — Redis")
    vps.run(
        f"grep -q 'requirepass {REDIS_PASS}' /etc/redis/redis.conf || "
        f"echo 'requirepass {REDIS_PASS}' >> /etc/redis/redis.conf"
    )
    vps.run("service redis-server restart 2>/dev/null || systemctl restart redis-server 2>/dev/null || true")
    time.sleep(2)
    vps.run(f"redis-cli -a {REDIS_PASS} ping 2>/dev/null")

    # ── 6. Fresh clone of repository ──────────────────────────────────────────
    banner("6/12 — Repository (fresh clone)")
    vps.run("mkdir -p /var/www")
    # FRESH: remove any existing directory so we always start from a clean clone
    vps.run(f"rm -rf {APP_DIR}", timeout=60)
    vps.run(f"git clone {REPO} {APP_DIR} 2>&1", timeout=180)
    vps.run(f"ls {APP_DIR}")

    # ── 7. Deploy .env ────────────────────────────────────────────────────────
    banner("7/12 — .env file")
    with open(ENV_FILE, "r") as fh:
        env_content = fh.read().strip()
    # Single-quoted heredoc: no shell expansion, no escaping required.
    # Constraint: env file must not contain a line that is exactly "ENVEOF".
    vps.run(f"cat > {APP_DIR}/.env << 'ENVEOF'\n{env_content}\nENVEOF")
    vps.run(f"wc -l {APP_DIR}/.env && head -5 {APP_DIR}/.env")

    # ── 8. Composer install + Laravel bootstrap ───────────────────────────────
    banner("8/12 — Composer install & Laravel setup (fresh)")
    # Pre-create cache/storage dirs with open perms so artisan runs as root
    vps.run(
        f"mkdir -p {APP_DIR}/bootstrap/cache "
        f"{APP_DIR}/storage/logs "
        f"{APP_DIR}/storage/app/public "
        f"{APP_DIR}/storage/framework/sessions "
        f"{APP_DIR}/storage/framework/views "
        f"{APP_DIR}/storage/framework/cache && "
        f"chmod -R 777 {APP_DIR}/storage {APP_DIR}/bootstrap/cache"
    )
    vps.run(
        f"cd {APP_DIR} && "
        "composer install --no-dev --optimize-autoloader 2>&1 | tail -5",
        timeout=300,
    )

    # ── 8b. Build React frontend on VPS ──────────────────────────────────────
    banner("8b/12 — Frontend build (pnpm install + build)")
    vps.run(
        f"cd {APP_DIR} && "
        "pnpm install --no-frozen-lockfile 2>&1 | tail -5",
        timeout=300,
    )
    vps.run(
        f"cd {APP_DIR}/frontend && "
        "pnpm build 2>&1 | tail -10",
        timeout=300,
    )

    # Generate a fresh app key
    vps.run(f"cd {APP_DIR} && php artisan key:generate --force 2>&1")

    # FRESH: Temporarily bypass production guard for migrate:fresh
    vps.run(f"sed -i 's|^APP_ENV=.*|APP_ENV=local|' {APP_DIR}/.env")
    vps.run(f"cd {APP_DIR} && php artisan optimize:clear 2>&1")
    vps.run(f"cd {APP_DIR} && php artisan migrate:fresh --seed --force 2>&1", timeout=600)
    vps.run(f"sed -i 's|^APP_ENV=.*|APP_ENV=production|' {APP_DIR}/.env")

    vps.run(
        f"cd {APP_DIR} && "
        "php artisan config:cache && "
        "php artisan route:cache && "
        "php artisan view:cache && "
        "php artisan event:cache 2>&1"
    )
    vps.run(f"cd {APP_DIR} && php artisan storage:link --force 2>&1 || true")
    vps.run(
        f"chown -R www-data:www-data {APP_DIR}/storage {APP_DIR}/bootstrap/cache && "
        f"chmod -R 775 {APP_DIR}/storage {APP_DIR}/bootstrap/cache"
    )

    # ── 9. Nginx + Certbot (HTTPS) ────────────────────────────────────────────
    banner("9/12 — Nginx + Certbot (HTTPS)")

    vps.run("mkdir -p /var/www/certbot")

    # 9a. Deploy HTTP-only config so certbot ACME challenge can pass
    vps.run(
        f"cat > /etc/nginx/sites-available/ogami << 'NGINXEOF'\n"
        f"{_nginx_http_only(DOMAIN, APP_DIR)}"
        f"NGINXEOF"
    )
    vps.run(
        "ln -sf /etc/nginx/sites-available/ogami /etc/nginx/sites-enabled/ogami && "
        "rm -f /etc/nginx/sites-enabled/default"
    )
    vps.run("nginx -t 2>&1 && (service nginx restart 2>/dev/null || systemctl restart nginx 2>/dev/null)")
    vps.run("service php8.3-fpm start 2>/dev/null || systemctl start php8.3-fpm 2>/dev/null || true")
    vps.run("systemctl enable php8.3-fpm nginx 2>/dev/null || true")

    # 9b. Obtain certificate (skip if already issued — certbot is idempotent)
    vps.run(
        f"certbot certonly --nginx "
        f"-d {DOMAIN} -d www.{DOMAIN} "
        f"--email {ACME_EMAIL} --agree-tos --no-eff-email "
        f"--non-interactive --keep-until-expiring 2>&1",
        timeout=120,
    )

    # 9c. Activate auto-renewal timer
    vps.run("systemctl enable --now certbot.timer 2>&1 || true")

    # 9d. Write the full production HTTPS config (explicit — no fragile sed patching)
    vps.run(
        f"cat > /etc/nginx/sites-available/ogami << 'NGINXEOF'\n"
        f"{_nginx_https(DOMAIN, APP_DIR)}"
        f"NGINXEOF"
    )
    vps.run("nginx -t 2>&1 && systemctl reload nginx")

    # ── 10. Production .env overrides ─────────────────────────────────────────
    banner("10/12 — Production .env overrides")
    # These sed patches ensure the live .env always has the correct HTTPS / domain
    # values regardless of what was in .env.production (safe to re-run).
    vps.run(f"sed -i 's|APP_URL=.*|APP_URL=https://{DOMAIN}|' {APP_DIR}/.env")
    vps.run(f"sed -i 's/SESSION_DOMAIN=.*/SESSION_DOMAIN=.{DOMAIN}/' {APP_DIR}/.env")
    vps.run(f"sed -i 's/SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' {APP_DIR}/.env")
    vps.run(f"sed -i 's/SESSION_SAME_SITE=.*/SESSION_SAME_SITE=lax/' {APP_DIR}/.env")
    vps.run(
        f"sed -i 's/SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS={DOMAIN},www.{DOMAIN}/' "
        f"{APP_DIR}/.env"
    )
    # Patch REVERB WebSockets to use standard HTTPS port (proxied by Nginx)
    vps.run(
        f"sed -i "
        f"'s|^REVERB_HOST=.*|REVERB_HOST={DOMAIN}|;"
        f"s|^REVERB_PORT=.*|REVERB_PORT=443|;"
        f"s|^REVERB_SCHEME=.*|REVERB_SCHEME=https|' "
        f"{APP_DIR}/.env"
    )
    vps.run(
        f"sed -i "
        f"'s|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"{DOMAIN}\"|;"
        f"s|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=\"443\"|;"
        f"s|^VITE_REVERB_SCHEME=.*|VITE_REVERB_SCHEME=\"https\"|' "
        f"{APP_DIR}/.env"
    )
    vps.run(f"cd {APP_DIR} && php artisan config:clear && php artisan config:cache 2>&1")

    # ── 11. Supervisor (horizon / reverb / scheduler) ─────────────────────────
    banner("11/12 — Supervisor")
    vps.run(
        f"cat > /etc/supervisor/conf.d/ogami.conf << 'SUPEOF'\n"
        f"{_supervisor_conf(APP_DIR)}"
        f"SUPEOF"
    )
    vps.run(
        "service supervisor start 2>/dev/null || "
        "systemctl start supervisor 2>/dev/null || true"
    )
    vps.run("systemctl enable supervisor 2>/dev/null || true")
    time.sleep(2)
    vps.run("supervisorctl reread && supervisorctl update 2>&1")
    # Restart if already running (update will start new programs automatically)
    vps.run(
        "supervisorctl restart ogami-horizon ogami-reverb ogami-scheduler ogami-pulse 2>&1 || "
        "supervisorctl start   ogami-horizon ogami-reverb ogami-scheduler ogami-pulse 2>&1 || true"
    )
    vps.run("supervisorctl status 2>&1")

    # ── 12. Final permissions & service enable ────────────────────────────────
    banner("12/12 — Final permissions & service enable")
    vps.run(
        "systemctl enable nginx postgresql redis-server supervisor php8.3-fpm 2>/dev/null || true"
    )
    vps.run(
        f"chown -R www-data:www-data {APP_DIR} && "
        f"find {APP_DIR} -type f -name '*.php' -exec chmod 644 {{}} \\; 2>/dev/null | head -1 || true"
    )
    vps.run(
        f"chmod -R 775 {APP_DIR}/storage {APP_DIR}/bootstrap/cache"
    )

    # ── Verification ──────────────────────────────────────────────────────────
    banner("Verification")
    time.sleep(3)
    vps.run(f"curl -si http://{DOMAIN}/ 2>&1 | head -5", timeout=15)
    vps.run(f"curl -sk https://{DOMAIN}/api/health 2>&1", timeout=15)
    vps.run("certbot certificates 2>&1 | grep -E 'Domains|Expiry|Certificate'", timeout=15)

    vps.close()

    banner("DEPLOYMENT COMPLETE")
    print(f"  URL     : https://{DOMAIN}")
    print(f"  Health  : https://{DOMAIN}/api/health")
    print(f"  Horizon : https://{DOMAIN}/horizon")
    print()
    print("  Credentials:")
    print("    admin@ogamierp.com        / password")
    print("    hr_manager@ogamierp.com   / password")
    print()


if __name__ == "__main__":
    deploy()
