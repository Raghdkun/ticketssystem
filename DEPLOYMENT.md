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

### Firebase Cloud Messaging

Push is optional and inert while blank. It has **two halves that fail
independently**, and half-configuring it is the confusing state:

| Missing | What happens | How it looks |
|---|---|---|
| `VITE_FCM_VAPID_KEY` | The browser is never asked for permission | The opt-in does not render on the ticket page at all. Deliberate: a prompt that cannot produce a usable token is worse than no prompt |
| `FCM_CREDENTIALS` | Devices register fine, nothing can ever be sent | Holders tap "notify me", it succeeds, and no notification ever arrives |

`VITE_FCM_VAPID_KEY` is Firebase Console → Project settings → **Cloud
Messaging → Web Push certificates → key pair**. It is public and ships in the
JS bundle, like every other `VITE_` value.

`FCM_CREDENTIALS` is an absolute path to the **service-account JSON**. That
file is a credential: keep it outside the repository and outside `public/`,
own it by the deploy user, and `chmod 600` it.

`public/firebase-messaging-sw.js` is served raw from the origin root and
cannot read Vite env vars, so its Firebase config is inlined in the file. If
you point the app at a different Firebase project, **edit that file too** —
otherwise foreground messages work and background ones silently do not.

Changing any `VITE_` value requires `npm run build`, not just
`config:cache` — they are compiled into the bundle.

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

## 6. Maps and outbound requests

The venue map is Leaflet over OpenStreetMap. **Nothing here runs on the
server** — the app makes no outbound HTTP call for maps. Three hosts are
fetched by the visitor's browser:

| Host | When | Fetched by |
|---|---|---|
| `tile.openstreetmap.org` | A map is on screen | Every visitor who opens a venue sheet, and the owner's picker |
| `nominatim.openstreetmap.org` | Owner presses **Search** on the venue page | Owners only, never visitors |
| `www.openstreetmap.org` | "Open in OpenStreetMap" is followed | Only on click |

So the VPS firewall is irrelevant to this feature, and there is no API key to
provision or rotate. What matters instead:

- **If you ever add a `Content-Security-Policy`**, it must allow
  `img-src https://tile.openstreetmap.org` and
  `connect-src https://nominatim.openstreetmap.org`, or maps go blank with
  nothing in the server log to explain it. There is no CSP today.
- **Attribution is required** by the OSM tile policy and is rendered by the
  map itself. Do not strip it with CSS.
- **Both services are free and rate-limited.** Tiles are fetched one map at a
  time and geocoding runs only on an explicit press, never as-you-type, which
  is what keeps us inside Nominatim's one-request-per-second guidance. If you
  later add map views to list pages, revisit this before shipping.

**If the OSM hosts are slow or unreachable from your users' networks**, the
feature degrades rather than breaking: the venue name still opens the sheet,
the address and landmark are server-rendered text, and on a phone the
**Directions** button is a `geo:` URL handled by whichever maps app is
installed. Only the tile image is lost. (On a desktop, where nothing handles
`geo:`, that button points at OSM's own directions page instead — so it is the
one part that does depend on OSM being reachable.) If that becomes the normal case for
your audience, the fix is a different tile URL in
`resources/js/components/map/map-canvas.tsx`; nothing else changes.

A venue with no pin is a supported state. `location` serialises as `null`, the
public page renders the venue name as plain text, and no map code loads at all.

---

## 7. Supervisor

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

## 8. First deploy

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

Tell each owner to open **Venue** and drop their pin. A venue has no location
until its owner sets one, and nothing prompts them: the public page just keeps
rendering the venue name as plain text. It is the one setup step that cannot be
done for them from the admin side.

---

## 9. Subsequent deploys

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

**If this release changed anything under `public/icons/`**, bump `VERSION` in
`public/sw.js` in the same commit. Files under `/build/` are content-hashed, so
a deploy renames them and the service worker's cache misses on its own. The
icons are not hashed — the worker serves them cache-first without
revalidating — so every already-installed PWA keeps the previous icon until
the cache name changes.

---

## 10. Verifying the deploy

The health endpoint (`/up`) only proves PHP runs. These prove the product works:

```bash
curl -I https://your-domain/            # 200 + X-Frame-Options: DENY
curl https://your-domain/sitemap.xml    # XML, and contains no /t/ URLs
curl https://your-domain/robots.txt     # absolute Sitemap: URL for this host

# Powerful features are denied by default and granted per route. The venue
# page needs geolocation or its "my location" button fails silently.
curl -sI https://your-domain/ | grep -i permissions-policy   # geolocation=()
```

Then, by hand:

1. Book a ticket on a public event page → the ticket page renders with a QR.
2. Open the QR URL while signed out → **redirected to login**. If it verifies
   the ticket, stop and investigate: that is the whole security model.
3. Sign in as the owner, check in some of the party → the holder's page flips
   to Paid **without a refresh**. If it does not, the queue worker or Reverb is
   down.
4. Print a door sheet → app chrome gone, black on white.
5. Sign in as an owner, open **Venue**, press **Search** and then drag the pin
   → tiles render and the coordinate under the map updates. Save, then open one
   of that venue's public events and tap the venue name → the sheet shows the
   map, the address and **Directions**. A blank grey map with a working pin
   means the tile host is unreachable from that network, not that the deploy is
   broken — see §6.
6. Check the browser console on any public event page → clean. Leaflet is
   lazy-loaded, so a chunk that failed to deploy shows up here and nowhere
   else.

---

## 11. Icons

Everything under `public/icons/`, plus `favicon.svg`, `favicon.ico` and
`apple-touch-icon.png`, is **generated** from `resources/brand/*.svg` by
`npm run icons`, and the output is committed. A deploy does not run it — the
rasters ship in the repository.

Regenerate only when the brand changes, and commit the result:

```bash
npm run icons
```

`@resvg/resvg-js` is a devDependency, so it is present for `npm ci` on the
build host and absent from `composer install --no-dev` runtime concerns. If a
platform ever has no prebuilt binary, the icons still ship — they are in git.

---

## 12. Backups

```bash
pg_dump swaida_tickets | gzip > /backups/tickets-$(date +%F).sql.gz
tar czf /backups/storage-$(date +%F).tar.gz storage/app/public
```

`storage/app/public` holds every cover, gallery image and promo video. It is
**not** in git and cannot be regenerated from the database — the rows point at
files that would no longer exist.
