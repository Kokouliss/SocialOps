# Pilot setup: platform apps, server, first client

What Panos has to do once so the app can log in to the platforms and publish. Everything here is our own app on each platform (decision D2), nothing paid. Keep every id and secret in `.env` on the server, never in git.

The redirect URIs below use `https://YOUR-DOMAIN`. Replace it with the real domain of the app (the `APP_URL` in `.env`). HTTPS is required by all three platforms.

## 1. Meta (Facebook Pages and Instagram) → `META_APP_ID`, `META_APP_SECRET`

1. Go to https://developers.facebook.com/apps and log in with the Facebook account that is an admin of the agency's Business Manager (Business Portfolio).
2. **Create app** → use case "Other" → type **Business** → name it `Social Ops` → attach the agency's Business Portfolio.
3. In the app dashboard add two products: **Facebook Login for Business** and **Instagram** (the "Instagram API with Facebook Login" setup).
4. Facebook Login for Business → **Settings** → *Valid OAuth Redirect URIs*: `https://YOUR-DOMAIN/oauth/meta/callback`. Save.
5. **App settings → Basic**: copy **App ID** and **App Secret** (click *Show*). Fill *Privacy Policy URL* and *App icon* now; App Review asks for them later. Add `YOUR-DOMAIN` under *App Domains*.
6. **App Roles → Roles**: every staff member who will press *Connect* in Social Ops must be an *Administrator* or *Developer* of the app. While the app is in **Development mode** only these people can log in, and only the Pages and Instagram accounts they manage can be published to. That is enough for the pilot.
7. The pilot client's Page (Fuse Pharmacy) and its Instagram professional account must be managed by the agency: in Business Manager add the Page to the portfolio (or request access from the client) and give the staff member *Full control* or *Content* on it. Instagram must be a Business or Creator account linked to that Page.
8. While the app is in Development mode a Facebook Page post it publishes is visible only to people with a role on the app (Instagram posts are normal). So before Fuse Pharmacy's Facebook posts can go public: **App Review** for `pages_show_list`, `pages_manage_posts`, `pages_read_engagement`, `instagram_basic`, `instagram_content_publish`, `business_management` (screencast of the Connect flow and one post going out), plus **Business Verification** of the agency. Start it in week one of the pilot; Instagram and the approval flow can be tested meanwhile.

`.env`:
```
META_APP_ID=…
META_APP_SECRET=…
META_GRAPH_VERSION=v26.0
```

## 2. TikTok → `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`

1. Go to https://developers.tiktok.com and log in with the agency's TikTok account (create one if needed). Complete the developer registration (organisation, website).
2. **Manage apps → Connect an app** → name `Social Ops`, category *Marketing*, platform *Web*, website `https://YOUR-DOMAIN`.
3. Add two products: **Login Kit** and **Content Posting API**. In Login Kit set *Redirect URI* `https://YOUR-DOMAIN/oauth/tiktok/callback`. In the scopes list tick `user.info.basic` and `video.publish`.
4. For Content Posting API with "pull from URL" (what the app uses) you must **verify the domain** the media is served from: the app's own domain for the pilot (Settings → URL properties → add `YOUR-DOMAIN`, place the verification file or DNS record it gives you).
5. **Submit the app for review** (basic review, needed before any real account can log in). After that it still counts as *unaudited*: posts are forced private. Apply for the **Content Posting API audit** from the same page once the pilot has a private test post going out.
6. Copy **Client key** and **Client secret** from the app's *App details* page.

`.env`:
```
TIKTOK_CLIENT_KEY=…
TIKTOK_CLIENT_SECRET=…
TIKTOK_AUDITED=false     # set true the day TikTok approves the audit
```

Until the audit passes, Social Ops publishes a TikTok post only when its privacy is *Only me* (a test); public posts go to Manual-Needed and you post them in the TikTok app.

## 3. Google Business Profile → `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`

1. Go to https://console.cloud.google.com with the agency Google account → **New project** `Social Ops`.
2. **APIs & Services → Library**: enable *My Business Account Management API*, *My Business Business Information API* and *Google My Business API* (v4, used for posts). Enabling is allowed, calling is not until Google grants access (step 5).
3. **OAuth consent screen**: *External*, app name `Social Ops`, support e-mail, the app's domain and privacy policy URL; scope `https://www.googleapis.com/auth/business.manage`. Publishing status can stay *Testing* for the pilot: add the staff Google accounts under *Test users* (test refresh tokens expire after 7 days while in Testing; set the app to *In production* before the pilot ends, which for this scope needs Google's verification).
4. **Credentials → Create credentials → OAuth client ID** → *Web application* → *Authorised redirect URIs*: `https://YOUR-DOMAIN/oauth/google/callback`. Copy **Client ID** and **Client secret**.
5. **Request API access**: fill in the Business Profile API access form at https://developers.google.com/my-business/content/prereqs (link "Request access") with the project number and the agency's business details. Quota is 0 until they approve; it usually takes days to a couple of weeks.
6. The staff Google account must be an *Owner* or *Manager* of the client's Business Profile location (Fuse Pharmacy) in https://business.google.com, or the location will not appear after Connect.

`.env`:
```
GOOGLE_CLIENT_ID=…
GOOGLE_CLIENT_SECRET=…
```

## 4. Claude → `ANTHROPIC_API_KEY`

Already in hand. Only in `.env`. Rotate at https://console.anthropic.com/settings/keys if it was ever pasted anywhere.

## 5. Server (decision D3: own hosting)

The app needs a Linux server you can install software on (a VPS, not shared cPanel hosting): Ubuntu 24.04, 2 GB RAM is enough for the pilot.

1. Install: nginx, PHP 8.3 with `php8.3-fpm php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-intl`, Composer 2, Node 22, PostgreSQL 16, Redis 7, certbot.
2. Create a Postgres database and user; point a DNS record `YOUR-DOMAIN` at the server; `certbot --nginx` for HTTPS.
3. Deploy as the `www-data`-readable user:
   ```
   git clone https://github.com/Kokouliss/SocialOps /var/www/socialops && cd /var/www/socialops
   composer install --no-dev --optimize-autoloader && npm ci && npm run build
   cp .env.example .env && php artisan key:generate
   ```
   Edit `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://YOUR-DOMAIN`, the Postgres values, `MAIL_*` for a real mailbox (reminders, failure alerts; an SMTP mailbox at the hosting provider is fine), the platform ids above. For the pilot keep `FILESYSTEM_DISK=local` (media is served from the server through signed links); move to an EU S3-compatible bucket before adding more clients.
4. `php artisan migrate --force && php artisan optimize`, then `php artisan staff:create "Your Name" you@agency.gr` for each staff member (there is no public registration).
5. nginx server block for `/var/www/socialops/public` (the standard Laravel one from https://laravel.com/docs/deployment#nginx).
6. Two background processes, both required for publishing:
   - **Horizon** (queue): a systemd service running `php /var/www/socialops/artisan horizon`, restarted on deploy with `php artisan horizon:terminate`.
   - **Scheduler**: crontab line `* * * * * cd /var/www/socialops && php artisan schedule:run >> /dev/null 2>&1`. It runs `posts:publish-due` every minute, `reviews:remind` hourly, `accounts:check-tokens` daily.
7. Deploy updates with `git pull && composer install --no-dev && npm run build && php artisan migrate --force && php artisan optimize && php artisan horizon:terminate`.

## 6. First client: Fuse Pharmacy (decision D4)

1. Clients → New client. Code `FUSE`, type `brand`. Paste the profile YAML (a starter is in the project Library, `clients/fuse-pharmacy.yaml`) and adjust voice, pillars, hashtags, approver name and e-mail, SLA.
2. Accounts → Connect Facebook / Instagram, Google (TikTok when the app review is through) and tick the Fuse Pharmacy Page, Instagram account and location.
3. Assets → copy the upload link and send it to the client.
4. Calendar → create one test post for tomorrow, *Draft captions with Claude*, send it for approval to yourself first (put your own e-mail as approver for the first run), approve from your phone, watch it publish. Then put the client's approver in and start the two weeks.

What to watch during the pilot: Horizon's *Failed jobs* page, the post's red *Publishing* box, and the Manual-Needed e-mails.
