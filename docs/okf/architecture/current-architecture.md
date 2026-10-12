---
type: Architecture
title: Current Architecture
description: The verified architecture and implementation status of Saza-login.
tags:
  - architecture
  - wordpress
status: draft
---

# Runtime Structure

The `saza-login.php` entry point loads settings, login URL, appearance, rate limiter, and lifecycle classes under the `SazaLogin` namespace. No Composer or frontend runtime dependency is used.

- [Settings](../../../README.md) uses the WordPress Settings API and stores the small settings array as an autoloaded option.
- [Custom login routing](../decisions/custom-login-routing.md) maps one validated slug to a private query variable, then includes the core `wp-login.php` script.
- [Appearance](../../../README.md) keeps the native login form and loads local CSS only on login pages. Admin CSS and media-library JavaScript load only on the Saza-login settings screen.
- Login layout stacks a centered card and core language selector, with a full-width submit button and natural scrolling on short screens. Settings colors are inherited from root CSS variables. The login stylesheet's cache version includes its file modification time.
- [Rate limiting](../decisions/rate-limiting-storage.md) uses a shared base-prefix table with per-site row keys in Multisite and HMAC identifiers.

## Lifecycle

Activation preserves existing settings, creates the rate-limit table, schedules one daily cleanup event, registers any configured login route, and performs a soft rewrite flush. The custom URL remains disabled by default.

Deactivation disables the custom URL, restores core login links, clears temporary counters, and removes the cleanup event. Uninstall preserves data unless the administrator opted into deletion.

## Compatibility Boundaries

- Declared minimums: WordPress 5.6 and PHP 7.4.
- Runtime integration was checked on WordPress 6.8.3, PHP 8.2.12, and MariaDB 10.4.32.
- Custom login URLs require pretty permalinks and are disabled on Multisite.
- Full browser, reverse-proxy, parallel-request, and multi-version matrices remain untested.

# Related

- [Authentication security controls](../security/current-security-controls.md)
- [Current test coverage](../testing/current-test-coverage.md)
- [Decision index](../decisions/index.md)

