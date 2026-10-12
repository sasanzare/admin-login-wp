---
type: Security Controls
title: Current Security Controls
description: Security-sensitive behaviors implemented by Saza-login and their coverage limits.
tags:
  - security
  - wordpress
  - authentication
status: draft
sources:
  - id: wordpress-authenticate
    resource: https://developer.wordpress.org/reference/hooks/authenticate/
    title: WordPress authenticate Hook
  - id: wordpress-application-passwords
    resource: https://developer.wordpress.org/reference/functions/wp_authenticate_application_password/
    title: WordPress Application Password Authentication
  - id: wordpress-xmlrpc-enabled
    resource: https://developer.wordpress.org/reference/hooks/xmlrpc_enabled/
    title: WordPress xmlrpc_enabled Hook
---

# Credential and Error Handling

The plugin does not replace WordPress authentication or persist submitted passwords. For core credential errors on the interactive login screen, it returns one generic message for an invalid username, email, or password.

Failed attempts pass through the core `authenticate` filter. Core Application Password requests are checked through the `determine_current_user` filter and the Application Password success/failure actions. Custom authentication code that bypasses those hooks may not be counted.

# Rate-Limit Data

The table stores HMAC hashes of the connection IP and the IP/login pair, per-site attempt counts, window start, block expiry, and a bounded penalty level. It uses the WordPress base table prefix so a Multisite network can share storage while keeping each site's counters separate.

The connection address comes from `REMOTE_ADDR` by default. Forwarded headers are ignored until an administrator selects a header and explicitly lists trusted proxy IP addresses or CIDRs. The X-Forwarded-For chain is walked from the trusted end.

# Other Authentication Paths

- Optional XML-RPC protection uses `xmlrpc_enabled`, which disables XML-RPC methods requiring authentication. It does not disable pingbacks or arbitrary custom XML-RPC methods.
- REST API routes remain enabled.
- Cookie-based authentication is not a failed password attempt.
- Third-party SSO, REST authentication, or application-specific handlers that bypass WordPress's covered hooks may not be rate limited.
- Hiding a URL and rate limiting are useful controls, not guarantees against all attacks.

# Recovery and Lifecycle

The custom login URL is opt-in and only becomes active after the configured route successfully serves the core login screen. Deactivation disables that route, restores the standard URL, clears scheduled cleanup, and removes temporary rate-limit rows. The plugin has no bypass token or fixed password.

# Related

- [Custom login routing decision](../decisions/custom-login-routing.md)
- [Rate-limit storage decision](../decisions/rate-limiting-storage.md)
- [Testing scope](../testing/current-test-coverage.md)

