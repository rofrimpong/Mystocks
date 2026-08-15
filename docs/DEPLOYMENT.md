# MyStocks — Production Deployment Guide (cPanel / Domain)

This guide is for hosting MyStocks on typical cPanel shared hosting or a VPS, with clear steps for PHP version, document root, and going live on your domain.

---

## 1. Requirements

| Item | Notes |
|------|--------|
| Domain / subdomain | e.g. mystocks.yourdomain.com |
| cPanel | File Manager + Terminal/SSH if possible |
| PHP 8.2 or 8.3 | MultiPHP Manager |
| MySQL 8 / MariaDB | Default on cPanel (PostgreSQL optional) |
| Composer 2.x | On server or run locally and upload vendor |
| Node 20+ | Only to build frontend (can be on your PC) |

**Rule:** Document Root must be **only**:

```text
/home/USERNAME/mystocks/backend/public
```

---

## 2. Folder layout

```text
/home/USERNAME/mystocks/
├── backend/                 # Laravel
│   ├── public/              # ← Document Root
│   ├── storage/
│   ├── vendor/
│   └── .env
├── frontend/                # source; build output goes into backend/public
└── docs/
```

---

## 3. Deploy steps

### 3.1 Upload code

Git:

```bash
cd ~
git clone YOUR_REPO mystocks
cd mystocks
```

Or upload ZIP via File Manager and extract (exclude node_modules).

### 3.2 Document Root

cPanel → Domains → set Document Root to:

```text
/home/USERNAME/mystocks/backend/public
```

### 3.3 PHP version

MultiPHP Manager → **8.2 or 8.3** for this domain.  
Enable: bcmath, curl, gd, intl, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, zip, opcache.

### 3.4 Database

Create MySQL database + user + ALL PRIVILEGES. Host is usually `localhost`.

### 3.5 Environment

```bash
cd ~/mystocks/backend
cp .env.example .env
```

Edit `.env`:

```env
APP_NAME="MyStocks"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Africa/Accra

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_mystocks
DB_USERNAME=username_dbuser
DB_PASSWORD=strong_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FRONTEND_URL=https://yourdomain.com
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com

MYSTOCKS_DEFAULT_CURRENCY=GHS
MYSTOCKS_DEFAULT_COUNTRY=GH
MYSTOCKS_DEFAULT_TIMEZONE=Africa/Accra
```

```bash
php artisan key:generate
```

### 3.6 Composer

```bash
cd ~/mystocks/backend
composer install --no-dev --optimize-autoloader
# if needed:
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader
```

### 3.7 Migrate & storage

```bash
php artisan migrate --force
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### 3.8 Frontend build (on your computer)

```bash
cd frontend
# create .env.production
echo 'VITE_API_URL=https://yourdomain.com/api/v1' > .env.production
npm ci
npm run build
```

Copy `frontend/dist/*` into `backend/public/` (keep Laravel `index.php` and `.htaccess`).

### 3.9 Production caches

```bash
cd ~/mystocks/backend
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.10 Cron

```text
* * * * * cd /home/USERNAME/mystocks/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 3.11 SSL

Enable AutoSSL / Force HTTPS in cPanel.

---

## 4. Go-live checks

- [ ] https://yourdomain.com/api/v1/health → `{"status":"ok",...}`
- [ ] Register works
- [ ] Login works
- [ ] Dashboard loads
- [ ] Create product + opening stock + sale
- [ ] APP_DEBUG=false
- [ ] Document root is backend/public only

---

## 5. Common fixes

| Issue | Fix |
|-------|-----|
| 500 error | storage/logs/laravel.log; APP_KEY; DB; permissions |
| No encryption key | php artisan key:generate |
| Composer OOM | COMPOSER_MEMORY_LIMIT=-1 |
| Wrong PHP | MultiPHP → 8.2/8.3 |
| API 404 | Document root + .htaccess |
| CORS | SANCTUM_STATEFUL_DOMAINS + FRONTEND_URL |
| Blank UI | Rebuild with VITE_API_URL; upload assets |

---

## 6. Updates

```bash
cd ~/mystocks && git pull
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
# rebuild frontend and upload dist
```

---

## 7. Security

- APP_DEBUG=false
- Strong DB password
- Document root = public only
- HTTPS on
- Never commit .env

---

**MyStocks · CNMG Technologies · Ghana (GHS)**
