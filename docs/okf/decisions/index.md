# Decisions

This directory records consequential implementation choices and their reasons.

- [Custom login routing](custom-login-routing.md) - preserve the core login script and verify the new route before hiding the old one.
- [Rate-limit storage](rate-limiting-storage.md) - use atomic database counters with HMAC identifiers.

