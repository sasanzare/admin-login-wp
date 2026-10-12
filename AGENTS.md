# Saza-login Agent Guide

## Purpose and boundaries

This repository contains the installable Saza-login WordPress plugin. Keep changes inside the plugin; do not edit WordPress core, `wp-config.php`, or server rewrite files. Runtime dependencies must remain limited to WordPress and PHP.

## Architecture and sensitive areas

- Preserve WordPress core authentication and login forms; extend them through public hooks.
- Keep custom login routing opt-in and fail closed when its prerequisites or slug validation fail.
- Store no raw passwords, login identifiers, or client IP addresses in rate-limit data.
- Load login and admin assets only on their respective plugin screens.
- Review the login URL and rate limiter code as security-sensitive.

## Commands

- PHP syntax: `php -l <file.php>` (requires PHP CLI).
- WordPress integration checks: see `tests/README.md`; they require a WordPress test environment.
- Package: `Compress-Archive` the plugin contents under a `saza-login/` directory as `saza-login.zip`.

## Navigation

- Product use, setup, recovery, and limitations: [README.md](README.md)
- Current work state and recovery: [HANDOFF.md](HANDOFF.md)
- Structured project knowledge: [docs/okf/index.md](docs/okf/index.md)
- Security controls: [docs/okf/security/current-security-controls.md](docs/okf/security/current-security-controls.md)
- Deployment, recovery, and known limitations: README.

