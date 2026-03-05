#!/usr/bin/env python3
"""
Ogami ERP — quick production update via pexpect SSH.

Use this after pushing bug fixes or new features to main.
Does NOT touch OS packages, nginx, certbot, PostgreSQL, or Redis.

Usage:
    python3 update.py
"""

import pexpect
import sys
import time
import subprocess
import os

# ── Configuration (must match deploy.py) ─────────────────────────────────────
HOST    = "45.151.155.64"
USER    = "root"
PASS    = "Gustokonamamatay@09"
APP_DIR = "/var/www/ogami"
DOMAIN  = "ogamiph.dev"
# ─────────────────────────────────────────────────────────────────────────────


def banner(msg: str) -> None:
    bar = "=" * 62
    print(f"\n\033[32m{bar}\n  {msg}\n{bar}\033[0m", flush=True)


def step(msg: str) -> None:
    print(f"\n\033[33m>>> {msg[:140]}\033[0m", flush=True)


class VPS:
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
        idx = self.c.expect(["password:", r"[$#]\s*$", pexpect.TIMEOUT], timeout=20)
        if idx == 0:
            self.c.sendline(PASS)
            self.c.expect(r"[$#]\s*$", timeout=20)
        self.c.sendline(
            "export PS1='READY# ' && "
            "export COMPOSER_ALLOW_SUPERUSER=1"
        )
        self.c.expect("READY#", timeout=10)
        print("  \033[32mConnected!\033[0m", flush=True)

    def run(self, cmd: str, timeout: int = 120) -> str:
        step(cmd)
        self.c.sendline(cmd)
        self.c.expect("READY#", timeout=timeout)
        return self.c.before

    def close(self) -> None:
        self.c.sendline("exit")
        self.c.close()


def switch_env(target: str) -> None:
    """Switch local .env using env.py."""
    script = os.path.join(os.path.dirname(os.path.abspath(__file__)), "env.py")
    result = subprocess.run([sys.executable, script, target], capture_output=False)
    if result.returncode != 0:
        print(f"\033[31m  ✘ Failed to switch env to '{target}'. Aborting.\033[0m")
        sys.exit(1)


def update() -> None:
    banner("Ogami ERP — Production Update")

    # Switch local .env to production before doing anything
    banner("0/5 — Switch local env → production")
    switch_env("production")

    vps = VPS()

    # ── 1. Pull latest code ───────────────────────────────────────────────────
    banner("1/5 — Pull latest code")
    vps.run(
        f"cd {APP_DIR} && "
        "git fetch --all 2>&1 && "
        "git reset --hard origin/$(git symbolic-ref --short HEAD) 2>&1"
    )
    vps.run(f"cd {APP_DIR} && git log --oneline -5")

        # ── 1.5. Configure WebSocket proxy + fix Reverb public env ──────────────
    banner("1.5/5 — Nginx WebSocket proxy + Reverb env")

    # Update REVERB_HOST/PORT/SCHEME in production .env so SpaController
    # injects the correct wss://ogamiph.dev config into the HTML at runtime
    vps.run(
        "sed -i "
        "'s|^REVERB_HOST=.*|REVERB_HOST=ogamiph.dev|;"
        "s|^REVERB_PORT=.*|REVERB_PORT=443|;"
        "s|^REVERB_SCHEME=.*|REVERB_SCHEME=https|' "
        f"{APP_DIR}/.env"
    )
    vps.run(f"grep 'REVERB_HOST\\|REVERB_PORT\\|REVERB_SCHEME' {APP_DIR}/.env")

    # Add nginx WebSocket reverse-proxy location for Reverb (idempotent)
    # Use the known nginx site config path (sites-available/ogami, linked from sites-enabled/ogami)
    nginx_conf = "/etc/nginx/sites-available/ogami"
    vps.run(
        f"if ! grep -q 'proxy_pass.*8080' {nginx_conf} 2>/dev/null; then "
        f"  python3 -c \""
        f"f=open('{nginx_conf}','r'); c=f.read(); f.close(); "
        "block='\\n    # Reverb WebSocket reverse proxy\\n"
        "    location ~ ^/app/ {\\n"
        "        proxy_pass             http://127.0.0.1:8080;\\n"
        "        proxy_http_version     1.1;\\n"
        "        proxy_set_header       Upgrade \\$http_upgrade;\\n"
        "        proxy_set_header       Connection \\\"Upgrade\\\";\\n"
        "        proxy_set_header       Host \\$host;\\n"
        "        proxy_read_timeout     60;\\n"
        "    }\\n'; "
        f"f=open('{nginx_conf}','w'); f.write(c.replace('location ~ /\\\\\\\\.ht', block+'    location ~ /\\\\\\\\.ht')); f.close(); "
        "print('added')\" && "
        "  nginx -t && nginx -s reload && echo 'Nginx WebSocket proxy added ✔'; "
        "else echo 'Nginx WebSocket proxy already configured ✔'; fi",
        timeout=30,
    )

        # ── 2. Composer (only if composer.lock changed) ───────────────────────────
    banner("2/5 — Composer install")
    vps.run(
        f"cd {APP_DIR} && "
        "composer install --no-dev --optimize-autoloader 2>&1 | tail -5",
        timeout=180,
    )

    # ── 3. Database migrations ────────────────────────────────────────────────
    banner("3/5 — Migrations")
    vps.run(f"cd {APP_DIR} && php artisan migrate --force 2>&1", timeout=120)

    # ── 4. Rebuild Laravel caches ─────────────────────────────────────────────
    banner("4/5 — Rebuild caches")
    vps.run(
        f"cd {APP_DIR} && "
        "php artisan cache:clear && "        # flush Redis runtime cache
        "php artisan config:clear && "
        "php artisan config:cache && "
        "php artisan route:cache && "
        "php artisan view:cache && "
        "php artisan event:cache 2>&1"
    )
    vps.run(
        f"chown -R www-data:www-data {APP_DIR}/storage {APP_DIR}/bootstrap/cache"
    )

    # ── 5. Restart queue workers ──────────────────────────────────────────────
    banner("5/5 — Restart workers")
    # horizon:terminate does a graceful restart (finishes current jobs first)
    vps.run(f"cd {APP_DIR} && php artisan horizon:terminate 2>&1 || true")
    time.sleep(3)
    vps.run(
        "supervisorctl restart ogami-horizon ogami-reverb ogami-scheduler 2>&1"
    )
    vps.run("supervisorctl status 2>&1")

    # ── Verification ──────────────────────────────────────────────────────────
    banner("Verification")
    time.sleep(2)
    vps.run(f"curl -sk https://{DOMAIN}/api/health 2>&1", timeout=15)

    vps.close()

    # Switch local .env back to local development
    banner("Switch local env → local")
    switch_env("local")

    banner("UPDATE COMPLETE")
    print(f"  URL    : https://{DOMAIN}")
    print(f"  Health : https://{DOMAIN}/api/health")
    print()


if __name__ == "__main__":
    update()
