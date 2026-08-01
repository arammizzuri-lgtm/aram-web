# Deployment

Target: a single Ubuntu 24.04 LTS VPS running Nginx, PHP-FPM 8.4 and PostgreSQL 16.

This is written for the shape of *this* system. Two things about it drive most of
the decisions below:

- **The money is in an append-only ledger.** `stock_movements` and
  `cash_transactions` are never updated in place, and every landed-cost run is
  versioned. That makes the database the single source of truth and makes a
  restorable backup the most important thing on this page.
- **PDF rendering shells out to a real browser.** Invoices lay out Arabic and
  Kurdish correctly because headless Chromium does the bidirectional text, not a
  PHP PDF library. That means the server needs a browser installed, and it means
  PDF generation is the one operation with a heavy memory spike.

Everything here assumes a domain pointed at the box and root or sudo access.

---

## 1. Sizing

| Users | vCPU | RAM | Disk |
|---|---|---|---|
| 1–5 | 2 | 4 GB | 40 GB SSD |
| 5–20 | 4 | 8 GB | 80 GB SSD |

**RAM is the binding constraint, not CPU.** Chromium holds ~300–400 MB while
rendering a PDF. Two people printing invoices at once on a 2 GB box will hit the
OOM killer, and the process it kills is usually PHP-FPM, which looks like the
whole ERP falling over rather than a printing problem. Do not go below 4 GB.

Add swap regardless — it converts an OOM kill into a slow request:

```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

---

## 2. Packages

```bash
apt update && apt upgrade -y

# PHP 8.4
add-apt-repository ppa:ondrej/php -y && apt update
apt install -y php8.4-fpm php8.4-cli \
  php8.4-bcmath php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl

apt install -y postgresql-16 nginx redis-server unzip git curl
apt install -y chromium-browser fonts-noto fonts-noto-arabic fonts-kacst

curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

Two of those lines are not optional and are easy to skip:

- **`php8.4-bcmath`** — every figure in the system is computed through
  `App\Support\Money`, which is arbitrary-precision bcmath arithmetic. It is
  declared in `composer.json` as `ext-bcmath`, so `composer install` will refuse
  to run without it rather than let you discover it when a landed cost is wrong.
- **`fonts-noto-arabic`** — Chromium renders what it has. Without Arabic fonts
  installed *on the server*, invoice PDFs come out with tofu boxes where the
  customer's Arabic name should be. The English side looks perfect, so this
  passes a casual smoke test and fails in front of a customer.

---

## 3. PostgreSQL

SQLite is the development default and it is genuinely fine there. It is not fine
here: it takes a **single writer lock across the whole database file**, so one
person committing a goods receipt blocks everyone else's invoice from saving.
With an append-only ledger writing on nearly every action, that ceiling arrives
early.

```bash
sudo -u postgres psql <<'SQL'
CREATE USER erp WITH PASSWORD 'use-a-generated-password-here';
CREATE DATABASE erp OWNER erp ENCODING 'UTF8' LC_COLLATE 'en_US.UTF-8' LC_CTYPE 'en_US.UTF-8' TEMPLATE template0;
GRANT ALL PRIVILEGES ON DATABASE erp TO erp;
SQL
```

`TEMPLATE template0` with an explicit UTF-8 collation matters — supplier and
product names carry Chinese (`name_zh`) and customer names carry Arabic. A
database created under the C collation will sort and compare those wrongly.

### Verify before you trust it

The application is developed against SQLite, and the two databases disagree in
ways that do not show up until you switch. Run the suite against PostgreSQL on
the server before putting real data in:

```bash
sudo -u postgres createdb erp_test -O erp
cd /var/www/erp
php artisan test --env=testing   # with DB_CONNECTION=pgsql, DB_DATABASE=erp_test
```

The suite includes `searching_ignores_case_on_every_database`, which exists
precisely for this run. SQLite matches `LIKE` case-insensitively and PostgreSQL
does not, so a search written as a plain `LIKE` silently stops finding anything
after the switch. The code uses Laravel's `whereLike()`, which emits `ILIKE`
where the driver needs it — that test is what stops a regression to raw `LIKE`.

> **Honest caveat:** the queries have been audited for PostgreSQL portability
> (every aggregate names all its non-aggregate columns in `GROUP BY`, no `enum`
> columns, no MySQL-only migration constructs), but this project's suite has only
> been executed against SQLite. Treat the PostgreSQL run above as a required
> deployment step, not a formality.

---

## 4. Application

```bash
adduser --system --group --home /var/www/erp erp
cd /var/www/erp
# copy the project here (git clone, rsync, or scp a tarball)

sudo -u erp composer install --no-dev --optimize-autoloader
sudo -u erp npm ci && sudo -u erp npm run build
```

`npm run build` needs outbound network access: the Vite config pulls webfonts
through `laravel-vite-plugin/fonts`. If the server is firewalled outbound, build
`public/build/` on your machine and ship the directory — the built assets are
self-contained.

Do **not** install the dev dependencies. `puppeteer` is a devDependency used only
for local screenshot checks; on the server, Browsershot drives the system
Chromium via `CHROME_PATH`.

### Configure

```bash
sudo -u erp cp .env.example .env
sudo -u erp php artisan key:generate
sudo -u erp nano .env
```

Every line marked `[PROD]` in `.env.example` needs changing. The ones with teeth:

| Key | Value | Why it matters |
|---|---|---|
| `APP_DEBUG` | `false` | A stack trace renders query bindings. This system's queries carry supplier costs and margins — the numbers you would least like a salesperson or a customer to read off an error page. |
| `APP_ENV` | `production` | Gates the destructive artisan commands behind a confirmation prompt. |
| `APP_TIMEZONE` | e.g. `Asia/Baghdad` | Drives more than display. The 07:00 alert sweep and 00:20 KPI snapshot fire in this zone, and "today" on an invoice resolves in it. Left at UTC, an evening delivery on the 1st is reported against the 2nd. |
| `SESSION_SECURE_COOKIE` | `true` | Only after TLS is working, or nobody can log in. |
| `CHROME_PATH` | `/usr/bin/chromium` | Without it PDF actions fail and nothing else does. |
| `ANTHROPIC_API_KEY` | optional | Omit it and the Ask page says so; every other module is unaffected. |
| `BACKUP_DISK` | off-server | See §7. |

```bash
sudo -u erp php artisan migrate --force
sudo -u erp php artisan db:seed --class=FoundationSeeder --force
sudo -u erp php artisan db:seed --class=ReferenceDataSeeder --force
sudo -u erp php artisan db:seed --class=RolePermissionSeeder --force
sudo -u erp php artisan storage:link
```

**Do not run `DemoDataSeeder` or `CrystalCatalogueSeeder` on production.** They
write fictional suppliers, products, containers and invoices. Once demo
containers are costed and demo invoices are posted, the ledger contains entries
you cannot delete without breaking referential integrity — you would be
rebuilding the database to get rid of them.

Create the real first user instead:

```bash
sudo -u erp php artisan tinker --execute="
\$u = App\Models\User::create(['name' => 'Aram', 'email' => 'you@yourdomain.com', 'password' => 'CHANGE-ME', 'is_active' => true]);
\$u->assignRole('owner');
"
```

Change that password at first login. There is no open registration — the panel
is login-only, so this is the only way an account comes into existence.

### Cache the config

```bash
sudo -u erp php artisan config:cache
sudo -u erp php artisan route:cache
sudo -u erp php artisan view:cache
sudo -u erp php artisan icons:cache
```

Once `config:cache` has run, **`env()` returns null outside config files**. That
is the intended Laravel behaviour and the code respects it (`config('services.anthropic.key')`,
never `env(...)` at runtime), but it is also why every deploy must re-run these
after touching `.env`.

### Permissions

```bash
chown -R erp:www-data /var/www/erp
find /var/www/erp -type f -exec chmod 644 {} \;
find /var/www/erp -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/erp/storage /var/www/erp/bootstrap/cache
chmod 600 /var/www/erp/.env
```

---

## 5. Nginx and TLS

```nginx
server {
    listen 80;
    server_name erp.yourdomain.com;
    root /var/www/erp/public;

    index index.php;
    charset utf-8;

    # Invoice PDFs and price-list imports are the large transfers here.
    client_max_body_size 32M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Chromium needs time to boot and lay out a multi-page RTL invoice.
        # The default 60s produces a 504 on the first print of the day, when
        # the browser is cold — which reads as "PDFs are broken" rather than
        # "PDFs are slow", so people stop trying.
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
}
```

```bash
ln -s /etc/nginx/sites-available/erp /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

apt install -y certbot python3-certbot-nginx
certbot --nginx -d erp.yourdomain.com   # rewrites the block above for TLS
```

Only after certbot succeeds, set `SESSION_SECURE_COOKIE=true` and
`APP_URL=https://…`, then re-run `php artisan config:cache`.

---

## 6. Background processes

Two things must run continuously. Neither is optional.

### Scheduler

```ini
# /etc/systemd/system/erp-scheduler.service
[Unit]
Description=ERP scheduler
After=network.target

[Service]
Type=simple
User=erp
ExecStart=/usr/bin/php /var/www/erp/artisan schedule:work
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

This drives the 07:00 business alerts (low stock, overdue invoices, containers
arriving, credit limits breached), the 00:20 KPI snapshot the dashboard reads
from, and the nightly backup. Without it the dashboard silently serves stale
figures — it does not error, it just quietly stops being true.

### Queue worker

```ini
# /etc/systemd/system/erp-queue.service
[Unit]
Description=ERP queue worker
After=network.target

[Service]
Type=simple
User=erp
ExecStart=/usr/bin/php /var/www/erp/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now erp-scheduler erp-queue
```

> The application dispatches no queued jobs today — imports and PDF rendering
> run synchronously. The worker is here because framework and package internals
> (notifications, backup notifications) do queue, and because the moment anything
> is moved to the queue, a missing worker means the work simply never happens with
> no error anywhere. Run it from the start.

**A worker holds the old code in memory.** Every deploy must run
`php artisan queue:restart`, or the worker keeps executing the previous release.

---

## 7. Backups

Configured in `config/backup.php` and scheduled in `routes/console.php`: backup
at 01:30, prune at 02:30, health check at 03:00. That order is deliberate —
pruning before the new archive lands would, on the night a backup fails, delete
the old archives and leave nothing.

Two decisions in that config worth knowing about, because both differ from the
package defaults:

**The archive contains the database and `storage/app` — not the codebase.**
The default sweeps up `base_path()`, which includes `.env`, which holds the
database password and the Claude API key. An archive shipped to cloud storage is
the last place those belong. Code and vendor are rebuildable; a scanned proforma
invoice attached to a shipment is not.

Back `.env` up separately and deliberately — a password manager entry or an
encrypted note. It is one file that changes perhaps twice a year.

**Success notifications are off; failures and health checks are on.** A nightly
"backup succeeded" email trains you to filter the sender, which is exactly the
sender you need to notice on the night it says something else. `backup:monitor`
covers the silent-failure case by failing loudly when the newest archive is too
old or too small.

### Off-server destination

A backup on the same disk as the database is not a backup. Set `BACKUP_DISK` to
an S3-compatible disk (Backblaze B2, Hetzner Object Storage, Wasabi) and fill in
the `AWS_*` keys, or `rsync` the local archives to another host nightly.

### The restore drill

Do this once, now, before you have data you cannot lose. An untested backup is a
belief, not a backup.

```bash
sudo -u postgres createdb erp_restore_test -O erp
gunzip -c /path/to/backup/db-dumps/postgresql-erp.sql.gz | sudo -u postgres psql erp_restore_test
sudo -u postgres psql erp_restore_test -c "select count(*) from stock_movements;"
sudo -u postgres dropdb erp_restore_test
```

If that count matches production, the backup is real.

---

## 8. Redeploying

```bash
#!/usr/bin/env bash
# /var/www/erp/deploy.sh
set -euo pipefail
cd /var/www/erp

php artisan down --render="errors::503"
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
php artisan queue:restart
```

The `trap` matters: without it, a failed migration leaves the site in
maintenance mode with no obvious way back for anyone who is not you.

**Back up before any deploy that migrates.** `php artisan backup:run` takes
seconds and migrations are not reversible in practice once new rows exist.

### Verify the deploy

From a workstation with the dev dependencies installed — not on the server:

```bash
SMOKE_EMAIL=you@yourdomain.com SMOKE_PASSWORD='…' \
  node tests/Browser/smoke.cjs /tmp https://erp.yourdomain.com
```

It logs in and loads all 20 screens, failing on any non-200 or uncaught JS
error. The PHPUnit suite renders pages through Livewire, which catches
server-side breakage but cannot see a broken asset build or a JavaScript error —
which are exactly the two things a deploy introduces. A green suite and a white
screen are entirely compatible states.

---

## 9. Security checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `.env` is `chmod 600` and owned by `erp`
- [ ] TLS active, `SESSION_SECURE_COOKIE=true`
- [ ] PostgreSQL listening on localhost only (`listen_addresses = 'localhost'`)
- [ ] UFW: `ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable`
- [ ] SSH password auth disabled, key auth only
- [ ] `fail2ban` installed
- [ ] Demo seeders never run against this database
- [ ] Every user has the narrowest role that lets them work

That last one is doing real work here. The roles are not decorative: `view_cost`
gates supplier prices, landed costs and margins throughout the system —
including the AI assistant, whose tool surface drops the cost-bearing tools
entirely for users without it (`app/Services/Ai/ErpToolSurface.php`). A
salesperson given the `manager` role for convenience can read your entire cost
structure and, through the assistant, ask for it in plain language.

- [ ] `unattended-upgrades` enabled for security patches

---

## 10. Troubleshooting

**PDFs fail or hang.** Check `CHROME_PATH` resolves (`ls -l $(which chromium)`)
and that the `erp` user can execute it. Chromium in a container or hardened host
may need `--no-sandbox`; Browsershot exposes this via `->noSandbox()`.

**PDFs render but Arabic is boxes.** Fonts are missing on the server:
`apt install fonts-noto-arabic fonts-kacst`. Nothing in the application changes
this — Chromium can only draw glyphs it has.

**A 504 on the first print of the day.** `fastcgi_read_timeout` is too low; the
browser is cold-starting. 120s in the Nginx block above.

**Config change had no effect.** `config:cache` is stale. Re-run it. This is the
single most common post-deploy confusion.

**Permissions look wrong after a role change.** spatie/laravel-permission caches
its map: `php artisan permission:cache-reset`.

**Dashboard figures frozen.** The scheduler is not running —
`systemctl status erp-scheduler`. The KPI snapshot is what the dashboard reads.

**"Please provide a valid cache path."** `storage/framework` subdirectories are
missing after a fresh copy:
`mkdir -p storage/framework/{cache,sessions,views} && chmod -R 775 storage`.

---

## 11. What to watch after go-live

For the first month, check weekly:

- `php artisan backup:monitor` reports healthy
- `systemctl status erp-scheduler erp-queue` — both active
- `df -h` — Chromium's temp files and old backups are the two things that fill a
  disk here
- `tail -n 200 storage/logs/laravel-*.log`
- Actual landed costs against your own spreadsheet for the first two containers

That last check is the one worth doing properly. The costing engine is tested to
the cent against a worked example (`docs/04-LANDED-COST.md`), but the test proves
the arithmetic, not that your freight forwarder's invoice was entered under the
right cost type with the right allocation basis. Reconcile the first two
containers by hand. After that, trust it.
