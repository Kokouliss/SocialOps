---
name: social-ops-hosting
description: Social Ops server and deploy routine — Plesk paths, update steps after a merge, LiteSpeed/PHP quirks, mail and Passport fixes. Read before giving server steps.
sources: [cowork]
aliases: [Social Ops hosting, Social Ops deploy, Social Ops server]
---
- [stated] D3: https://socialops.inglelandi.com, a subdomain in the inglelandi.com Plesk subscription; code in /var/www/vhosts/inglelandi.com/socialops.inglelandi.com/ pulled with Plesk Git (GitHub deploy key); document root is public/
- [stated] System user SYSTEM-USER on server1 (IP SERVER-IP, behind Cloudflare); PHP binary /opt/plesk/php/8.3/bin/php; Panos has SSH and runs commands himself when given exact lines
- [stated] Platform OAuth redirect URIs: https://socialops.inglelandi.com/oauth/{meta,tiktok,google}/callback
- [stated] .env: DB_CONNECTION=mariadb, QUEUE_CONNECTION=database, CACHE_STORE=database (no Redis, Horizon unused), FILESYSTEM_DISK=local with signed links, mail through a Plesk mailbox over SMTP
- [stated] Two per-minute Plesk scheduled tasks: `artisan schedule:run` and `artisan queue:work --stop-when-empty --max-time=55`; setup in docs/pilot-setup.md

## Update routine after a merge
- [stated] Plesk Git "Pull now", then open /refresh and press "Refresh after update" (migrate --force + optimize; on its own page since #104 because the dashboard could 500 before a migration ran)
- [stated] SSH fallback: `cd /var/www/vhosts/inglelandi.com/socialops.inglelandi.com && /opt/plesk/php/8.3/bin/php artisan migrate --force && /opt/plesk/php/8.3/bin/php artisan optimize`; error line: `grep -h ERROR storage/logs/*.log | tail -2`
- [stated] Refresh installs no packages: after a pull that changed composer.lock, run `/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader` in the app folder over SSH, then Refresh
- [stated] public/build is committed since #112 (no Node on the server); before that a build.zip was extracted into public/ in Plesk File Manager
- [stated] tailwind.config.js scans only resources/views plus app/Posts/PostState.php; classes written in other PHP files are not generated

## Quirks
- [stated] The web server is LiteSpeed: PHP must run as "FPM application served by Apache" or "FastCGI application"; "served by nginx" makes every PHP request a 403 (MIME type application/x-httpd-php)
- [stated] Plesk's 404 page = document root is not public/; Plesk's 403 with static files working = the PHP handler problem
- [stated] CLI PHP prints harmless memcached.so warnings; no nano on the server: use Plesk File Manager's code editor; .env is edited on the in-app Settings page, which rebuilds the config cache
- [stated] APP_DEBUG must be false in production; delete public/index.html (Plesk default page) if it reappears
- [stated] Never run key:generate again: it breaks encrypted tokens and signed links
- [stated] Mail: MAIL_HOST=localhost:587 fails STARTTLS (certificate is for the server's full hostname); set MAIL_HOST to that hostname (Plesk → Tools & Settings → Server Settings) or MAIL_URL=smtp://localhost:25?auto_tls=false, then Refresh
- [stated] Passport: /oauth/* gave 500 until `/opt/plesk/php/8.3/bin/php artisan passport:keys; chmod 600 storage/oauth-private.key; chmod 660 storage/oauth-public.key` ran in the app folder (Refresh also makes the keys since #106)
- [stated] Give Panos one short command block per step with full paths; check the Plesk Logs error line before guessing
