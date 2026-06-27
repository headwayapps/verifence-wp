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

Verifence Spam Shield connects WordPress to the Verifence platform — a real-time spam and bot protection network. It adds layered defences to comments, logins, and registrations without requiring users to solve puzzles or click checkboxes.

The plugin combines the Verifence Shield invisible bot-detection widget, server-side token verification, a local honeypot, brute-force rate limiting, disposable email blocking, URL threat scanning, a shared network block list, an IP/email allowlist, and a detailed activity log.

**Comment Protection**

* Verifence Shield invisible widget — proof-of-work + behavioural analysis in the browser
* Server-side Shield token verification via Verifence siteverify
* Configurable minimum human score (0–100) — low-scoring submissions are blocked
* Comment spam scoring — flag comment text that scores above a configurable threshold
* Honeypot field — catches bots that populate hidden fields
* Optional URL scanning — every link in a comment is checked against the Verifence URL threat feed
* Verifence Block List — blocks IPs and emails already flagged across the network
* Logs missing tokens, failed verification, low scores, spam signals, and block-list hits

**Login Protection**

* Verifence Shield widget on the WordPress login form
* WordPress nonce validation
* Honeypot field
* Local brute-force rate limiter — configurable attempt threshold and lockout duration
* Optional admin email notification when an IP is locked out
* Logs failed attempts, lockouts, nonce failures, and Shield rejections

**Registration Protection**

* Verifence Shield token verification on the registration form
* Deep email scan via Verifence Email Scan API — checks the submitted address before the account is created
* Disposable and temporary email blocking
* Verifence Email Rules — block registrations by email address, domain, IP, or country
* Email typo detection — shows a "did you mean?" suggestion for common misspellings
* Verifence Block List — checks email and IP before allowing registration

**Activity Log**

* Searchable, filterable log of every blocked or flagged event
* Shows event type, IP address, username, email, event details, and timestamp
* 14-day bar chart and top-IP leaderboard for at-a-glance activity trends
* Filter by event type; search by IP, email, username, or details
* **Allow IP** button — adds an IP to the local allowlist in one click and reports it to Verifence as a false positive
* **Allow Email** button — adds an email to the local allowlist and reports it to Verifence as a false positive
* False positive reports are reviewed by the Verifence team and can remove entries from the shared network block list
* Configurable log retention (default 30 days); automatic daily pruning

**IP & Email Allowlist**

* IP Allowlist — trusted IPs bypass all comment, login, and registration checks
* Email Allowlist — trusted email addresses bypass registration and comment checks
* Both allowlists are managed from the Settings page or updated in one click from the Activity Log

**General**

* Configurable API fallback behavior — allow or block submissions when Verifence is unreachable
* Per-event logging controls — choose exactly which event types to record
* Optional trust of proxy forwarding headers (for sites behind Cloudflare, load balancers, etc.)
* Settings page under *Verifence Spam Shield* in the WordPress admin menu
* Uninstall cleans up all plugin data (log table, options, transients)

== Getting Started ==

= 1. Create a Verifence account =

Go to https://app.verifence.io and sign up for a free account. No credit card is required to start.

= 2. Get your API key =

Your API key authorises email scanning, URL scanning, and block-list lookups.

1. Log in to https://app.verifence.io.
2. Open **Settings → API Keys** in the left sidebar.
3. Click **Create API Key**, give it a name (e.g. "My WordPress Site"), and copy the key.

Keep this key secret — it authenticates all server-to-server requests from your site.

= 3. Create a Shield site and get your site key and secret =

The Shield widget requires a public **site key** (loaded in the browser) and a **secret key** (used server-side to verify tokens).

1. In the Verifence dashboard, go to **Shield → Sites**.
2. Click **Add Site**, enter your domain, and save.
3. On the site detail page, copy the **Site Key** (starts with `sk_`) and the **Secret Key** (starts with `sec_`).

= 4. Configure the plugin =

1. Upload the `dss-spam-shield` folder to `/wp-content/plugins/` and activate it.
2. Go to **Verifence Spam Shield → Settings** in your WordPress admin.
3. Paste your **API Key**, **Shield Site Key**, and **Shield Secret** into the corresponding fields.
4. Choose which protections to enable (Comments, Login, Registration).
5. Click **Save Changes**.

The plugin is now active. Visit your site, submit a comment or attempt a login, and check **Verifence Spam Shield → Activity Log** to confirm events are being recorded.

== Installation ==

1. Upload the `dss-spam-shield` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Verifence Spam Shield → Settings**.
4. Enter your Verifence API key, Shield site key, and Shield secret key.
5. Choose which protections and log events you want enabled.
6. Click **Save Changes**.

== Frequently Asked Questions ==

= Do I need a Verifence account? =

Yes. A free account at https://app.verifence.io gives you an API key and lets you create Shield sites. Without credentials the plugin falls back to the honeypot check only.

= Where do I find my API key? =

Log in to https://app.verifence.io → **Settings → API Keys** → **Create API Key**.

= Where do I find my Shield site key and secret? =

Log in to https://app.verifence.io → **Shield → Sites** → select your site. The site key and secret key are shown on the detail page.

= Does this conflict with other CAPTCHA plugins? =

Avoid running multiple challenge widgets on the same form. Verifence Spam Shield adds the Shield widget to all enabled forms automatically.

= A legitimate user was blocked. What do I do? =

Open **Verifence Spam Shield → Activity Log**, find the event, and click **Allow IP** or **Allow Email**. This adds the address to your local allowlist immediately and sends a false positive report to Verifence so the shared network block list can be reviewed.

= Can I whitelist my own IP during development? =

Yes. Go to **Settings → General** and add your IP to the IP Allowlist. Allowlisted IPs skip all protection checks.

= What happens when the Verifence API is unreachable? =

The **API Fallback** setting controls this. Set it to *Allow* (default) to pass submissions through when the API is down, or *Block* to reject them with a "try again shortly" message.

= Does this protect XML-RPC or the REST API? =

The local brute-force rate limiter applies to all authentication attempts. The Shield widget and honeypot apply only to normal form submissions (comment form, wp-login.php).

= Where are logs stored? =

In a custom `{prefix}dss_log` database table. All data is removed when you uninstall the plugin.

= How long are logs kept? =

By default 30 days. Change the retention period under **Settings → Logs**. A daily cron job prunes old entries automatically.

== Changelog ==

= 2.0.0 =
* Renamed to Verifence Spam Shield.
* Added Verifence Shield invisible widget for comments, login, and registration.
* Added disposable email blocking, Verifence Email Rule checks, and URL threat scanning.
* Added Verifence Block List checks for IPs and emails on comments and registrations.
* Added IP Allowlist and Email Allowlist with one-click allow actions in the Activity Log.
* Added false positive reporting — Allow IP / Allow Email submits feedback to the Verifence network for review.
* Added comment spam scoring and configurable spam score threshold.
* Added 14-day activity chart and top-IP summary in the Activity Log.
* Added per-event logging controls and configurable log retention.
* Added optional proxy header trust (Cloudflare, load balancers).

== Upgrade Notice ==

= 2.0.0 =
Adds Verifence Shield verification, network block-list checks, email scanning, and false positive reporting. After upgrading, add your Verifence API key, Shield site key, and Shield secret under Verifence Spam Shield → Settings.
