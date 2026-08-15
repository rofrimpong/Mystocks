# MyStocks Dependency & Implementation Ledger

**Project:** MyStocks by CNMG Technologies  
**Architecture:** Modular Monolith  
**Last Updated:** 2026-08-15  

---

## Phase Status Overview

| Phase | Module                          | Status      | Notes                                      |
|-------|---------------------------------|-------------|--------------------------------------------|
| 1     | Project Foundation              | COMPLETED   | Directory structure, configs, Git, docs    |
| 2     | Database Schema                 | COMPLETED   | All core migrations + key models created   |
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
COMPLETED – Foundation committed. Ready for Phase 2 (Database Schema).

---

## Rules Enforced
- No placeholder code
- No incomplete files
- Tenant isolation from day one of data models
- All financial operations transactional
- Idempotency keys for offline
- Permission-based authorization (not role-name hardcoding)

## Phase 2 – Database Schema

### Files Created
**Migrations:**
- 2024_01_01_000001_create_users_table.php
- 2024_01_01_000002_create_businesses_and_branches_tables.php
- 2024_01_01_000003_create_permission_tables.php
- 2024_01_01_000004_create_categories_and_products_tables.php
- 2024_01_01_000005_create_inventory_tables.php
- 2024_01_01_000006_create_sales_tables.php
- 2024_01_01_000007_create_purchases_tables.php
- 2024_01_01_000008_create_customers_suppliers_expenses_tables.php
- 2024_01_01_000009_create_notifications_sync_audit_tables.php
- 2024_01_01_000010_create_subscriptions_tables.php
- 2024_01_01_000011_create_cache_jobs_tables.php

**Models:**
- User.php
- Business.php
- Branch.php
- Category.php
- Product.php
- InventoryBalance.php
- InventoryMovement.php

**Config:**
- config/permission.php

### Database Tables
users, password_reset_tokens, sessions,
businesses, branches, business_user,
permissions, roles, model_has_permissions, model_has_roles, role_has_permissions, personal_access_tokens,
categories, products,
inventory_balances, inventory_movements,
sales, sale_items, sale_payments,
purchases, purchase_items, purchase_payments,
customers, customer_transactions,
suppliers, supplier_transactions,
expense_categories, expenses,
notifications, device_tokens,
idempotency_keys, sync_operations, audit_logs,
plans, subscriptions, platform_payments,
cache, cache_locks, jobs, job_batches, failed_jobs

### Key Design Decisions
- UUID primary keys on all domain entities
- decimal(15,4) for quantities and money (no floats)
- Historical cost stored on sale_items
- Permanent inventory_movements ledger
- Fast inventory_balances for current stock
- Idempotency keys + sync_operations for offline
- Full audit_logs table (append-only)
- Soft deletes on major business entities
- Tenant isolation via business_id on every business-owned table

### Status
COMPLETED – Schema ready. Models for core entities created. Ready for Phase 3 (Authentication).
