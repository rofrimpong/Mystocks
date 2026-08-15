# MyStocks — Deploy Checklist (print / tick)

## Before upload
- [ ] Code is on `main` (or release branch)
- [ ] No `.env` secrets in Git
- [ ] Frontend builds locally (`npm run build`)

## Server
- [ ] PHP 8.2 or 8.3 selected for domain
- [ ] Required PHP extensions enabled
- [ ] MySQL database + user created
- [ ] Document Root = `.../backend/public` only

## Backend
- [ ] `.env` configured (production, APP_DEBUG=false, DB_*, APP_URL)
- [ ] `php artisan key:generate`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `chmod -R 775 storage bootstrap/cache`
- [ ] `php artisan config:cache && route:cache && view:cache`

## Frontend
- [ ] `VITE_API_URL=https://YOUR_DOMAIN/api/v1`
- [ ] `npm run build`
- [ ] Assets copied into `backend/public`

## After go-live
- [ ] `/api/v1/health` OK
- [ ] Register + login OK
- [ ] Sale + inventory OK
- [ ] HTTPS forced
- [ ] Cron for scheduler installed

## Rollback plan
- [ ] Keep previous `vendor` + `.env` backup
- [ ] `php artisan down` before risky updates
