# 11 — Deployment on Afrihost/cPanel-Style Hosting

## Goal

Deploy haydenn.co.za and Trailbound in a way that works on standard shared hosting.

## Recommended deployment layout

Best case if you can create folders outside `public_html`:

```text
/home/username/
  private/
    .env
    config/
    logs/
    storage/
  public_html/
    index.html
    assets/
    app/
    api/
```

If you cannot create folders outside `public_html`, use:

```text
public_html/
  index.html
  assets/
  app/
  api/
  private/
    .htaccess
    .env
    config/
    logs/
```

And protect `private/`:

```apache
Deny from all
```

or for Apache 2.4:

```apache
Require all denied
```

## PHP version

Use the newest stable PHP version available in Afrihost/cPanel.

Recommended:

```text
PHP 8.1+
PHP 8.2 preferred
```

Required extensions:

- PDO
- PDO MySQL
- cURL
- JSON
- OpenSSL
- mbstring

## MySQL

Create one database:

```text
trailbound
```

Create one DB user with only needed permissions.

Required permissions:

- SELECT
- INSERT
- UPDATE
- DELETE
- CREATE
- ALTER during setup only

Remove CREATE/ALTER later if desired.

## Environment file

Create `.env`.

Do not put `.env` in a public readable folder.

Example:

```env
APP_ENV=production
APP_URL=https://haydenn.co.za
DB_HOST=localhost
DB_NAME=trailbound
DB_USER=your_db_user
DB_PASS=your_db_password
STRAVA_CLIENT_ID=your_client_id
STRAVA_CLIENT_SECRET=your_client_secret
STRAVA_REDIRECT_URI=https://haydenn.co.za/api/strava/callback.php
STRAVA_VERIFY_TOKEN=random_string
MAPBOX_PUBLIC_TOKEN=pk.your_public_token
APP_KEY=random_secret_key
```

## Strava setup

In Strava developer settings:

- Create app
- Set callback domain to your domain
- Use redirect URL:

```text
https://haydenn.co.za/api/strava/callback.php
```

For development, use the allowed local/redirect setup supported by Strava or test directly on staging.

## Mapbox setup

Create a Mapbox public token.

Restrict it to:

```text
https://haydenn.co.za/*
https://www.haydenn.co.za/*
```

Keep secret tokens server-side only.

## Cron jobs

In cPanel Cron Jobs, add:

### Process Strava webhooks

```bash
*/15 * * * * /usr/local/bin/php /home/username/private/cron/process_strava_webhooks.php
```

### Daily maintenance

```bash
0 2 * * * /usr/local/bin/php /home/username/private/cron/daily_maintenance.php
```

Paths may differ on Afrihost. Use the correct PHP binary shown in cPanel.

## File permissions

Recommended:

```text
Folders: 755
Files: 644
.env: 600 if possible
```

## HTTPS

Ensure SSL is active for haydenn.co.za.

Do not run OAuth callbacks over HTTP.

## `.htaccess` suggestions

In `public_html/.htaccess`:

```apache
Options -Indexes

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set X-Frame-Options "SAMEORIGIN"
</IfModule>

RewriteEngine On
```

If using a single PHP router later:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]
```

But for simple endpoint files, do not need this.

## Backups

Minimum:

- Weekly database export
- Keep copies of `.env` securely offline
- Export important game data before schema changes

## Deployment checklist

### Personal site

- Upload `index.html`
- Upload `assets/`
- Test desktop
- Test mobile
- Test HTTPS
- Test reduced motion

### Trailbound

- Create database
- Run schema SQL
- Upload API files
- Upload app frontend
- Add `.env`
- Test DB connection
- Test register
- Test login
- Test Strava connect
- Test manual sync
- Test reward processing

## Common shared hosting issues

### Problem: Composer not available

Solution:

- Install dependencies locally
- Upload `vendor/`
- Or avoid dependencies initially

### Problem: Cannot put files outside public_html

Solution:

- Put private folder inside public_html
- Block access with `.htaccess`
- Test by visiting `https://haydenn.co.za/private/.env` and confirm it is forbidden

### Problem: Cron PHP path unknown

Solution:

- Check cPanel documentation
- Use `which php` over SSH if available
- Ask host support if needed

### Problem: OAuth callback fails

Check:

- HTTPS active
- Redirect URL exactly matches Strava app settings
- No trailing slash mismatch
- PHP errors hidden from output but logged

### Problem: Mapbox map blank

Check:

- Token exists
- Token domain restrictions include exact domain
- Browser console errors
- Mapbox CSS included
