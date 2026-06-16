# Verifence Spam Shield for WordPress

Verifence Spam Shield protects WordPress comments, login, and registration forms
with Verifence Shield verification, local honeypot and rate-limit checks,
disposable email blocking, URL scanning, block-list controls, and activity logs.

## What It Protects

- Comment submissions, including direct POSTs missing a valid Shield token
- Login forms, including nonce failures, honeypot checks, and brute-force limits
- Registration forms, including disposable email, email rule, and block-list
  checks
- Suspicious URLs in comment bodies when URL scanning is enabled
- Admin visibility through local event logs and per-event logging controls

## Requirements

- WordPress 5.9 or newer
- PHP 7.4 or newer
- Verifence platform API key
- Verifence Shield site key and secret

## Installation

1. Upload this plugin folder to `wp-content/plugins/`.
2. Activate **Verifence Spam Shield** in WordPress.
3. Open **Verifence Spam Shield -> Settings**.
4. Add your API key, Shield site key, and Shield secret.
5. Enable the protections and log events you want.

See `readme.txt` for the WordPress plugin readme and changelog.
