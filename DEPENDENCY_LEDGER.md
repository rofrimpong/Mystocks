# MyStocks Dependency & Implementation Ledger

**Project:** MyStocks by CNMG Technologies  
**Architecture:** Modular Monolith  
**Last Updated:** 2026-08-15  

---

## Phase Status Overview

| Phase | Module                          | Status      | Notes                                      |
|-------|---------------------------------|-------------|--------------------------------------------|
| 1     | Project Foundation              | IN PROGRESS | Directory structure, configs, Git, docs    |
| 2     | Database Schema                 | PENDING     | Migrations for all core tables             |
| 3     | Authentication                  | PENDING     | Registration, login, password reset, OTP   |
| 4     | Tenant / Business / Branches    | PENDING     | Multi-tenancy core                         |
| 5     | Products & Categories           | PENDING     |                                            |
| 6     | Inventory Engine                | PENDING     | Balances + Movements ledger                |
| 7     | Purchases                       | PENDING     |                                            |
| 8     | Sales                           | PENDING     |                                            |
| 9     | Customers & Suppliers           | PENDING     |                                            |
| 10    | Expenses & Profit Engine        | PENDING     |                                            |
| 11    | Reports                         | PENDING     |                                            |
| 12    | Notifications / Low Stock       | PENDING     |                                            |
| 13    | Offline Synchronization         | PENDING     | Idempotency, conflict handling             |
| 14    | React PWA Frontend              | PENDING     |                                            |
| 15    | Platform Admin                  | PENDING     |                                            |
| 16    | Subscriptions                   | PENDING     |                                            |
| 17    | Testing & Security Hardening    | PENDING     |                                            |
| 18    | Production Deployment Package   | PENDING     | cPanel-ready                               |

---

## Phase 1 – Project Foundation

### Files
- `README.md`
- `DEPENDENCY_LEDGER.md` (this file)
- `.gitignore`
- `backend/composer.json`
- `backend/.env.example`
- `backend/artisan`
- `backend/bootstrap/app.php`
- `backend/public/index.php`
- `backend/public/.htaccess`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/config/app.php` (key settings)
- `backend/config/database.php`
- `backend/config/auth.php`
- `backend/config/cors.php`
- `frontend/package.json`
- `frontend/vite.config.ts`
- `frontend/tsconfig.json`
- `frontend/index.html`
- `frontend/src/main.tsx`
- `docs/INSTALLATION.md`
- `docs/DEPLOYMENT.md`
- `scripts/deploy.sh`

### Dependencies
- PHP 8.2+ / 8.3+
- Composer 2.x
- Node.js 20+ / npm
- PostgreSQL 14+ (or MySQL 8 as fallback)
- Redis (optional)

### Database tables
None yet (Phase 2)

### Routes
None yet (API routes will be added from Phase 3)

### Tests
None yet

### Status
IN PROGRESS – Creating foundation files.

---

## Rules Enforced
- No placeholder code
- No incomplete files
- Tenant isolation from day one of data models
- All financial operations transactional
- Idempotency keys for offline
- Permission-based authorization (not role-name hardcoding)
