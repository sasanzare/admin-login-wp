# Project Handoff

## Last Updated

2026-10-12

## Project Summary

This repository contains the Saza-login WordPress plugin requested in the user's brief. The plugin styles the native login screen, offers a verified opt-in login route, and limits repeated failed authentication.

## Current Objective

Refine the login form appearance from the user's Digidento screenshot and deliver an updated installable ZIP.

## Current Phase or Milestone

Appearance changes, static Chromium fixture checks, documentation, and refreshed packaging are complete. Live-site deployment/verification remains outstanding. No Git commit was performed.

## Repository State

- Repository path: `D:\All projects\admin-login-wp`
- Current branch: `main`
- Base or starting commit: `2a8486468b9c9dbbbd22fc7fd1152304380c3f13`
- Current HEAD: `2a8486468b9c9dbbbd22fc7fd1152304380c3f13`
- Working tree status: implementation, documentation, and package were already uncommitted at the start of this appearance task and have been preserved.
- Staged changes: none
- Unstaged changes: `README.md`
- Untracked files: `AGENTS.md`, `HANDOFF.md`, plugin source/assets/translations, tests, OKF bundle, and `saza-login.zip`.

## Completed Work

- Implemented the Saza-login 1.0.0 plugin and WordPress Settings API page.
- Added responsive login styling, automatic/custom logo handling, Persian translation files, and settings-only media/copy JavaScript.
- Added a custom login route that leaves the standard URL available until the new route has successfully served the core login screen.
- Added MySQL/MariaDB rate-limit storage with HMAC bucket keys, per-IP and per-IP/login counters, progressive lockouts, trusted proxy parsing, XML-RPC authentication control, and deactivation/uninstall behavior.
- Added installation, recovery, compatibility, and limitation documentation.
- Added helper tests and a scoped project-specific lesson to the verified mistakes log.
- Ran a disposable WordPress 6.8.3/PHP 8.2.12/MariaDB 10.4.32 integration site without changing the existing XAMPP sites or databases.
- Verified core Application Password failure/success hooks and the optional XML-RPC authentication filter in the isolated test site.
- Updated the OKF bundle with routing/storage decisions, security boundaries, and test evidence.
- Centered the login card and placed the language selector below it, expanded the submit button to full width, harmonized field/icon styling, preserved password-toggle clearance, fixed custom color inheritance, and enabled natural short-screen scrolling.
- Added stylesheet file modification time to its cache version; no authentication, routing, schema, or dependencies changed.
- Verified RTL/LTR static core-style fixtures at 320, 360, 390, 430, 768, 1280, and 1920px; visually inspected mobile/desktop previews.
- Refreshed the installable ZIP and verified every packaged file against source SHA-256.

## Work in Progress

None.

## Remaining Work

- Test the ZIP on each target hosting stack before deployment; only the documented WordPress/PHP/MariaDB combination has runtime coverage.
- Broaden browser, Multisite, proxy, concurrency, and server-stack testing when those environments are available.

## Exact Next Steps

1. In staging, upload [saza-login.zip](saza-login.zip), replace the installed plugin version, and verify appearance, password visibility, language switching, and sign-in on the target site.
2. Keep the documented File Manager/SFTP or WP-CLI recovery path available while validating the login route.

## Files Created

- `AGENTS.md`
- `HANDOFF.md`
- `saza-login.php`, `uninstall.php`, and `includes/class-*.php`
- `assets/css/login.css`, `assets/css/admin.css`, and `assets/js/admin.js`
- `languages/saza-login-fa_IR.po` and `languages/saza-login-fa_IR.mo`
- `readme.txt`
- `tests/README.md` and `tests/run.php`
- `docs/okf/` project, architecture, decision, security, and testing documents
- `saza-login.zip` (installable archive with a `saza-login/` top-level directory)

## Files Modified

- `README.md` replaced the initial 16-byte repository-name stub with the plugin documentation.
- This appearance task modified `assets/css/login.css`, `includes/class-login-style.php`, `README.md`, `tests/README.md`, `docs/okf/architecture/current-architecture.md`, `docs/okf/testing/current-test-coverage.md`, `docs/okf/log.md`, `HANDOFF.md`, and `saza-login.zip`.

## Important Architecture and Design Decisions

- Adopted official OKF v0.2.
- Kept WordPress core login/authentication and forms; the custom path includes core `wp-login.php` through a private rewrite query variable.
- Custom URL is opt-in and verification-gated; ordinary old-path login is hidden only after the new route responds.
- Rewrite flushing is soft; WordPress core, `wp-config.php`, and server rewrite files are not modified.
- Rate limit rows use a base-prefix table and per-site IDs for Multisite. The custom login URL is disabled on Multisite.
- Rate-limit identifiers use HMAC; no raw password, login identifier, or client IP is stored.
- Uninstall preserves data unless the administrator explicitly enables deletion.

## Commands Executed

- Inspected repository files and Git state; the initial commit was `2a8486468b9c9dbbbd22fc7fd1152304380c3f13` on `main`.
- Read official OKF v0.2 and WordPress Settings, rewrite, authentication, XML-RPC, activation, Application Password, and uninstall documentation.
- Ran XAMPP PHP CLI syntax checks and `php tests/run.php`.
- Compiled and checked `languages/saza-login-fa_IR.mo` with Python standard-library code.
- Ran `node --check assets/js/admin.js`, OKF structure/link checks, archive entry validation, and PHP lint on the extracted ZIP.
- Started a disposable MariaDB instance and PHP development server bound to `127.0.0.1` under approved elevated execution; both were stopped after each run.
- Ran isolated WordPress HTTP checks and inspected the temporary WordPress `debug.log`.
- Appearance task: read repository instructions/docs, verified the project mistakes fingerprint/log, inspected Git with `core.fsmonitor=false`, ran XAMPP PHP lint/helper tests, and ran a Playwright Chromium fixture script using the installed browser cache.
- Appearance task: rebuilt the ZIP with .NET compression and checked SHA-256 for all 13 packaged files. A directory-only ZIP entry from the earlier archive was omitted.
- Built-in Docker daemon was unavailable; WP-CLI was not installed.

## Validation and Test Results

- PHP syntax: passed for plugin, uninstall, and helper-test files.
- Helper tests: 15 passed, covering slug validation, forwarded-header trust, IPv4/IPv6 CIDR handling, and rate-limit state transitions.
- Isolated runtime: WordPress 6.8.3, PHP 8.2.12, MariaDB 10.4.32.
- Runtime checks passed: plugin activation/table/daily-cleanup setup, 3-failure threshold and subsequent block, email sign-in, Application Password failure/success hooks, XML-RPC authentication filter, pending-route fallback, custom login page/CSS, old-path 404 after verification, lost-password form route, legacy reset confirmation, REST, registered admin-ajax action, core URL filters, reauthentication redirect parameters, real login form sign-in to `/wp-admin/`, and the authenticated settings/clear-limits UI.
- The authenticated settings page rendered; its Settings API form and nonce-protected clear-limits form were separate and the clear action succeeded.
- Final isolated `WP_DEBUG_LOG`: no PHP warnings, notices, or fatal errors.
- Updated `saza-login.zip`: 13 file entries, 29,938 bytes; all packaged files match current source SHA-256. Changed appearance PHP passed lint and all 15 helper checks passed again.
- Appearance fixtures in Chromium passed responsive geometry, lost/reset/error forms at 320x480, custom primary color, and reduced-motion checks. RTL screenshots at 390/1280px were inspected.
- OKF required frontmatter structure and relative links passed for 12 documents; a full YAML parser was unavailable. Existing frontmatter/version was preserved.
- `git diff --check` reported a trailing blank line at the end of the previously modified README; no unrelated formatting cleanup was applied. This Git check does not include untracked plugin files.
- Other versions/browsers, live-site browser verification, Multisite network activation, true parallel HTTP attempts, live Application Password REST and XML-RPC clients, Recovery Mode email, uninstall, and Apache/Nginx/IIS configurations were not tested.

## Known Issues and Blockers

- No blocker remains for the requested implementation/package task.
- Only one WordPress/PHP/database version combination received runtime integration coverage.
- Direct localhost HTTP access from the sandbox was denied; appearance verification used static core-style fixtures instead of changing or logging into the installed site.

## Risks and Assumptions

- A future server or rewrite-plugin change can break a previously verified custom route. Recovery is documented through the standard URL, File Manager/SFTP deactivation, or WP-CLI.
- Rate limiting covers WordPress's core authentication/Application Password hooks; custom handlers that bypass them may not be counted.
- In-flight concurrent authentication requests may complete, while atomic database upserts record their failures for subsequent attempts.

## Database or Migration State

- Plugin schema: one base-prefix table with site ID, HMAC bucket hash, attempt/window/block/penalty fields, and indexed cleanup timestamp; created with `dbDelta` on activation.
- A disposable WordPress/MariaDB test installation was created under the repository and removed after validation; no test database or server process remains.
- No existing site database was changed.

## Configuration and Environment Notes

- XAMPP PHP CLI is available at `D:\xampp\php\php.exe` (8.2.12); it is not on PATH.
- WP-CLI is unavailable. Docker is installed but its daemon is not running.
- Existing XAMPP WordPress sites were read only; the integration site was copied into and removed with the task's disposable directory.
- The project mistakes log is `D:\All projects\Mistakes\admin-login-wp\mistakes.md`; its fingerprint matches this project root.
- This task reviewed that log without changing it; its location is outside the writable roots. No new recurring project lesson required a log update.
- Appearance script and screenshots are outside the package at `D:\CodexHome\Codex\visualizations\2026\10\12\01a12f1f-9a15-7bb3-a658-38dd7278f6d1`.

## Uncommitted or Partially Applied Changes

All intended implementation/documentation changes and `saza-login.zip` are uncommitted. No files are staged. The generated test-only WordPress/MariaDB tree has been removed.

## Recovery or Rollback Notes

- Standard WordPress login is restored by deactivation. If the dashboard is inaccessible, rename `wp-content/plugins/saza-login` or use `wp plugin deactivate saza-login`.
- No commit, branch change, migration on an existing site, or external-site modification was made.
- The previous installable ZIP was copied before replacement to `C:\Users\moxfo\AppData\Local\Packages\sandbox.{a776fff3-148e-46af-95bc-8d24e1732a67}\AC\Temp\saza-login-appearance-758d27a55c644d6c884fd0501eb5a7e4\saza-login-before.zip`. This temporary backup may be removed by environment cleanup.

## Related Documentation

- [README.md](README.md)
- [OKF bundle index](docs/okf/index.md)
- [Current test coverage](docs/okf/testing/current-test-coverage.md)
- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/)
- [WordPress rewrite API](https://developer.wordpress.org/apis/rewrite/)
- [Official OKF v0.2 specification](https://github.com/GoogleCloudPlatform/open-knowledge-format/blob/main/SPEC.md)

## Notes for the Next Agent

Install the updated ZIP on staging and verify the native controls and final appearance with the active theme/plugins. Packaging is complete. Do not commit or alter branches unless separately requested.

