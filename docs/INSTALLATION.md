# MyStocks Installation Guide

## Requirements

### Server
- PHP 8.2 or 8.3 (8.4 also supported)
- Composer 2.x
- PostgreSQL 14+ **or** MySQL 8.0+
- Node.js 20+ (for building the frontend)
- Redis (optional – database drivers work as fallback)
- HTTPS recommended for production

### PHP Extensions
- bcmath, ctype, curl, dom, fileinfo, gd, intl, json, mbstring, openssl, pdo, pgsql (or pdo_mysql), tokenizer, xml, zip

---

## Local Development Setup

### 1. Clone / Download

```bash
cd /path/to/projects
# place the mystocks folder here
cd mystocks
```

### 2. Backend

```bash
cd backend
cp .env.example .env
# Edit .env – set DB credentials, APP_URL, etc.
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

API will be available at `http://localhost:8000/api/v1`

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

Frontend runs at `http://localhost:5173` and proxies API requests.

---

## Database Notes

PostgreSQL is preferred for production (better concurrency and JSON support).

Example `.env` for PostgreSQL:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mystocks
DB_USERNAME=mystocks_user
DB_PASSWORD=strong_password
```

For MySQL simply change `DB_CONNECTION=mysql` and `DB_PORT=3306`.

---

## Next Steps After Installation

1. Create the first platform administrator (Phase 3+).
2. Register a business and start using the system.
3. Review `docs/DEPLOYMENT.md` for production hosting (especially cPanel).

---

**CNMG Technologies – MyStocks**
