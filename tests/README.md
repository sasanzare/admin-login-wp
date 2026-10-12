# Saza-login checks

Run the dependency-free helper checks with the PHP CLI:

```text
php tests/run.php
```

They cover slug validation, IP/CIDR parsing, spoofed forwarding headers, trusted proxy chain selection, and rate-limit state transitions. They do not bootstrap WordPress or test database behavior.

## Executed integration run

An isolated temporary site used WordPress 6.8.3, PHP 8.2.12, and MariaDB 10.4.32. Its database and HTTP server were bound to `127.0.0.1`; the local XAMPP sites and their databases were left untouched.

The run covered plugin activation/table/cleanup-event creation; the rate threshold and email sign-in through `wp_signon()`; core Application Password success/failure hooks and bucket clearing; the XML-RPC authentication filter; a custom login form submission that reached `/wp-admin/`; standard login remaining available before the custom route was verified; the custom screen and local CSS; old-path 404 after verification; password reset form routing and legacy reset confirmation; login/lost-password/register/logout URLs and reauthentication redirects; REST and a registered `admin-ajax.php` action; the authenticated settings page, separate nonce-protected clear-limits form, and Settings API form rendering. WordPress debug logs were clear of PHP warnings, notices, and fatal errors during the final HTTP runs.

The run did not cover other WordPress/PHP versions, browser rendering, RTL/mobile screenshots, Multisite activation, concurrent HTTP attempts, Application Passwords over a live REST request, XML-RPC clients, Recovery Mode email delivery, or Apache/Nginx/IIS deployments. Do not treat the single-version run as a complete compatibility or security certification.

## Appearance fixture checks (2026-10-12)

Headless Chromium separately rendered static login markup with local WordPress core CSS and the updated plugin CSS. RTL/LTR widths 320, 360, 390, 430, 768, 1280, and 1920px passed checks for overflow, centered card, language selector below the card, and full-width submit. Lost/reset/error fixtures at 320x480, custom primary colors, and reduced motion also passed. RTL screenshots at 390 and 1280px were inspected. This was not a live WordPress browser integration run.

