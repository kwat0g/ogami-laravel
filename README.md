# Ogami ERP - Manufacturing Resource Planning System

A comprehensive ERP system designed for manufacturing businesses in the Philippines.

## 📚 Documentation

### Essential Reading

| Document | Description |
|----------|-------------|
| [**AGENTS.md**](AGENTS.md) | Technical documentation for AI coding agents |
| [**docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md**](docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md) | **Complete real-life ERP testing guide** |

### System Documentation
- [**sod.md**](sod.md) - Segregation of Duties (SoD) implementation
- [**system_specs.md**](system_specs.md) - System specifications

---

## 🚀 Quick Start for Testing

### Step 1: Setup Environment

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations and seeders
php artisan migrate:fresh --seed
```

### Step 2: Start Servers

```bash
# Terminal 1 - Backend
cd /home/kwat0g/Desktop/ogamiPHP
php artisan serve

# Terminal 2 - Frontend
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm dev
```

### Step 3: Access Application

- **Frontend:** http://localhost:5173
- **Admin Login:** `admin@ogamierp.local` / `Admin@12345!`

---

## 📖 Testing Guide

For complete testing instructions, see:

### [**REAL_LIFE_ERP_TESTING_GUIDE.md**](docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md)

This guide covers:
- **Phase 1:** Foundation Setup (Banking, Vendors, Customers, Items)
- **Phase 2:** Procurement Workflow
- **Phase 3:** Inventory Management
- **Phase 4:** Production Workflow
- **Phase 5:** Quality Control
- **Phase 6:** Sales & Delivery
- **Phase 7:** Accounting & Finance
- **Phase 8:** HR & Payroll
- **Phase 9:** Maintenance

---

## 👥 Test Accounts

All test accounts are created by the `ConsolidatedEmployeeSeeder`.

### Executive
| Email | Password | Role |
|-------|----------|------|
| chairman@ogamierp.local | Executive@12345! | Executive |
| president@ogamierp.local | Executive@12345! | Executive |
| vp@ogamierp.local | VicePresident@1! | Vice President |

### Department Managers
| Email | Password | Department |
|-------|----------|------------|
| prod.manager@ogamierp.local | Manager@12345! | Production |
| qc.manager@ogamierp.local | Manager@12345! | QC |
| hr.manager@ogamierp.local | HrManager@12345! | HR |
| acctg.manager@ogamierp.local | Manager@12345! | Accounting |
| sales.manager@ogamierp.local | Manager@12345! | Sales |

### Department Heads
| Email | Password | Department |
|-------|----------|------------|
| warehouse.head@ogamierp.local | Head@123456789! | Warehouse |
| production.head@ogamierp.local | Head@123456789! | Production |
| maintenance.head@ogamierp.local | Head@123456789! | Maintenance |

### Officers
| Email | Password | Department |
|-------|----------|------------|
| purchasing.officer@ogamierp.local | Officer@12345! | Procurement |
| accounting@ogamierp.local | Officer@12345! | Accounting |

### System
| Email | Password |
|-------|----------|
| admin@ogamierp.local | Admin@12345! |

---

## 🏗️ Architecture

### Backend
- **Framework:** Laravel 11
- **Database:** PostgreSQL 16
- **Queue:** Redis
- **Authentication:** Laravel Sanctum

### Frontend
- **Framework:** React 18 + TypeScript
- **Build:** Vite 6
- **State:** TanStack Query + Zustand
- **UI:** Tailwind CSS

### Domains (20 Total)
1. HR & Payroll
2. Accounting (GL, AP, AR)
3. Inventory
4. Procurement
5. Production
6. Quality Control
7. Sales & Delivery
8. Maintenance
9. And more...

---

## 🧪 Testing

```bash
# Backend tests
./vendor/bin/pest

# Frontend tests
cd frontend && pnpm test

# E2E tests
cd frontend && pnpm exec playwright test
```

---

## 🔐 Security

- RBAC (Role-Based Access Control)
- SoD (Segregation of Duties)
- Session-based authentication
- Rate limiting
- Audit trails

---

## 📞 Support

For issues and questions, refer to:
- [AGENTS.md](AGENTS.md) - Technical details
- [docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md](docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md) - Testing guide

---

RULES:
1. Always use MCP Server (Context7 and Socraciticode)

**Ogami ERP** - Built for Philippine Manufacturing
