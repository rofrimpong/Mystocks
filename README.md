# MyStocks

**Production-ready Multi-tenant Inventory, Sales & Business Management PWA**

Built for **CNMG Technologies**  
Primary market: Ghana (GHS) — expandable across Africa

---

## Overview

MyStocks is a commercial Progressive Web App designed for traders, retailers, wholesalers and small businesses. It provides:

- Multi-tenant business management
- Product & inventory control with permanent movement ledger
- Sales & purchases with accurate historical cost tracking
- Offline-first operation with conflict-aware synchronization
- Profit, expense and reporting engines
- Role & permission based access control
- Platform administration for CNMG Technologies

This is **not** a demo or tutorial project. It is built to production standards.

---

## Technology Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Backend    | PHP 8.3+, Laravel 11/13, REST API   |
| Database   | PostgreSQL (MySQL fallback)         |
| Cache/Queue| Redis (database fallback)           |
| Frontend   | React + TypeScript + Vite           |
| Offline    | Service Worker + IndexedDB          |
| Auth       | Laravel Sanctum                     |
| Architecture | Modular Monolith                  |

---

## Project Structure

```
mystocks/
├── backend/                 # Laravel application
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/              # ← Document root on hosting
│   ├── routes/
│   └── ...
├── frontend/                # React + TypeScript PWA
├── docs/                    # Installation, API, deployment docs
├── scripts/                 # Deployment helpers
├── DEPENDENCY_LEDGER.md
└── README.md
```

---

## Quick Start (Local Development)

### Requirements
- PHP 8.2 or 8.3 with extensions: bcmath, ctype, curl, gd, intl, mbstring, openssl, pdo, pgsql (or mysql), tokenizer, xml, zip
- Composer 2.x
- Node.js 20+
- PostgreSQL 14+ (or MySQL 8)

### Backend

```bash
cd backend
cp .env.example .env
# Edit .env with your database credentials
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

---

## Hosting on cPanel / Shared Hosting

MyStocks is designed for straightforward cPanel deployment:

1. Upload the entire `mystocks` folder (or just `backend` + built frontend).
2. Point the domain/subdomain **document root** to `backend/public`.
3. Ensure PHP version is 8.2+.
4. Create a PostgreSQL or MySQL database.
5. Copy `.env.example` → `.env` and configure.
6. Run `composer install --no-dev --optimize-autoloader` via SSH or cPanel Terminal.
7. Run migrations.
8. Build the frontend (`npm run build`) and place assets correctly.

Detailed instructions: see `docs/DEPLOYMENT.md`.

---

## Development Phases

See `DEPENDENCY_LEDGER.md` for the full implementation order and current status.

We build incrementally. Each phase is completed, tested and integrated before the next begins.

---

## Security Highlights

- Strict tenant isolation at database and application layer
- Permission-based authorization (extensible roles)
- Database transactions for all inventory & financial operations
- Historical cost price stored on every sale item
- Idempotency keys prevent duplicate offline transactions
- Full audit logging of sensitive actions
- No secrets in frontend or version control

---

## License

Proprietary – CNMG Technologies. All rights reserved.

---

**CNMG Technologies**  
MyStocks – Reliable inventory & business management for African traders.
