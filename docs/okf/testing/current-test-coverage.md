---
type: Test Coverage
title: Current Test Coverage
description: Runtime and helper validation completed for the initial Saza-login implementation.
tags:
  - testing
  - wordpress
  - php
status: draft
---

# Environment

- PHP CLI: 8.2.12
- WordPress: 6.8.3, copied into an isolated temporary test installation
- MariaDB: 10.4.32, with a new temporary data directory and database bound only to `127.0.0.1`
- The source WordPress installation and its database were not changed.

# Checks Run

- `php -l` on all plugin, uninstall, and helper-test PHP files.
- `php tests/run.php`: slug validation, untrusted header handling, trusted proxy CIDRs and chains, IPv6 matching, threshold lockout, progressive extension, window expiry, and in-flight block state.
- Isolated WordPress activation created the rate-limit table and daily cleanup event.
- `wp_signon()` tests covered three failed password attempts, the next blocked attempt, and successful email-based authentication.
- Core Application Password function checks covered a failed and successful password, per-pair counter clearing, and the XML-RPC authenticated-method filter.
- HTTP checks covered pending-route behavior, the custom login page and stylesheet, 404 for ordinary direct `wp-login.php` after verification, password-reset form action, legacy `checkemail=confirm`, REST, a registered AJAX action, generated login/lost-password/register/logout URLs, an actual login form submission that reached `/wp-admin/`, the authenticated settings page, and the separate nonce-protected clear-limits form.
- The isolated WordPress `debug.log` contained no PHP warnings, notices, or fatal errors after the custom-route include-scope fix.

# Not Run

- Other WordPress or PHP versions, live-site browser verification, browsers other than Chromium, Apache, Nginx, IIS, and WordPress installed in a subdirectory.
- Multisite network activation/deactivation.
- Parallel-request rate-limit integration, Application Passwords over a live REST request, XML-RPC client integration, Recovery Mode email flow, password reset email delivery, and uninstall on a production-style installation.

See [tests/README.md](../../../tests/README.md) for the isolated test setup boundary and the helper-test command.

# Appearance Validation (2026-10-12)

- Re-ran PHP lint on the changed appearance class and all 15 dependency-free helper checks; passed.
- Used headless Chromium with static core-style fixtures, local WordPress CSS, and the Digidento logo; no site settings or database were modified.
- RTL and LTR at widths 320, 360, 390, 430, 768, 1280, and 1920px: no horizontal overflow, centered card, language selector below the card, full-width submit button, and reachable card top.
- Lost-password, reset-password, and error-notice fixtures at 320x480 passed overflow/top-visibility checks. Custom primary color and reduced-motion preference checks passed.
- Visually inspected RTL screenshots at 390 and 1280px. These fixture checks do not establish live-site authentication or third-party plugin compatibility.

