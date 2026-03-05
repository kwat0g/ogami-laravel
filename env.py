#!/usr/bin/env python3
"""
Ogami ERP — environment switcher.

Usage:
    python3 env.py local       # switch to local development
    python3 env.py production  # switch to production config

Files managed:
    .env            — active config (read by Laravel)
    .env.local      — local development values
    .env.production — production values (deployed to VPS by deploy.py)
"""

import sys
import shutil
import subprocess
import os

ROOT = os.path.dirname(os.path.abspath(__file__))

ENVS = {
    "local":      ".env.local",
    "production": ".env.production",
}

GREEN  = "\033[32m"
YELLOW = "\033[33m"
RED    = "\033[31m"
NC     = "\033[0m"


def current_env() -> str:
    """Read APP_ENV from the active .env file."""
    env_path = os.path.join(ROOT, ".env")
    if not os.path.exists(env_path):
        return "unknown"
    with open(env_path) as f:
        for line in f:
            if line.startswith("APP_ENV="):
                return line.strip().split("=", 1)[1].strip('"').strip("'")
    return "unknown"


def switch(target: str) -> None:
    source_file = ENVS[target]
    source_path = os.path.join(ROOT, source_file)
    dest_path   = os.path.join(ROOT, ".env")

    if not os.path.exists(source_path):
        print(f"{RED}  ✘ {source_file} not found.{NC}")
        sys.exit(1)

    shutil.copy2(source_path, dest_path)
    print(f"{GREEN}  ✔ .env updated from {source_file}{NC}")

    # Clear Laravel's cached config so the change takes effect immediately
    artisan = os.path.join(ROOT, "artisan")
    if os.path.exists(artisan):
        subprocess.run(["php", artisan, "config:clear"], cwd=ROOT, capture_output=True)
        print(f"{GREEN}  ✔ Laravel config cache cleared{NC}")

    print()
    if target == "local":
        print(f"  APP_URL : http://localhost:8000")
        print(f"  DB      : ogami_erp (local PostgreSQL)")
        print(f"  Run     : npm run dev")
    else:
        print(f"  APP_URL : https://ogamiph.dev")
        print(f"  DB      : ogami_erp (production VPS)")
        print(f"  ⚠  This is the PRODUCTION config — do not run locally with this.")
    print()


def status() -> None:
    env = current_env()
    colour = GREEN if env == "local" else YELLOW if env == "production" else NC
    print(f"\n  Active environment: {colour}{env}{NC}\n")


def main() -> None:
    if len(sys.argv) < 2:
        status()
        print(f"  Usage: python3 env.py [local|production]\n")
        sys.exit(0)

    target = sys.argv[1].lower()

    if target not in ENVS:
        print(f"{RED}  ✘ Unknown environment '{target}'. Use: local | production{NC}\n")
        sys.exit(1)

    env = current_env()
    print(f"\n  Switching: {env}  →  {target}\n")
    switch(target)


if __name__ == "__main__":
    main()
