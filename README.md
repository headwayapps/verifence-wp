# Verifence Spam Shield for WordPress

Verifence Spam Shield connects WordPress to the [Verifence](https://verifence.io) platform — a real-time spam and bot protection network. It adds layered defences to comments, logins, and registrations without requiring users to solve puzzles or click checkboxes.

## Features

**Comment Protection**
- Verifence Shield invisible widget — proof-of-work + behavioural analysis in the browser
- Server-side Shield token verification via Verifence siteverify
- Configurable minimum human score (0–100)
- Comment spam scoring with configurable threshold
- Honeypot field
- Optional URL scanning — every link checked against the Verifence URL threat feed
- Verifence Block List — blocks IPs and emails flagged across the network

**Login Protection**
- Verifence Shield widget on the WordPress login form
- WordPress nonce validation and honeypot field
- Local brute-force rate limiter — configurable attempt limit and lockout duration
- Optional admin email notification on lockout

**Registration Protection**
- Verifence Shield token verification
- Deep email scan — checks for disposable/temporary addresses before account creation
- Verifence Email Rules — block by email address, domain, IP, or country
- Email typo detection with "did you mean?" suggestion
- Verifence Block List check on email and IP

**Activity Log**
- Searchable, filterable log of every blocked or flagged event
- 14-day bar chart and top-IP leaderboard
- **Allow IP** / **Allow Email** buttons — one-click allowlist + false positive report sent to Verifence
- Configurable retention period with automatic daily pruning

**IP & Email Allowlist**
- Trusted IPs and emails bypass all checks
- Managed from Settings or updated in one click from the Activity Log

**General**
- Configurable API fallback (allow or block when Verifence is unreachable)
- Per-event logging controls
- Optional proxy header trust (Cloudflare, load balancers)
- Full uninstall cleanup

## Requirements

- WordPress 5.9 or newer
- PHP 7.4 or newer
- Verifence account — [sign up free at app.verifence.io](https://app.verifence.io)

## Getting Started

### 1. Create a Verifence account

Go to [app.verifence.io](https://app.verifence.io) and sign up for a free account.

### 2. Get your API key

1. Log in to [app.verifence.io](https://app.verifence.io)
2. Go to **Settings → API Keys**
3. Click **Create API Key**, name it (e.g. "My WordPress Site"), and copy it

### 3. Get your Shield site key and secret

1. In the Verifence dashboard, go to **Shield → Sites**
2. Click **Add Site**, enter your domain, and save
3. Copy the **Site Key** (`sk_…`) and **Secret Key** (`sec_…`) from the site detail page

### 4. Install and configure

1. Upload this plugin folder to `wp-content/plugins/`
2. Activate **Verifence Spam Shield** in WordPress
3. Go to **Verifence Spam Shield → Settings**
4. Paste your API key, Shield site key, and Shield secret
5. Choose which protections to enable and click **Save Changes**

## Handling False Positives

If a legitimate user gets blocked, open **Verifence Spam Shield → Activity Log**, find the event, and click **Allow IP** or **Allow Email**. This adds the address to your local allowlist immediately and sends a false positive report to Verifence for network-level review.

## License

GPL v2 or later. See `readme.txt` for the full WordPress plugin readme and changelog.
