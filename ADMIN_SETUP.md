# Aram Mizuri — Admin Panel & CMS

Your portfolio is now a **Laravel application**. The public site looks and behaves
exactly as before, but its projects and text now come from a database you control
through a secure admin panel.

| What | Where |
|------|-------|
| Public site | `http://localhost:8000/` |
| Admin panel (secret) | `http://localhost:8000/studio-aks-panel` |
| Database | SQLite file at `database/database.sqlite` |

---

## First-time setup (run these once)

> **Where:** your IDE's terminal, inside the project folder
> `/Users/blindjamil/aram-web`

1. **Install PHP dependencies**
   ```bash
   composer install
   ```

2. **Create your environment file** (copies the template; keeps secrets out of git)
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Create the database tables and load your projects + text**
   ```bash
   php artisan migrate --seed
   ```
   You should see each migration and "Seeded 21 projects" / "Seeded 68 site settings".

4. **Create YOUR admin login** (you type your own email + password — it is never
   stored in code)
   ```bash
   php artisan make:filament-user
   ```
   Enter a name, your email, and a strong password when prompted.

---

## Running it

```bash
php artisan serve
```
Then open:
- Public site → http://localhost:8000
- Admin → http://localhost:8000/studio-aks-panel (log in with the account from step 4)

Press `Ctrl + C` in the terminal to stop the server.

---

## What you can do in the admin

- **Projects** — add, edit, reorder, publish/unpublish, and delete projects.
  Upload images or paste image URLs. Changes appear on the live site immediately.
- **Ordering the grid** — the order here is the order visitors see. Two ways to
  change it:
  - the **⌃⌄ arrows** button above the table turns on drag mode; grab a row and
    drop it anywhere. Drag mode lists every project on one page, so you can pull
    one from the bottom all the way to the top in a single drag.
  - the **⌃⌃ / ⌄⌄ buttons on each row** send that project straight to the start
    or the end of the grid — handy when the two ends are far apart, and they
    still work while the list is filtered or searched.

  Turn on the **Order** column (the "toggle columns" button) if you want to see
  each project's exact position number.
- **Site Content** — edit the hero, about, process, heritage, contact and footer text
  in English **and** Kurdish, from one screen.
- **Messages** — every submission from the public contact form lands here.
- **Your account** — click your initials (top-right) → **Profile** to change your
  name, email, or password. Changing your email or password requires re-entering your
  **current password** for safety.
- **Dashboard** — a personal greeting plus live visitor analytics: visitors today, this
  week, and this month, a 30-day trend chart, and your latest contact messages.

---

## Security notes (already handled for you)

- The admin lives at a **secret URL** (`/studio-aks-panel`) — there is no `/admin`.
- **Login is rate-limited**: after 5 wrong passwords the account is temporarily locked.
- The **contact form is rate-limited** (5 / minute / visitor) to stop spam.
- **No public sign-up** — the only way to create an admin is the `make:filament-user`
  command above.
- Your password and app key live in `.env`, which is **git-ignored** (never committed).

---

## About the visitor analytics

The dashboard counts real visits to your public site automatically — no third-party
tracker, no cookies. For privacy it never stores anyone's IP address; it keeps only a
one-way daily fingerprint so it can count **unique visitors per day**.

> **Heads up — demo data:** to show you the dashboard populated, I seeded ~30 days of
> *sample* visits. **Before you go live, clear it** so your numbers are real:
> ```bash
> php artisan tinker --execute="App\Models\PageView::query()->delete();"
> ```
> Real tracking is already active, so visits will start counting from then on. To preview
> with sample data again any time: `php artisan db:seed --class=DemoVisitsSeeder`.

## Going live later (separate step)

GitHub Pages can't run Laravel. When you're ready to publish, host it on a PHP
platform (Laravel Cloud, Railway, Render, or a small VPS). Ask me and I'll walk you
through it. On the server you run the same first-time setup, plus:
```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
```
and serve over **HTTPS**.
