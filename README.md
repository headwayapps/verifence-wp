# Verifence

Verifence blocks disposable or throwaway email addresses during WordPress registration and commenting by checking emails against the API provided by [Verifence](https://Verifence.io), which is constantly updated to include the latest disposable email domains. This ensures your site stays protected against new disposable email providers.

## Features
- Blocks disposable emails on comment submission.
- Blocks disposable emails during user registration.

## Installation
1. Copy the plugin folder into your WordPress `wp-content/plugins/` directory.
2. Activate **Verifence** from the Plugins screen.
3. Go to **Settings → Verifence** and enter your API key.

## Requirements
- WordPress 5.0+ (recommended)
- PHP 7.4+ (recommended)

## Notes
- If the API key is missing or the API is unavailable, Verifence allows the email to avoid blocking legitimate users.
