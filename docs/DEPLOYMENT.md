# MyStocks Production Deployment (cPanel / Shared Hosting Friendly)

MyStocks is intentionally structured so the Laravel application itself is **never** exposed as the web root.

---

## Recommended Structure on Server

```
/home/username/
└── mystocks/                    # or public_html/mystocks
    ├── backend/                 # Full Laravel app
    │   ├── app/
    │   ├── bootstrap/
    │   ├── config/
    │   ├── database/
    │   ├── public/              # ← Point domain Document Root here
    │   ├── routes/
    │   ├── storage/
    │   ├── vendor/
    │   └── .env
    ├── frontend/                # Source (optional after build)
    └── docs/
```

**Critical rule:** The domain’s Document Root must point **only** to `backend/public`.

---

## Step-by-step cPanel Deployment

### 1. Upload Files
- Upload the entire `mystocks` folder via File Manager or Git/SSH.
- Or upload a release zip and extract it.

### 2. Set Document Root
In cPanel → Domains / Subdomains:
- Set the document root of your domain (or subdomain) to:
  ```
  /home/username/mystocks/backend/public
  ```

### 3. PHP Version
- Go to **Select PHP Version** (or MultiPHP Manager)
- Choose **PHP 8.2** or **8.3**
- Enable required extensions: bcmath, curl, gd, intl, mbstring, pdo, pgsql (or mysqlnd), xml, zip, opcache

### 4. Create Database
- Create a PostgreSQL database (preferred) or MySQL database
- Create a user and assign full privileges
- Note the host, database name, username, password

### 5. Configure Environment
```bash
cd ~/mystocks/backend
cp .env.example .env
nano .env   # or use File Manager editor
```

Set at minimum:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=   # generate with artisan
DB_CONNECTION=pgsql   # or mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
FRONTEND_URL=https://yourdomain.com
```

Generate key:
```bash
php artisan key:generate
```

### 6. Install Dependencies (SSH recommended)
```bash
cd ~/mystocks/backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

If you do not have SSH, use cPanel Terminal if available, or contact support to run Composer.

### 7. Storage Link & Permissions
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### 8. Frontend Build
On your local machine (or CI):
```bash
cd frontend
npm ci
npm run build
```

Copy the contents of `frontend/dist/` into `backend/public/` (or a dedicated assets folder and adjust Vite base).

Alternatively serve the frontend from a subdomain and point API calls to the backend domain.

### 9. Cron Jobs (Scheduler)
In cPanel → Cron Jobs add:
```
* * * * * cd /home/username/mystocks/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 10. Queue Worker (optional but recommended)
If Redis or database queues are used, run a worker via Supervisor or a long-running cron. For simple shared hosting, database queue + the scheduler is acceptable for V1.

---

## Security Checklist for Production

- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY`
- [ ] Document root is **only** `public/`
- [ ] `.env` is not web-accessible
- [ ] HTTPS enabled (Force HTTPS redirect)
- [ ] Database user has minimal required privileges
- [ ] File permissions: storage and bootstrap/cache writable by web user
- [ ] Rate limiting enabled (Laravel default + custom)
- [ ] CORS restricted to your frontend domain

---

## Updating the Application

1. Put the site in maintenance mode: `php artisan down`
2. Pull/upload new code
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan migrate --force`
5. Rebuild frontend assets if needed
6. Clear & recache config/routes/views
7. `php artisan up`

---

## Troubleshooting Common Issues

| Problem | Solution |
|---------|----------|
| 500 error after deploy | Check `storage/logs/laravel.log`. Usually permissions or missing APP_KEY |
| Composer memory limit | `COMPOSER_MEMORY_LIMIT=-1 composer install ...` |
| PHP version wrong | Change in MultiPHP Manager and re-run composer |
| Database connection refused | Verify host (often `localhost` or a specific socket on shared hosts) |
| Assets 404 | Confirm frontend build was copied and Vite base path is correct |

---

**CNMG Technologies**  
MyStocks is built to run reliably on typical African shared hosting environments while remaining secure and maintainable.
