# =============================================================================
# Ogami ERP — Windows Dev Launcher
# Just double-click start.bat  OR  run: .\start.ps1
# Auto-detects your setup — no flags needed.
# =============================================================================
$ErrorActionPreference = "Stop"
$Root   = $PSScriptRoot
$LogDir = "$Root\storage\logs"

function OK($m)   { Write-Host "  OK  $m" -ForegroundColor Green }
function INFO($m) { Write-Host "  >>  $m" -ForegroundColor Cyan }
function WARN($m) { Write-Host "  !!  $m" -ForegroundColor Yellow }
function ERR($m)  { Write-Host "  XX  $m" -ForegroundColor Red; exit 1 }

Write-Host ""
Write-Host "  Ogami ERP - Dev Server" -ForegroundColor Cyan
Write-Host "  ----------------------------------------" -ForegroundColor DarkGray
Write-Host ""

# ── Check required tools ──────────────────────────────────────────────────────
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    ERR "Docker not found. Install Docker Desktop: https://www.docker.com/products/docker-desktop"
}
if (-not (Get-Command pnpm -ErrorAction SilentlyContinue)) {
    ERR "pnpm not found. Run in PowerShell: npm install -g pnpm"
}

# ── First-run: create .env if missing ────────────────────────────────────────
if (-not (Test-Path "$Root\.env")) {
    WARN ".env not found - copying from .env.example..."
    Copy-Item "$Root\.env.example" "$Root\.env"
    Write-Host ""
    Write-Host "  ----------------------------------------" -ForegroundColor Yellow
    Write-Host "  ACTION REQUIRED: Open .env and fill in:" -ForegroundColor Yellow
    Write-Host "    DB_PASSWORD, REDIS_PASSWORD, APP_KEY"  -ForegroundColor Yellow
    Write-Host "  Then run start.bat again."               -ForegroundColor Yellow
    Write-Host "  ----------------------------------------" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "  Press Enter to exit"
    exit 0
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# ── Auto-detect: PHP installed locally? ──────────────────────────────────────
$hasPhp = [bool](Get-Command php -ErrorAction SilentlyContinue)

# =============================================================================
# DOCKER-ONLY MODE  (no PHP installed)
# Everything runs inside Docker containers — just needs Docker Desktop + pnpm
# =============================================================================
if (-not $hasPhp) {
    INFO "PHP not found locally - starting everything in Docker..."
    Write-Host ""

    & docker compose up -d --build
    if ($LASTEXITCODE -ne 0) { ERR "docker compose failed - is Docker Desktop running?" }

    INFO "Running database migrations..."
    Start-Sleep 5
    & docker compose exec app php artisan migrate --force 2>$null
    OK "Ready"

    Write-Host ""
    Write-Host "  ----------------------------------------" -ForegroundColor DarkGray
    Write-Host "  Open: http://localhost:5173" -ForegroundColor Green
    Write-Host "  ----------------------------------------" -ForegroundColor DarkGray
    Write-Host "  Press Ctrl+C to stop" -ForegroundColor Yellow
    Write-Host ""

    Set-Location "$Root\frontend"
    try {
        & pnpm dev
    } finally {
        Set-Location $Root
        Write-Host "`n  Stopping containers..." -ForegroundColor Yellow
        & docker compose down
        Write-Host "  Done." -ForegroundColor Green
    }
    exit 0
}

# =============================================================================
# HYBRID MODE  (PHP installed locally)
# Docker for databases only — faster, uses your local PHP + Vite
# =============================================================================
INFO "Starting database containers (PostgreSQL + Redis)..."
& docker compose up -d postgres redis
if ($LASTEXITCODE -ne 0) { ERR "Failed to start containers - is Docker Desktop running?" }

# Wait for postgres to accept connections
$tries = 0
do {
    Start-Sleep 1; $tries++
    docker exec ogami_postgres pg_isready -U ogami 2>$null | Out-Null
} while ($LASTEXITCODE -ne 0 -and $tries -lt 30)
OK "PostgreSQL ready"
OK "Redis ready"

& php artisan config:clear -q
& php artisan config:cache -q

# ── Laravel ───────────────────────────────────────────────────────────────────
INFO "Starting Laravel..."
$laravel = Start-Process php `
    -ArgumentList "artisan","serve","--host=127.0.0.1","--port=8000" `
    -WorkingDirectory $Root `
    -RedirectStandardOutput "$LogDir\serve.log" `
    -RedirectStandardError  "$LogDir\serve-err.log" `
    -PassThru -WindowStyle Hidden

$tries = 0; $up = $false
do {
    Start-Sleep 1; $tries++
    try { $null = Invoke-WebRequest "http://127.0.0.1:8000/up" -UseBasicParsing -TimeoutSec 1; $up = $true }
    catch {}
} while (-not $up -and $tries -lt 20)
OK "Laravel ready"

# ── Queue worker ──────────────────────────────────────────────────────────────
INFO "Starting queue worker..."
$queue = Start-Process php `
    -ArgumentList "artisan","queue:work","redis",
        "--queue=notifications,default,payroll,computations",
        "--tries=3","--sleep=3","--timeout=600" `
    -WorkingDirectory $Root `
    -RedirectStandardOutput "$LogDir\queue.log" `
    -RedirectStandardError  "$LogDir\queue-err.log" `
    -PassThru -WindowStyle Hidden
OK "Queue worker ready"

# ── Vite ──────────────────────────────────────────────────────────────────────
INFO "Starting Vite..."
$vite = Start-Process pnpm `
    -ArgumentList "dev" `
    -WorkingDirectory "$Root\frontend" `
    -RedirectStandardOutput "$LogDir\vite.log" `
    -RedirectStandardError  "$LogDir\vite-err.log" `
    -PassThru -WindowStyle Hidden
Start-Sleep 3
OK "Vite ready"

Write-Host ""
Write-Host "  ----------------------------------------" -ForegroundColor DarkGray
Write-Host "  Open: http://localhost:5173" -ForegroundColor Green
Write-Host "  ----------------------------------------" -ForegroundColor DarkGray
Write-Host "  Press Ctrl+C to stop everything" -ForegroundColor Yellow
Write-Host ""

try {
    Get-Content "$LogDir\serve.log" -Wait
} finally {
    Write-Host "`n  Shutting down..." -ForegroundColor Yellow
    $laravel | Stop-Process -Force -ErrorAction SilentlyContinue
    $queue   | Stop-Process -Force -ErrorAction SilentlyContinue
    $vite    | Stop-Process -Force -ErrorAction SilentlyContinue
    Write-Host "  Done." -ForegroundColor Green
}
