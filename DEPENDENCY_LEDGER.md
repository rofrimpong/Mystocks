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
| 3     | Authentication                  | COMPLETED   | Register, login, logout, password reset    |
| 4     | Tenant / Business / Branches    | COMPLETED   | Business + Branch CRUD, tenant middleware  |
| 5     | Products & Categories           | COMPLETED   | Full CRUD, tenant-scoped, validation       |
| 6     | Inventory Engine                | COMPLETED   | Service + balances + movements + adjust    |
| 7     | Purchases                       | COMPLETED   | Create purchase + inventory increase       |
| 8     | Sales                           | COMPLETED   | Full transactional sales engine            |
| 9     | Customers & Suppliers           | COMPLETED   | Full CRUD for customers and suppliers      |
| 10    | Expenses & Profit Engine        | COMPLETED   | Expenses + historical profit calculations  |
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

## Phase 3 – Authentication

### Files Created
- app/Http/Controllers/Api/V1/AuthController.php
- app/Http/Controllers/Controller.php
- app/Http/Requests/Auth/RegisterRequest.php
- app/Http/Requests/Auth/LoginRequest.php
- app/Http/Requests/Auth/ForgotPasswordRequest.php
- app/Http/Requests/Auth/ResetPasswordRequest.php
- app/Http/Resources/UserResource.php
- app/Providers/AppServiceProvider.php
- bootstrap/providers.php
- config/auth.php
- config/sanctum.php
- routes/api.php (updated)

### Endpoints
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/logout          (auth:sanctum)
POST   /api/v1/auth/logout-all      (auth:sanctum)
GET    /api/v1/auth/me              (auth:sanctum)

### Features
- Registration creates User + Business + Head Office Branch in a single transaction
- 14-day trial on new businesses
- Sanctum token authentication
- Rate limiting on auth endpoints
- Password reset via Laravel Password broker
- Strong password defaults (mixed case, numbers, symbols, uncompromised)
- Device name support for token management
- Returns businesses list on login/me

### Status
COMPLETED – Ready for Phase 4 (Tenant / Business management enhancements).

## Phase 4 – Tenant / Business / Branches

### Files Created
- app/Http/Middleware/EnsureBusinessAccess.php
- app/Http/Controllers/Api/V1/BusinessController.php
- app/Http/Controllers/Api/V1/BranchController.php
- app/Http/Requests/Business/UpdateBusinessRequest.php
- app/Http/Requests/Branch/StoreBranchRequest.php
- app/Http/Requests/Branch/UpdateBranchRequest.php
- app/Http/Resources/BusinessResource.php
- app/Http/Resources/BranchResource.php
- bootstrap/app.php (middleware alias)
- routes/api.php (updated)

### Endpoints
GET    /api/v1/businesses
GET    /api/v1/businesses/{id}
PUT    /api/v1/businesses/{id}
GET    /api/v1/business/current          (requires X-Business-Id)
GET    /api/v1/branches                  (requires business context)
POST   /api/v1/branches
GET    /api/v1/branches/{id}
PUT    /api/v1/branches/{id}
DELETE /api/v1/branches/{id}

### Tenant Isolation
- Middleware `business` resolves current business from `X-Business-Id` header
- Falls back to user's first active business if header omitted
- Platform admins can access any business
- Owner checks for destructive / configuration changes
- Branch operations scoped to current business

### Status
COMPLETED – Ready for Phase 5 (Products & Categories).

## Phase 5 – Products & Categories

### Files Created
- app/Http/Controllers/Api/V1/CategoryController.php
- app/Http/Controllers/Api/V1/ProductController.php
- app/Http/Requests/Category/StoreCategoryRequest.php
- app/Http/Requests/Category/UpdateCategoryRequest.php
- app/Http/Requests/Product/StoreProductRequest.php
- app/Http/Requests/Product/UpdateProductRequest.php
- app/Http/Resources/CategoryResource.php
- app/Http/Resources/ProductResource.php
- routes/api.php (updated)

### Endpoints (all require auth:sanctum + business middleware)
Categories:
GET/POST   /api/v1/categories
GET/PUT/DELETE /api/v1/categories/{id}

Products:
GET/POST   /api/v1/products
GET/PUT/DELETE /api/v1/products/{id}

### Features
- Full tenant isolation (business_id scoping)
- Category parent/child support
- Auto slug generation + uniqueness per business
- SKU uniqueness per business
- Supported units: piece, box, carton, kilogram, gram, litre, metre, service, pack, dozen
- Search by name/SKU/barcode/brand
- Pagination
- Soft deletes on products
- Cannot delete category that has products or children
- Prices stored as decimal (no floats)

### Status
COMPLETED – Ready for Phase 6 (Inventory Engine).

## Phase 6 – Inventory Engine

### Files Created
- app/Services/InventoryService.php          (core engine – transactional + locking)
- app/Http/Controllers/Api/V1/InventoryController.php
- app/Http/Requests/Inventory/OpeningStockRequest.php
- app/Http/Requests/Inventory/AdjustStockRequest.php
- app/Http/Resources/InventoryBalanceResource.php
- app/Http/Resources/InventoryMovementResource.php
- routes/api.php (updated)

### Endpoints
GET  /api/v1/inventory/balances
GET  /api/v1/inventory/balances/{productId}
GET  /api/v1/inventory/movements
POST /api/v1/inventory/opening-stock
POST /api/v1/inventory/adjust

### Core Guarantees
- Every stock change creates an immutable inventory_movement record
- inventory_balances updated inside the same DB transaction
- Row-level locking (lockForUpdate) prevents race conditions
- Negative stock blocked unless business.allow_negative_stock = true
- Average cost recalculated on inbound movements
- Reason required for adjustments (audit)
- All operations tenant + branch scoped

### Status
COMPLETED – Ready for Phase 7 (Purchases).

## Phase 7 – Purchases

### Files Created
- app/Models/Purchase.php
- app/Models/PurchaseItem.php
- app/Models/PurchasePayment.php
- app/Models/Supplier.php
- app/Services/PurchaseService.php
- app/Http/Controllers/Api/V1/PurchaseController.php
- app/Http/Requests/Purchase/StorePurchaseRequest.php
- app/Http/Resources/PurchaseResource.php
- routes/api.php (updated)

### Endpoints
GET  /api/v1/purchases
POST /api/v1/purchases
GET  /api/v1/purchases/{id}

### Behaviour
- Full DB transaction
- Creates purchase + items
- Increases inventory via InventoryService (type=purchase)
- Records historical unit_cost on each line
- Updates product.buying_price to latest cost
- Optional payment at creation
- Idempotency key support
- Auto-generated purchase numbers
- Tenant + branch scoped

### Status
COMPLETED – Ready for Phase 8 (Sales).

## Phase 8 – Sales Engine

### Files Created
- app/Models/Sale.php
- app/Models/SaleItem.php
- app/Models/SalePayment.php
- app/Models/Customer.php
- app/Services/SaleService.php
- app/Http/Controllers/Api/V1/SaleController.php
- app/Http/Requests/Sale/StoreSaleRequest.php
- app/Http/Resources/SaleResource.php
- routes/api.php (updated)

### Endpoints
GET  /api/v1/sales
POST /api/v1/sales
GET  /api/v1/sales/{id}

### Critical Guarantees (as specified in architecture)
1. Validate request
2. Validate product availability (with row locking)
3. Create sale
4. Create sale items with historical selling price + historical cost price
5. Reduce inventory via InventoryService
6. Calculate COGS and gross profit
7. Record payment
8. Full transaction – any failure rolls back everything
9. Idempotency key prevents duplicate sales
10. Never uses today's cost for historical profit

### Status
COMPLETED – Ready for Phase 9 (Customers & Suppliers).

## Phase 9 – Customers & Suppliers

### Files Created
- app/Http/Controllers/Api/V1/CustomerController.php
- app/Http/Controllers/Api/V1/SupplierController.php
- app/Http/Requests/Customer/StoreCustomerRequest.php
- app/Http/Requests/Customer/UpdateCustomerRequest.php
- app/Http/Requests/Supplier/StoreSupplierRequest.php
- app/Http/Requests/Supplier/UpdateSupplierRequest.php
- app/Http/Resources/CustomerResource.php
- app/Http/Resources/SupplierResource.php
- routes/api.php (updated)

### Endpoints
Customers:
GET/POST   /api/v1/customers
GET/PUT/DELETE /api/v1/customers/{id}

Suppliers:
GET/POST   /api/v1/suppliers
GET/PUT/DELETE /api/v1/suppliers/{id}

### Features
- Full tenant isolation
- Search by name/phone/email
- Filter active / with outstanding balance
- Cannot delete if outstanding balance > 0
- Credit limit support on customers
- Soft deletes

### Status
COMPLETED – Ready for Phase 10 (Expenses & Profit).

## Phase 10 – Expenses & Profit Engine

### Files Created
- app/Models/Expense.php
- app/Models/ExpenseCategory.php
- app/Services/ProfitService.php
- app/Http/Controllers/Api/V1/ExpenseController.php
- app/Http/Controllers/Api/V1/ExpenseCategoryController.php
- app/Http/Controllers/Api/V1/ReportController.php
- Requests + Resources for expenses
- routes/api.php (updated)

### Endpoints
Expense Categories:
GET/POST /api/v1/expense-categories

Expenses:
GET/POST /api/v1/expenses
GET/PUT/DELETE /api/v1/expenses/{id}

Reports:
GET /api/v1/reports/profit
GET /api/v1/reports/dashboard

### Profit Calculation Rules
- Revenue = SUM of completed sales.total
- COGS = SUM of sale.cost_of_goods (historical)
- Gross Profit = Revenue - COGS (from stored sale.gross_profit)
- Expenses = SUM of expenses.amount in period
- Net Profit = Gross Profit - Expenses
- Never uses current product cost for historical profit

### Status
COMPLETED – Ready for Phase 11 (Reports expansion) or Phase 12/13 (Notifications / Offline).

## Phase 11 – Expanded Reports

### Files Updated
- app/Http/Controllers/Api/V1/ReportController.php (expanded)
- routes/api.php

### New Endpoints
GET /api/v1/reports/best-sellers
GET /api/v1/reports/low-stock
GET /api/v1/reports/inventory-valuation
GET /api/v1/reports/sales-by-day

### Existing
GET /api/v1/reports/profit
GET /api/v1/reports/dashboard  (now includes stock value + low-stock count)

### Status
COMPLETED – Ready for Phase 12 (Notifications) or Phase 13 (Offline Sync) or Phase 14 (PWA).

## Phase 13 – Offline Synchronization

### Files Created
- app/Models/IdempotencyKey.php
- app/Models/SyncOperation.php
- app/Services/SyncService.php
- app/Http/Controllers/Api/V1/SyncController.php
- app/Http/Requests/Sync/SyncBatchRequest.php
- routes/api.php (updated)

### Endpoints
POST /api/v1/sync/push     – Push batch of offline operations
GET  /api/v1/sync/status   – Query previous sync results

### Supported offline operations
- sale
- inventory_adjustment
- opening_stock

### Guarantees
- Idempotency keys prevent duplicate sales / movements
- Each operation processed independently
- Explicit conflict status returned (never silent overwrite)
- Full audit via sync_operations + idempotency_keys tables
- Safe for retries

### Status
COMPLETED – Ready for Phase 14 (React PWA Frontend).

## Phase 14 – React PWA Frontend (Foundation)

### Files Created
- src/types/index.ts
- src/services/api.ts
- src/services/auth.ts
- src/stores/authStore.ts
- src/stores/offlineStore.ts
- src/layouts/AppLayout.tsx
- src/pages/LoginPage.tsx
- src/pages/RegisterPage.tsx
- src/pages/DashboardPage.tsx
- src/pages/ProductsPage.tsx
- src/pages/PlaceholderPage.tsx
- src/App.tsx (routing)

### Features
- Login / Register flows
- Protected routes
- Mobile-first layout with bottom nav + desktop sidebar
- Online/Offline indicator
- Offline queue + sync button
- Dashboard with live stats
- Products list with search
- Token + X-Business-Id headers on every API call
- PWA config already present in vite.config.ts

### Status
Foundation COMPLETED. Sales POS screen and remaining modules can be filled iteratively.

## Phase 14b – Sales POS Screen

### Files
- frontend/src/services/sales.ts
- frontend/src/pages/SalesPage.tsx
- frontend/src/App.tsx (updated)

### Features
- Product grid with search
- Cart with qty +/- 
- Payment methods: Cash, MoMo, Card, Credit
- Online sale via API
- Automatic offline queue when network fails or offline
- Mobile cart drawer + desktop side panel
- Large touch targets

### Status
Sales POS COMPLETED.

## Phase 14c – Inventory Screen

### Files
- frontend/src/services/inventory.ts
- frontend/src/pages/InventoryPage.tsx
- frontend/src/App.tsx (updated)

### Features
- List inventory balances with search
- Low-stock filter + badge
- Adjust stock (in/out) with required reason
- Record opening stock
- Online API + offline queue fallback

### Status
Inventory UI COMPLETED.
