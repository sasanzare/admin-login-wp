---
type: Decision Record
title: Custom Login Routing
description: How Saza-login changes the login address while preserving the WordPress login script.
tags:
  - wordpress
  - routing
  - authentication
status: stable
sources:
  - id: wordpress-rewrite-api
    resource: https://developer.wordpress.org/apis/rewrite/
    title: WordPress Rewrite API
  - id: wordpress-activation-hooks
    resource: https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
    title: WordPress Activation and Deactivation Hooks
---

# Decision

Register a single-segment rewrite rule that maps the configured login slug to a private query variable, then include the core `wp-login.php` script for that request. Keep the core login form and authentication actions intact.

The feature is disabled by default. When enabled, the standard URL remains active until the custom route returns the core login screen once. That successful route marks the address verified; only then are generated login URLs rewritten and ordinary direct login requests to `wp-login.php` answered with 404.

# Rationale

WordPress core continues to own login, logout, password reset, registration, protected-post passwords, and Recovery Mode. A verification request prevents a misconfigured web-server rewrite from immediately locking out the administrator.

Rewrite rules are refreshed on activation, route changes, permalink-structure changes, and deactivation using a soft flush. The plugin does not write server configuration files.

# Constraints

- Pretty permalinks must be enabled.
- The selected slug must not match a reserved endpoint, an existing public post path, or a file/directory under the WordPress installation.
- Custom login URLs are disabled on Multisite.
- A later server or plugin rewrite change can still make a previously verified route unavailable; the documented recovery path is plugin deactivation.

# Related

- [Current architecture](../architecture/current-architecture.md)
- [Authentication security controls](../security/current-security-controls.md)

