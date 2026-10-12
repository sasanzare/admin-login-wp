# Saza-login

Saza-login is a lightweight WordPress plugin that styles the native login screen, optionally serves it from a custom URL, and limits repeated failed authentication attempts.

## Requirements

- WordPress 5.6 or later
- PHP 7.4 or later
- MySQL or MariaDB with the WordPress database abstraction
- Pretty permalinks for the optional custom login URL

The code declares these minimum versions. Runtime integration was checked on WordPress 6.8.3, PHP 8.2.12, and MariaDB 10.4.32; a wider compatibility matrix has not been tested.

## Install

1. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
2. Upload `saza-login.zip` and activate Saza-login.
3. Open **Settings → Saza-login**.

The default login design and brute-force protection are enabled. The custom login URL is disabled until an administrator explicitly enables it.

## Appearance and logo

The plugin keeps the core WordPress login form and its authentication behavior. Its local CSS changes spacing, colors, focus states, notices, and the responsive layout. It adds no frontend JavaScript, remote fonts, CDN assets, or frontend library.

Logo priority is:

1. Custom image selected in Saza-login
2. The WordPress theme's `custom_logo`
3. The site name as readable text

Images keep their original proportions. The logo links to the site home page. Browser autofill, password managers, and WordPress's built-in password visibility control remain in the core form.

The design and admin assets load only on the WordPress login screen and the Saza-login settings screen respectively. Persian translations are included, and layout direction follows the current WordPress locale.

The login card is centered with a full-width sign-in button and a compact language selector below it. Custom primary/background colors remain inherited from the settings. Short screens scroll naturally so longer notices and password-reset forms remain reachable. The login stylesheet uses its file modification time alongside the plugin version for cache invalidation.

## Custom login address

1. Keep an administrator session open and copy the proposed URL shown in Settings.
2. Select a single lowercase slug such as `my-secure-login`.
3. Save the slug, then enable **Change login address** and save again.
4. Open the proposed address in another tab. The plugin keeps `wp-login.php` available until the new route successfully opens, then switches generated login links and returns 404 for ordinary direct login requests at the old address.
5. Test sign-in from a private browser window before ending the administrator session.

The feature uses WordPress rewrite rules and URL filters. It requires pretty permalinks, an unused public path, and the normal web-server rewrite that sends WordPress paths to `index.php`. It is unavailable on Multisite. The plugin does not edit `.htaccess`, Nginx configuration, WordPress core, or `wp-config.php`.

Ordinary direct requests to `wp-login.php` receive a 404 while the custom URL is active. Core password reset, registration, logout, protected-post password, and recovery actions are retained on their generated custom URLs. A short set of recovery actions on the legacy URL is also retained so older password-reset and Recovery Mode links can complete.

### Recovery

- Use the copied custom URL or open **Settings → Saza-login** in an existing administrator session and turn off **Change login address**.
- If the dashboard is unavailable, rename `wp-content/plugins/saza-login` to another folder name in File Manager or over SFTP. WordPress then deactivates the plugin and restores the standard login URL.
- If WP-CLI is installed, run `wp plugin deactivate saza-login` from the WordPress root.
- After access returns, restore the plugin folder name if needed, activate the plugin, and review the saved slug and permalink settings.

There is no hidden bypass URL, shared token, or fixed password.

## Brute-force protection

The default limit is five failures for one IP and username/email pair in 15 minutes, with a separate ceiling of 20 failures per IP in the same window. The initial lock lasts 15 minutes. Repeated lockouts for the same bucket grow progressively and are capped at 24 hours. A successful sign-in clears that IP/login pair's counter. A daily WordPress Cron event removes old rows; deactivation clears the site's temporary records.

Failed attempts are counted from WordPress's `authenticate` flow, including the core username/password and email authentication filters. Core Application Password authentication is checked through its WordPress authentication and Application Password hooks. The table stores HMAC hashes for the IP and IP/login pair; raw usernames, raw IPs, and passwords are not stored.

By default, the plugin uses only the connection's `REMOTE_ADDR`. To use Cloudflare or another reverse proxy, select a header and list only proxy IPs/CIDRs you control. `X-Forwarded-For` is walked from the trusted end of the chain. Do not add public or untrusted proxy ranges.

The optional XML-RPC setting disables XML-RPC methods that require authentication. It does not disable pingbacks or every custom XML-RPC method. REST is not disabled.

### Rate-limit coverage and limits

- Custom authentication code that bypasses WordPress's `authenticate` hook may not be counted.
- WordPress cookie authentication is not a password attempt and is not counted.
- The plugin does not claim complete coverage of all third-party REST or SSO authentication handlers.
- In-flight concurrent requests that passed the initial check may complete; failures are persisted with atomic database upserts so later attempts see the updated ceiling.
- The protection depends on the plugin's database table being available. If table creation fails, the settings screen shows an error and authentication continues to avoid locking all users out.
- Hiding the login URL is not sufficient security by itself, and rate limiting cannot guarantee protection against every attack.

## Multisite

Rate-limit rows use shared network table storage with per-site counters. The custom login URL feature is disabled on Multisite because network-specific login paths need separate routing validation. Settings remain per site. On uninstall with data deletion enabled, only the current site's settings and rows are removed; the shared network table is retained to protect other sites.

## Uninstall and data retention

Deactivation restores the standard login URL, clears scheduled cleanup, and removes temporary lock records. It preserves settings and the database table. On uninstall, settings and the table are preserved by default. Select **Delete data on uninstall** before deleting the plugin to delete this site's settings and rate-limit rows. On single-site installs, that choice also drops the Saza-login table; on Multisite, the shared table remains.

## Compatibility and conflicts

The isolated WordPress 6.8.3 runtime check does not cover other WordPress/PHP versions, browsers, Multisite activation, or every web server. The plugin uses the standard login, rewrite, Settings API, and Application Password hooks. Another login URL, security, SSO, or rewrite plugin may alter the same URLs or authentication hooks; test changes on staging and keep a recovery path available.

## Validation status

See [tests/README.md](tests/README.md) for checks actually executed and the WordPress runtime scenarios that remain unverified.

