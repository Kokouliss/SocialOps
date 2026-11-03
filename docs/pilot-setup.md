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

## 5. Server (decision D3: Panos's Plesk server)

A subdomain of the agency domain is enough, for example `social.AGENCY.gr`; the app is internal and the links clients receive carry a signature, not a brand. Below, `SUB` is that subdomain and `ROOT` is its folder, `/var/www/vhosts/AGENCY.gr/SUB`.

1. **Websites & Domains → Add Subdomain** `SUB`. Then **SSL/TLS Certificates → Install** a free Let's Encrypt certificate and, in **Hosting Settings**, tick *Permanent SEO-safe redirect from HTTP to HTTPS*.
2. **Hosting Settings**: PHP version **8.3**, run as *FPM application served by Apache* (Apache reads the app's `.htaccess`; with *served by nginx* you must add `location / { try_files $uri $uri/ /index.php?$query_string; }` under Apache & nginx Settings → Additional nginx directives, or every page is a 404); *Document root* `SUB/public` (set this after step 4 when the folder exists; until then Plesk shows its own 404 page). If 8.3 is not offered, install it under Tools & Settings → Updates (admin).
3. **Web Hosting Access**: *Access to the server over SSH* = `/bin/bash`. Everything below runs over SSH as that user, with Plesk's PHP (its CLI prints harmless `memcached.so` warnings; `nano` may be missing, so edit `.env` in Plesk's File Manager): `alias php=/opt/plesk/php/8.3/bin/php` and `alias composer="php /usr/lib/plesk-9.0/composer.phar"` (`/usr/lib64/...` on AlmaLinux; if neither exists, download composer.phar from getcomposer.org).
4. **Websites & Domains → Git → Add repository**: remote `git@github.com:Kokouliss/SocialOps.git`, branch `main`, deploy to `SUB`. Plesk shows a public key: add it on GitHub under the repo's Settings → Deploy keys (read-only). Click *Pull now*. In *Additional deployment actions* paste the update commands from step 10 so every pull redeploys.
5. **Databases → Add Database**: MariaDB (Plesk's default) is fine, Postgres is not needed. Note the name, user and password.
6. **Mail → Create mailbox** `social@AGENCY.gr` for the reminders and alerts.
7. `.env` (in `ROOT`, copy from `.env.example`):
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://SUB
   DB_CONNECTION=mariadb
   DB_HOST=localhost
   DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=…
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   FILESYSTEM_DISK=local
   MAIL_MAILER=smtp MAIL_HOST=localhost MAIL_PORT=587 MAIL_USERNAME=social@AGENCY.gr MAIL_PASSWORD=… MAIL_FROM_ADDRESS=social@AGENCY.gr
   ANTHROPIC_API_KEY=… plus the platform ids from sections 1 to 3
   ```
   Plesk has no Redis, so the queue and cache use the database (Horizon is simply unused). Media stays on the server's disk behind signed links for the pilot; an EU S3-compatible bucket comes before more clients.
8. First install, in `ROOT`:
   ```
   composer install --no-dev --optimize-autoloader
   php artisan key:generate && php artisan migrate --force && php artisan optimize
   php artisan staff:create "Your Name" you@AGENCY.gr      # one per staff member, no public registration
   ```
   Front-end assets: if `node -v` works over SSH (Plesk's Node.js extension), run `npm ci && npm run build` there; otherwise run those two commands on your PC and upload the `public/build` folder with File Manager.
9. **Scheduled Tasks → Add Task**, type *Run a command*, cron style `* * * * *` (every minute), two tasks:
   ```
   /opt/plesk/php/8.3/bin/php ROOT/artisan schedule:run
   /opt/plesk/php/8.3/bin/php ROOT/artisan queue:work --stop-when-empty --max-time=55
   ```
   The first runs `posts:publish-due`, `reviews:remind` and `accounts:check-tokens`; the second publishes the queued posts.
10. Updates: Git → *Pull now* (or push to `main` with auto-deploy on) followed by
   ```
   composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize
   ```
   plus `npm run build` or a new `public/build` upload when the front-end changed.

Any other Linux server works the same with nginx + PHP-FPM, a database, cron for `schedule:run` and either the `queue:work` task above or Redis + `php artisan horizon` under systemd.

## 6. First client: Fuse Pharmacy (decision D4)

1. Clients → New client. Code `FUSE`, type `brand`. Paste the profile YAML (a starter is in the project Library, `clients/fuse-pharmacy.yaml`) and adjust voice, pillars, hashtags, approver name and e-mail, SLA.
2. Accounts → Connect Facebook / Instagram, Google (TikTok when the app review is through) and tick the Fuse Pharmacy Page, Instagram account and location.
3. Assets → copy the upload link and send it to the client.
4. Calendar → create one test post for tomorrow, *Draft captions with Claude*, send it for approval to yourself first (put your own e-mail as approver for the first run), approve from your phone, watch it publish. Then put the client's approver in and start the two weeks.

What to watch during the pilot: Horizon's *Failed jobs* page, the post's red *Publishing* box, and the Manual-Needed e-mails.
