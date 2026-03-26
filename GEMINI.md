# Ogami ERP - Gemini Development Environment

This document provides a guide for developers using the Gemini CLI to work on the Ogami ERP project.

## Project Overview

Ogami ERP is a comprehensive, full-stack manufacturing resource planning system built for the Philippine market. It features a modular architecture covering domains such as HR, accounting, inventory, and procurement.

**Backend:**
- **Framework:** Laravel 11
- **Language:** PHP 8.2+
- **Database:** PostgreSQL 16
- **Key Libraries:**
  - `laravel/sanctum`: API Authentication
  - `spatie/laravel-permission`: Roles & Permissions
  - `owen-it/laravel-auditing`: Activity Audit Trails
  - `laravel/horizon`: Queue Management
  - `l5-swagger`: API Documentation

**Frontend:**
- **Framework:** React 18 (with TypeScript)
- **Build Tool:** Vite
- **UI:** Tailwind CSS with `lucide-react` for icons
- **State Management:**
  - `zustand`: Global state
  - `@tanstack/react-query`: Server state (caching, refetching)
- **Routing:** `react-router-dom`
- **Forms:** `react-hook-form` with `zod` for validation
- **Testing:** `vitest` for unit/integration tests and `playwright` for E2E tests.

## Development Environment Setup

This project uses a dual backend/frontend structure.

### 1. Install Dependencies

From the project root:

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies for the frontend
cd frontend
pnpm install
```

### 2. Configure Environment

From the project root:

```bash
# Create the .env file
cp .env.example .env

# Generate a new application key
php artisan key:generate
```
*Note: You will need to configure your `.env` file with the correct database credentials and other settings for your local environment.*

### 3. Database Setup

Once your environment is configured, run the migrations and seed the database with test data:

```bash
php artisan migrate:fresh --seed
```

## Running the Application

You need to run both the backend and frontend servers simultaneously.

### Start Backend Server

In a terminal, from the project root:

```bash
php artisan serve
```
This will start the Laravel development server, typically at `http://127.0.0.1:8000`.

### Start Frontend Server

In a separate terminal, from the `frontend` directory:

```bash
pnpm dev
```
This will start the Vite development server, typically at `http://localhost:5173`.

### Accessing the Application

- **Frontend URL:** [http://localhost:5173](http://localhost:5173)
- **Admin Login:** `admin@ogamierp.local`
- **Password:** `Admin@12345!`

## Key Commands

### Backend (`/` directory)

- **Run backend tests:** `./vendor/bin/pest`
- **Generate API docs:** `php artisan l5-swagger:generate`

### Frontend (`/frontend` directory)

- **Run unit tests:** `pnpm test`
- **Run E2E tests:** `pnpm e2e`
- **Check for type errors:** `pnpm typecheck`
- **Lint files:** `pnpm lint`

## Development Conventions

- **Branching:** Follow standard Gitflow (feature branches from `develop`).
- **Commits:** Use conventional commit messages (e.g., `feat:`, `fix:`, `docs:`).
- **Code Style:**
  - **PHP:** Adheres to PSR-12. Run `vendor/bin/pint` to format.
  - **TypeScript/React:** Adheres to standards enforced by ESLint and Prettier.
- **API:** All new API endpoints should be documented via OpenAPI docblocks.
- **State Management:** Use TanStack Query for data fetching and caching. Use Zustand for global UI state only when necessary.
- **Testing:** New features require corresponding unit or feature tests. Critical workflows should be covered by E2E tests.
