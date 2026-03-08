# Ogami ERP — Running on Windows via WSL2

The easiest way to run this project on Windows is through **WSL2** (Windows Subsystem for Linux). It gives you a real Ubuntu environment inside Windows so you can run everything exactly the same way as on Linux — same commands, no reconfiguring.

---

## Part 1 — One-Time Windows Setup

### Step 1: Install WSL2 + Ubuntu

Open **PowerShell as Administrator** (right-click PowerShell → Run as administrator):

```powershell
wsl --install
```

This installs WSL2 and Ubuntu automatically. **Restart your computer** when it finishes.

After restart, Ubuntu opens automatically. Set a username and password when prompted (this is your Linux account — remember it).

---

### Step 2: Install Docker Desktop

1. Download from: https://www.docker.com/products/docker-desktop
2. Run the installer — accept all defaults
3. After install, open **Docker Desktop** → Settings → Resources → WSL Integration
4. Toggle on your **Ubuntu** distro → click **Apply & Restart**

Docker Desktop is needed to run PostgreSQL and Redis. Once integrated with WSL2, it works transparently from inside Ubuntu.

---

### Step 3: Install the VS Code WSL Extension

1. Open VS Code
2. Press `Ctrl+Shift+X` (Extensions panel)
3. Search **WSL** → install the one by **Microsoft**

This lets VS Code connect directly into WSL so you never need a separate Ubuntu window.

---

## Part 2 — Project Setup (Inside Ubuntu)

Everything from here runs inside the **Ubuntu terminal**.

Open it one of two ways:
- Search **"Ubuntu"** in the Windows Start menu, or
- Open VS Code → `Ctrl+Shift+P` → **"WSL: Open Folder in WSL"** (then the VS Code terminal is Ubuntu)

---

### Step 4: Install PHP 8.3

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mbstring php8.3-xml php8.3-zip \
    php8.3-pgsql php8.3-curl php8.3-bcmath php8.3-intl php8.3-redis
```

Verify:
```bash
php --version
# Should show: PHP 8.3.x
```

---

### Step 5: Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Verify:
```bash
composer --version
```

---

### Step 6: Install Node.js + pnpm

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
npm install -g pnpm
```

Verify:
```bash
node --version    # Should show v22.x
pnpm --version    # Should show 10.x
```

---

### Step 7: Clone the Project

```bash
cd ~
git clone <your-repo-url> ogamiPHP
cd ogamiPHP
```

---

### Step 8: Install Dependencies

```bash
composer install
cd frontend && pnpm install && cd ..
```

---

### Step 9: Create Your .env File

```bash
cp .env.example .env
```

Open it in VS Code:
```bash
code .env
```

Make sure these lines are set (defaults from `.env.example` work fine locally):

```dotenv
DB_HOST=127.0.0.1
DB_DATABASE=ogami_erp
DB_USERNAME=ogami
DB_PASSWORD=secret

REDIS_PASSWORD=secret
```

Generate the app key:
```bash
php artisan key:generate
```

---

### Step 10: Set Up the Database (First Time Only)

Make sure the project is running first (see Step 11), then in a **second terminal**:

```bash
php artisan migrate
php artisan db:seed
```

Default admin login after seeding:

| Field    | Value                  |
|----------|------------------------|
| Email    | `admin@ogamierp.local` |
| Password | `password`             |

---

## Part 3 — Daily Use

### Starting the project

```bash
cd ~/ogamiPHP
npm run dev
```

Open your browser: **http://localhost:5173**

That's it — identical to running on Linux.

### Stopping the project

Press `Ctrl+C` in the terminal.

---

## Opening in VS Code (Recommended)

Once WSL is set up, always open the project this way for the best experience:

1. Open VS Code
2. Press `Ctrl+Shift+P`
3. Type **WSL: Open Folder in WSL** → select `~/ogamiPHP`
4. VS Code reconnects into Ubuntu — the bottom-left corner shows `>< WSL: Ubuntu`
5. Open the terminal (`Ctrl+`` `) — it's a real Ubuntu bash terminal
6. Run `npm run dev`

All VS Code features (IntelliSense, extensions, debugging) work natively inside WSL.

---

## Troubleshooting

### Docker not found inside WSL
Make sure Docker Desktop WSL Integration is enabled:
Docker Desktop → Settings → Resources → WSL Integration → toggle Ubuntu on → Apply & Restart.

### `php` command not found
Run the install command from Step 4 again. If `php8.3` installs but `php` doesn't resolve, add a symlink:
```bash
sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 83
```

### Port 5432 or 6379 already in use
Another process is using the database port. Check and stop it:
```bash
sudo lsof -i :5432    # find what's using PostgreSQL port
sudo lsof -i :6379    # find what's using Redis port
```

### `pnpm: command not found`
Close and reopen your Ubuntu terminal after installing pnpm — it needs to reload the PATH.

### Slow file access
Keep your project files inside the WSL filesystem (`~/ogamiPHP`), **not** under `/mnt/c/...` (Windows drives). WSL file I/O to `/mnt/c` is slow — always clone into `~`.
