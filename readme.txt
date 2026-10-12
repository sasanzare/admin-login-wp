=== Saza-login ===
Contributors: saza-login
Tags: login, security, brute-force, logo, rtl
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight design for the native WordPress login screen, optional custom login address, and database-backed rate limits.

== Description ==

Saza-login preserves the WordPress login form and authentication flow. It adds a responsive login screen design, site or custom logo, optional custom login URL, and configurable rate limits for failed authentication.

The custom login URL is disabled by default. Brute-force protection and the custom login design are enabled by default.

== Installation ==

1. Upload the saza-login.zip file from Plugins > Add New Plugin > Upload Plugin.
2. Activate Saza-login.
3. Configure it under Settings > Saza-login.

== Frequently Asked Questions ==

= Does hiding the login URL replace other security controls? =

No. Hiding the login URL is not sufficient security by itself. Use strong passwords, updates, HTTPS, and appropriate account security.

= What happens if I forget the custom login URL? =

Use the saved URL in an administrator session, deactivate the plugin through File Manager/SFTP, or run `wp plugin deactivate saza-login` with WP-CLI.

= Does Saza-login block every authentication method? =

No. It counts failures through WordPress authentication hooks and core Application Password hooks. Authentication methods that bypass those hooks may not be covered. REST remains enabled.

== Changelog ==

= 1.0.0 =
* Initial release.

