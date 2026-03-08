# Ogami ERP — Windows Setup Guide

Complete step-by-step guide to running Ogami ERP on a Windows machine.

---

## What You Need (Prerequisites)

Before starting, install these 3 tools. You only do this once.

### 1. Docker Desktop
Docker runs the databases (PostgreSQL + Redis) so you don't have to install them manually.

1. Go to: https://www.docker.com/products/docker-desktop
2. Download **Docker Desktop for Windows**
3. Run the installer — accept all defaults
4. When asked, make sure **"Use WSL 2"** is checked
5. Restart your computer when prompted
6. After restart, open Docker Desktop and wait until it says **"Engine running"** in the bottom left

> **WSL 2 not installed?** Open PowerShell as Administrator and run:
> ```powershell
> wsl --install
> ```
> Then restart your computer.

---

### 2. Git
Used to download (clone) the project.

1. Go to: https://git-scm.com/download/win
2. Download and run the installer
3. Accept all defaults — just keep clicking Next

---

### 3. Node.js + pnpm
Node.js runs the frontend (React). pnpm is the package manager.

1. Go to: https://nodejs.org
2. Download the **LTS version** (e.g. 22.x)
3. Run the installer — accept all defaults
4. After install, open **PowerShell** and run:
   ```powershell
   npm install -g pnpm
   ```
5. Verify it worked:
   ```powershell
   pnpm --version
   ```
   You should see a version number like `10.x.x`

---

## Step 1 — Get the Project

Open **PowerShell** (search for it in the Start menu):

```powershell
# Navigate to where you want the project (e.g. your Desktop)
cd C:\Users\YourName\Desktop

# Clone the repository
git clone <your-repo-url> ogamiPHP

# Enter the project folder
cd ogamiPHP
```

---

## Step 2 — Install Dependencies

Still in PowerShell, inside the `ogamiPHP` folder:

```powershell
# Install PHP dependencies (Composer must be installed — see note below)
composer install

# Install frontend dependencies
cd frontend
pnpm install
cd ..
```

> **Composer not installed?**
> Download from: https://getcomposer.org/Composer-Setup.exe
> Run the installer, then close and reopen PowerShell.

---

## Step 3 — Create Your .env File

The `.env` file holds your app configuration and database passwords.

```powershell
# Copy the example file
copy .env.example .env
```

Now open `.env` in Notepad (or any text editor):

```powershell
notepad .env
```

Find and update these lines:

```dotenv
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_DATABASE=ogami_erp
DB_USERNAME=ogami
DB_PASSWORD=secret          # ← change this to any password you want

REDIS_PASSWORD=secret       # ← change this to any password you want
```

> **Everything else can stay as-is for local development.**
> Just make sure `DB_PASSWORD` and `REDIS_PASSWORD` in `.env` match what's in `docker-compose.yml` (they already match if you copied from `.env.example`).

Save and close Notepad.

---

## Step 4 — Generate an App Key

```powershell
php artisan key:generate
```

This fills in `APP_KEY=` in your `.env` file automatically.

---

## Step 5 — Start the Project

**Just double-click `start.bat`** in the project folder.

Or from PowerShell:

```powershell
.\start.ps1
```

> **First time running?** PowerShell may ask about execution policy. Type `Y` and press Enter.

The script will:
1. Start PostgreSQL and Redis in Docker
2. Start the Laravel backend on http://127.0.0.1:8000
3. Start the queue worker
4. Start the Vite frontend on http://localhost:5173

When you see `OK  Vite ready` — open your browser and go to:

```
http://localhost:5173
```

---

## Step 6 — Set Up the Database (First Time Only)

While the project is running, open a **second PowerShell window** in the same folder:

```powershell
# Create all tables
php artisan migrate

# Fill with sample data (roles, salary grades, departments, sample employees)
php artisan db:seed
```

You should see `Seeding complete.` when done.

Now you can log in at http://localhost:5173 with the admin account:

| Field | Value |
|-------|-------|
| Email | `admin@ogamierp.local` |
| Password | `password` |

---

## Stopping the Project

Press **Ctrl+C** in the `start.bat` window (or the PowerShell window).

This stops Laravel, Vite, and the queue worker. The Docker containers (databases) stop too.

---

## Starting Again Next Time

Just double-click **`start.bat`** again. No setup needed — it remembers everything.

---

## Troubleshooting

### "Docker is not running" error
Open Docker Desktop from the Start menu and wait for **"Engine running"** to appear. Then try again.

### Port 5432 already in use
You have a local PostgreSQL installed. Either:
- Stop it: open **Services** (search in Start menu) → find `postgresql` → right-click → Stop
- Or change the port in `docker-compose.yml`: `"5433:5432"` then update `DB_PORT=5433` in `.env`

### Port 6379 already in use
Same idea — stop your local Redis service, or change the port.

### "composer: command not found"
Install Composer from https://getcomposer.org/Composer-Setup.exe then close and reopen PowerShell.

### "pnpm: command not found"
Run: `npm install -g pnpm` then close and reopen PowerShell.

### `.env` file keeps getting reset
Make sure you're editing the `.env` file in the **root** of the project (`ogamiPHP\.env`), not `.env.example`.

### Blank page or login doesn't work
The app might still be starting up. Wait 10–15 seconds and refresh. If still broken, check `storage\logs\serve.log` for errors.

### "php artisan" not found
PHP is not installed locally or not in your PATH. For local PHP:
1. Download from: https://windows.php.net/download (Thread Safe, x64)
2. Extract to `C:\php`
3. Add `C:\php` to your **System PATH**: Search "environment variables" in Start → Edit → PATH → New → `C:\php`
4. Reopen PowerShell

Alternatively, if you don't want to install PHP, you can run artisan commands through Docker:
```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

---

## Summary

| Step | What it does | Run once? |
|------|-------------|-----------|
| Install Docker Desktop | Runs PostgreSQL + Redis | ✅ Once |
| Install Git | Download the project | ✅ Once |
| Install Node.js + pnpm | Run the frontend | ✅ Once |
| Install Composer | Install PHP packages | ✅ Once |
| `git clone` | Get the project files | ✅ Once |
| `composer install` + `pnpm install` | Install dependencies | ✅ Once (re-run after `git pull`) |
| Copy `.env.example` to `.env` | Configure the app | ✅ Once |
| `php artisan key:generate` | Generate app secret | ✅ Once |
| `php artisan migrate --seed` | Set up the database | ✅ Once |
| **Double-click `start.bat`** | Start the project | 🔁 Every time |
