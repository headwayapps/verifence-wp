=== Verifence Spam Shield ===
Contributors: headwayapps
Tags: spam, anti-spam, comments, login, registration, honeypot, brute force, security
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Spam protection for WordPress comments, login, and registration using Verifence Shield, email checks, URL scanning, and block-list controls.

== Description ==

Verifence Spam Shield connects WordPress comments, login, and registration to the Verifence platform. It combines Shield bot protection, server-side token verification, local honeypot/rate-limit checks, email validation, disposable email blocking, URL threat scanning, block-list controls, and activity logs.

**Comment Protection**
* Shield token verification
* Honeypot checks
* Configurable minimum human score
* Comment spam scoring
* Optional URL scanning
* Verifence Block List checks for IPs and emails
* Logs for missing tokens, failed verification, spam scores, and block-list hits

**Login Protection**
* Shield widget on the login form
* WordPress nonce validation
* Honeypot checks
* Local brute-force rate limiting
* Optional admin notification on lockout
* Logs for failed attempts, lockouts, nonce failures, and Shield failures

**Registration Protection**
* Shield token verification
* Verifence email scanning
* Disposable and temporary email blocking
* Verifence Email Rules for email, IP, and country controls
* Verifence Block List checks
* Email typo detection hints

**General**
* IP whitelist — bypass all checks for trusted addresses
* Email whitelist
* Configurable API fallback behavior
* Activity log with event filtering, context, and pagination
* Per-event logging controls
* Automatic log pruning (configurable retention period)
* Settings page under *Verifence Spam Shield* in the admin menu
* Uninstall cleans up all data (table, options, transients)

This plugin requires a Verifence account for Shield site keys and API-backed checks.

== Installation ==

1. Upload the `dss-spam-shield` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Verifence Spam Shield → Settings**.
4. Add your Verifence platform API key, Shield site key, and Shield secret.
5. Choose which protections and log events you want enabled.

== Frequently Asked Questions ==

= Does this plugin conflict with other CAPTCHA plugins? =
Avoid running multiple CAPTCHA or challenge widgets on the same form. Verifence Spam Shield already adds the Shield widget where protection is enabled.

= Does this plugin need Verifence keys? =
Yes. Shield protection requires a public site key and server-side secret. Email, URL, and block-list checks require a Verifence platform API key.

= Can I whitelist my own IP during development? =
Yes. Add your IP on the **General** settings tab. Whitelisted IPs skip protection checks.

= Does this protect XML-RPC or the REST API login endpoint? =
The local brute-force rate limiter applies to authentication attempts. Shield widget and honeypot checks only apply to normal form submissions.

= Where are the logs stored? =
In a custom `{prefix}dss_log` database table. All data is removed if you uninstall the plugin.

== Changelog ==

= 2.0.0 =
* Renamed the plugin to Verifence Spam Shield.
* Added Verifence Shield token verification for comments, login, and registration.
* Added disposable email blocking, email rule checks, block-list checks, URL scanning, improved logs, and per-event logging controls.

== Upgrade Notice ==

= 2.0.0 =
Adds Verifence Shield verification and API-backed spam controls. Configure your Verifence API key, Shield site key, and Shield secret after upgrading.
