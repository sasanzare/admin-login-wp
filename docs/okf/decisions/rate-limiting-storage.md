---
type: Decision Record
title: Rate-Limit Storage
description: Database and identity choices for failed WordPress authentication attempts.
tags:
  - security
  - database
  - authentication
status: stable
sources:
  - id: wordpress-authenticate
    resource: https://developer.wordpress.org/reference/hooks/authenticate/
    title: WordPress authenticate Hook
  - id: wordpress-application-passwords
    resource: https://developer.wordpress.org/reference/functions/wp_authenticate_application_password/
    title: WordPress Application Password Authentication
---

# Decision

Store rate-limit state in one small database table named with the WordPress base prefix. Rows use a site ID and an HMAC bucket hash as the primary key. Separate buckets cover an IP and login-identifier pair and the IP alone.

Use atomic `INSERT ... ON DUPLICATE KEY UPDATE` statements to count failures and apply progressive lockouts. The standard limit is five failures per pair and 20 per IP in 15 minutes, with a 15-minute initial lock and a 24-hour maximum.

# Rationale

Transients and ordinary read-then-write options do not provide reliable atomic increments under concurrent authentication. The table is touched only from authentication flows and has an indexed timestamp for daily stale-row cleanup.

The HMAC input includes the IP and, for pair buckets, a normalized username or email. Raw usernames, IP addresses, and passwords are not stored. A successful login clears that pair's bucket; the separate IP counter remains.

# Limits

Requests already in flight when the threshold is reached may finish. Their failures are still counted atomically, and subsequent attempts see the block. Authentication implementations that bypass WordPress's `authenticate` and Application Password hooks may not be covered.

# Related

- [Authentication security controls](../security/current-security-controls.md)
- [Testing scope](../testing/current-test-coverage.md)

