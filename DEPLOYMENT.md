# Deployment — Swaida Tickets Hub

Target: **Hostinger VPS, nginx + PHP-FPM + PostgreSQL**.

Everything below is required, not advisory. Where a step exists because of a
specific failure mode, that failure is named.

---

## 1. What has to be running

Three processes, not one. The app is not correct without all three.

| Process | Why | If it is missing |
|---|---|---|
| `php-fpm` behind nginx | Serves the app | Nothing works |
| `php artisan queue:work` | Broadcasts and push notifications are **queued** | Tickets never flip to Paid on the holder's screen. Silent — no error anywhere |
| `php artisan reverb:start` | WebSocket server | Same as above, plus the owner's live feed stops |

A fourth entry belongs in cron:

```cron
* * * * * cd /var/www/tickets && php artisan schedule:run >> /dev/null 2>&1
```

Without it `tickets:expire` never runs, unpaid holds keep their seats forever,
and events sell out to bookings nobody paid for.

---

## 2. Environment

`.env.example` documents **every** variable the code reads. Copy it, do not
start from a stock Laravel file.

| Variable | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Stack traces leak file paths and query contents |
| `APP_URL` | `https://your-domain` | QR codes encode absolute verification URLs. **Wrong value = every printed QR points at the wrong host** |
| `APP_LOCALE` | `ar` | Arabic is the default audience |
| `SESSION_SECURE_COOKIE` | `true` | Session cookie never travels over plain HTTP |
| `SESSION_DOMAIN` | your domain | |
| `DB_CONNECTION` | `pgsql` | |
| `BROADCAST_CONNECTION` | `reverb` | |
| `QUEUE_CONNECTION` | `database` (or `redis`) | Must not be `sync`: a slow FCM call would block the door staff's request |
| `CACHE_STORE` | `database` or `redis` | Platform settings and rate limits live here |

Reverb needs both halves. The `VITE_` copies are compiled into the JS bundle,
so **they must be literal values** — Vite does not expand `${REVERB_APP_KEY}`:

```dotenv
REVERB_APP_ID=      REVERB_APP_KEY=      REVERB_APP_SECRET=
REVERB_HOST="your-domain"   REVERB_PORT=443   REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1   REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="<same as REVERB_APP_KEY>"
VITE_REVERB_HOST="your-domain"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
```

FCM is optional and inert while blank — the sender reports itself
unconfigured, no permission prompt is shown, and nothing breaks.

Run `php artisan config:cache` after editing `.env` or nothing changes.

---

## 3. PostgreSQL

Required. The seat guarantee is `SELECT … FOR UPDATE` on the event row inside a
transaction; the overbooking tests fork real processes and fail without it.
SQLite has no equivalent and would silently oversell.

```sql
CREATE DATABASE swaida_tickets;
CREATE USER tickets_app WITH ENCRYPTED PASSWORD '…';
GRANT ALL PRIVILEGES ON DATABASE swaida_tickets TO tickets_app;
```

---

## 4. Upload limits — three places, all of them

Promo videos are capped at **100 MB** in the app. Three other limits sit in
front of that and each one fails differently and confusingly if left at its
default:

```ini
; php.ini — headroom above 100 MB for multipart overhead
upload_max_filesize = 110M
post_max_size = 120M
memory_limit = 512M
max_execution_time = 120
```

```nginx
client_max_body_size 120m;
```

nginx's default is **1 MB**: without this, a video upload dies with a bare 413
before PHP is ever reached, and the owner sees no validation message at all.

---

## 5. nginx

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain;
    root /var/www/tickets/public;
    index index.php;

    client_max_body_size 120m;

    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Uploaded media. Static only — never add a PHP handler in this location,
    # or an uploaded file becomes executable code on our own origin.
    location /storage/ {
        add_header X-Content-Type-Options "nosniff" always;
        expires 30d;
        access_log off;
    }

    # Content-hashed build output.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Reverb WebSocket. Long read timeout: these connections are meant to idle.
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }

    location /apps/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml;
}
```

---

## 6. Supervisor

```ini
[program:tickets-queue]
command=php /var/www/tickets/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
stopwaitsecs=3600

[program:tickets-reverb]
command=php /var/www/tickets/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
stopwaitsecs=3600
```

`stopwaitsecs` matters: killing a queue worker mid-job loses the broadcast.

---

## 7. First deploy

```bash
git clone <repo> /var/www/tickets && cd /var/www/tickets
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env      # then fill it in
php artisan key:generate
php artisan migrate --force
php artisan storage:link

php artisan config:cache && php artisan route:cache && php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache
```

Create the first super admin — public registration is closed, so there is no
other way in:

```bash
php artisan tinker --execute='
  $u = new App\Models\User;
  $u->name = "Super Admin";
  $u->email = "you@example.com";
  $u->password = Hash::make("<a long password>");
  $u->role = App\Enums\UserRole::SuperAdmin;
  $u->email_verified_at = now();
  $u->save();
'
```

Then add venue owners from **Admin → Owners → Add owner**, which creates the
account and its venue together.

---

## 8. Subsequent deploys

```bash
cd /var/www/tickets
php artisan down --render="errors::503"

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart tickets-queue tickets-reverb

php artisan up
```

Restarting the workers is not optional: they hold the **old** code in memory
until they are cycled, so a deploy without it leaves stale logic broadcasting.

---

## 9. Verifying the deploy

The health endpoint (`/up`) only proves PHP runs. These prove the product works:

```bash
curl -I https://your-domain/            # 200 + X-Frame-Options: DENY
curl https://your-domain/sitemap.xml    # XML, and contains no /t/ URLs
```

Then, by hand:

1. Book a ticket on a public event page → the ticket page renders with a QR.
2. Open the QR URL while signed out → **redirected to login**. If it verifies
   the ticket, stop and investigate: that is the whole security model.
3. Sign in as the owner, check in some of the party → the holder's page flips
   to Paid **without a refresh**. If it does not, the queue worker or Reverb is
   down.
4. Print a door sheet → app chrome gone, black on white.

---

## 10. Backups

```bash
pg_dump swaida_tickets | gzip > /backups/tickets-$(date +%F).sql.gz
tar czf /backups/storage-$(date +%F).tar.gz storage/app/public
```

`storage/app/public` holds every cover, gallery image and promo video. It is
**not** in git and cannot be regenerated from the database — the rows point at
files that would no longer exist.
