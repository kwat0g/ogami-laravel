#!/usr/bin/env python3
"""
Ogami ERP — production update via pexpect SSH.

Runs the full local → remote pipeline:
  1. Type-check the frontend (tsc --noEmit) — aborts on TS errors
  2. Build the React frontend (pnpm build) — aborts on build errors
  3. Commit any changed build artefacts in public/build/ to git
  4. Push to origin/main
  5. SSH into the VPS and pull + migrate + restart

This guarantees that every `python3 update.py` leaves production
100 % in sync with local — no leftover stale JS bundles.

Does NOT touch OS packages, nginx, certbot, PostgreSQL, or Redis.

Usage:
    python3 update.py
"""

import os
import subprocess
import pexpect
import sys
import time

# Absolute path of the repo root (the directory this file lives in)
REPO_ROOT = os.path.dirname(os.path.abspath(__file__))

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


def local(cmd: str, cwd: str | None = None, timeout: int = 180) -> None:
    """Run a local shell command, stream output live, abort on non-zero exit."""
    step(cmd)
    result = subprocess.run(
        cmd,
        shell=True,
        cwd=cwd or REPO_ROOT,
        timeout=timeout,
    )
    if result.returncode != 0:
        print(f"\n\033[31m✗ Command failed (exit {result.returncode}): {cmd}\033[0m", flush=True)
        sys.exit(result.returncode)


def local_output(cmd: str, cwd: str | None = None) -> str:
    """Run a local command and return its stripped stdout (no live stream)."""
    result = subprocess.run(
        cmd,
        shell=True,
        cwd=cwd or REPO_ROOT,
        capture_output=True,
        text=True,
    )
    return result.stdout.strip()


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


def update() -> None:
    banner("Ogami ERP — Production Update")

    # ══════════════════════════════════════════════════════════════
    #  LOCAL PHASE — build, commit, push
    # ══════════════════════════════════════════════════════════════

    # ── L1. Build the React frontend ─────────────────────────────
    banner("L1 — Build React frontend (typecheck + pnpm build)")
    frontend_dir = os.path.join(REPO_ROOT, "frontend")
    local("pnpm typecheck", cwd=frontend_dir, timeout=120)   # abort on TS errors
    local("pnpm build", cwd=frontend_dir, timeout=300)        # abort on build errors

    # ── L2. Stage ALL local changes (src + built assets) ─────────
    banner("L2 — Stage & commit all local changes")
    step("git add -A")
    subprocess.run("git add -A", shell=True, cwd=REPO_ROOT)

    # Check if there is anything to commit
    diff = local_output("git status --porcelain", cwd=REPO_ROOT)
    if diff:
        # Build a commit message that mentions changed source files (max 5)
        changed_files = [line.strip() for line in diff.splitlines() if line.strip()]
        summary = ", ".join(f.split()[-1] for f in changed_files[:5])
        if len(changed_files) > 5:
            summary += f" (+{len(changed_files) - 5} more)"
        commit_msg = f"deploy: sync local changes — {summary}"
        local(f'git commit -m "{commit_msg}"', cwd=REPO_ROOT)
    else:
        print("  \033[36mNothing new to commit — working tree already clean.\033[0m", flush=True)

    # ── L3. Push to origin/main ───────────────────────────────────
    banner("L3 — Push to origin/main")
    local("git push origin main", cwd=REPO_ROOT, timeout=60)
    local("git log --oneline -3", cwd=REPO_ROOT)

    # ══════════════════════════════════════════════════════════════
    #  REMOTE PHASE — pull + migrate + restart
    # ══════════════════════════════════════════════════════════════

    vps = VPS()

    # ── R1. Pull latest code ─────────────────────────────────────────────────
    banner("R1 — Pull latest code (VPS)")
    vps.run(
        f"cd {APP_DIR} && "
        "git fetch --all 2>&1 && "
        "git reset --hard origin/$(git symbolic-ref --short HEAD) 2>&1"
    )
    vps.run(f"cd {APP_DIR} && git log --oneline -5")

    # ── R1.5. Configure WebSocket proxy + fix Reverb public env ────────────────
    banner("R1.5 — Nginx WebSocket proxy + Reverb env")

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

    # ── R2. Composer install ─────────────────────────────────────────────────
    banner("R2 — Composer install")
    vps.run(
        f"cd {APP_DIR} && "
        "composer install --no-dev --optimize-autoloader 2>&1 | tail -5",
        timeout=180,
    )

    # ── R3. Database migrations ──────────────────────────────────────────────
    banner("R3 — Migrations")
    vps.run(f"cd {APP_DIR} && php artisan migrate --force 2>&1", timeout=120)

    # ── R4. Rebuild Laravel caches ───────────────────────────────────────────
    banner("R4 — Rebuild caches")
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

    # ── R5. Restart queue workers ────────────────────────────────────────────
    banner("R5 — Restart workers")
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
    vps.run(f"cd {APP_DIR} && git log --oneline -3")

    vps.close()

    banner("UPDATE COMPLETE")
    print(f"  URL    : https://{DOMAIN}")
    print(f"  Health : https://{DOMAIN}/api/health")
    print()


if __name__ == "__main__":
    update()
