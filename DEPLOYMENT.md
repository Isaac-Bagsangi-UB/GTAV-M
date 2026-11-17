# Deploying GTAV-M to a live server

For a real server (Linux with Apache or Nginx, PHP 8.4+, MySQL 8). For local demos, `README.md` is enough.

## 1. Server and code

1. Point the web server's document root at the project's **`public`** folder, never the project root.
   Apache uses the included `public/.htaccess` (rewrites, compression, caching, dotfile blocking).
2. Install an HTTPS certificate (for example Let's Encrypt). The camera scanner requires HTTPS.
3. Upload the project, then on the server:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate          # only on the first deployment
php artisan migrate --force        # never migrate:fresh or --seed in production: they wipe data / add demo users
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. The web server user must be able to write to `storage/` and `bootstrap/cache/`
   (`chown -R www-data:www-data storage bootstrap/cache`).

## 2. `.env` for production

```env
APP_NAME=GTAV-M
APP_ENV=production
APP_DEBUG=false                  # never true in production: debug pages reveal code and settings
APP_URL=https://your-domain.example

SESSION_SECURE_COOKIE=true       # session cookie only over HTTPS
SESSION_ENCRYPT=true
SESSION_LIFETIME=120

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=gtavm
DB_USERNAME=gtavm_app             # a dedicated account with rights on this database only, not root
DB_PASSWORD=a-long-random-password

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=events@your-domain.example
MAIL_FROM_NAME="GTAV-M"
```

With `APP_URL` starting with `https://`, every link the site generates uses HTTPS and the
`Strict-Transport-Security` header is sent.

Keep `.env` out of version control and readable only by the web server user (`chmod 640 .env`).

## 3. First administrator

Production has no demo accounts. Create the first administrator once:

```bash
php artisan tinker
>>> App\Models\User::create(['name' => 'Your Name', 'email' => 'you@your-domain.example', 'password' => 'a-strong-password-123', 'role' => 'admin']);
```

Then sign in and create chapters, managers and gate staff under **Users**.

## 4. Search engines

Add this line to `public/robots.txt` with the real address:

```
Sitemap: https://your-domain.example/sitemap.xml
```

## 5. Backups

Back up the database every day and keep at least 30 days. Example cron entry:

```bash
0 2 * * * mysqldump --single-transaction gtavm | gzip > /backups/gtavm-$(date +\%F).sql.gz
```

Also back up `storage/app/private` (uploaded workshop materials). Test a restore at least every three months.

## 6. After each update

```bash
php artisan down
git pull                       # or upload the new files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

When CSS or JS changes, raise the `?v=` number on their links in `resources/views/layouts/app.blade.php`
so browsers fetch the new files instead of the cached ones.

## 7. Monitoring

- Application errors: `storage/logs/laravel.log` (or point `LOG_CHANNEL` to a central log service).
- Security events (sign-ins, failed logins, role changes, reports): **Audit log** in the admin area.
- Uptime: a free external monitor such as UptimeRobot checking `https://your-domain.example/up` every 5 minutes.
