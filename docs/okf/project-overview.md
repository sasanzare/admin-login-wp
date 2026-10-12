---
type: Project
title: Saza-login
description: A WordPress plugin for login screen styling, optional login URL routing, and authentication rate limiting.
tags:
  - wordpress
  - plugin
  - authentication
status: draft
---

# Scope

Saza-login is intended to be an installable WordPress plugin that improves the native login screen and adds configurable controls against repeated failed authentication attempts. The custom login URL is optional and must be recoverable without a secret bypass.

The plugin implementation is present and has passed PHP syntax/helper checks plus a limited isolated integration run on WordPress 6.8.3. Broader compatibility and security scenarios remain unverified; see [current test coverage](testing/current-test-coverage.md).

# Boundaries

- Keep WordPress core authentication and its login actions as the source of truth.
- Do not modify WordPress core, `wp-config.php`, or server rewrite configuration.
- Document authentication paths that the limiter cannot cover.

# Related

- [Current architecture](architecture/current-architecture.md)
- [Architecture index](architecture/index.md)
- [Security controls](security/current-security-controls.md)
- [Test coverage](testing/current-test-coverage.md)

