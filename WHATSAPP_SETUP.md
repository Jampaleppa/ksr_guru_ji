# WhatsApp OTP Setup

Patient registration, OTP login, password-reset verification, and Super Admin second-factor login send codes only through the Meta WhatsApp Cloud API authentication-template endpoint. The application never returns OTPs to the browser or writes their values to logs.

## Meta prerequisites

1. Add and verify the business phone number in WhatsApp Manager; copy its **Phone Number ID**.
2. Create a system-user access token with the `whatsapp_business_messaging` permission for the correct WhatsApp Business Account and phone number.
3. Create an **Authentication** message template with a **Copy Code (OTP)** button, a six-digit code placeholder, and a five-minute expiry. Wait until Meta reports the template as **APPROVED** and copy its exact name and language code.
4. Obtain business opt-in for numbers before sending authentication messages.

## Server configuration

Set these variables in the web/PHP process environment or your deployment secret manager. The access token is environment-only; admin settings and database token values are ignored.

- `WHATSAPP_PROVIDER=cloud_api`
- `WHATSAPP_PHONE_NUMBER_ID=<Meta phone number ID>`
- `WHATSAPP_ACCESS_TOKEN=<system-user access token>`
- `WHATSAPP_OTP_TEMPLATE_NAME=<approved template name>`
- `WHATSAPP_OTP_TEMPLATE_LANGUAGE=en_US`
- `WHATSAPP_API_VERSION=v25.0` (or a currently supported version enabled for the app)
- `WHATSAPP_BUSINESS_ACCOUNT_ID=<Meta WhatsApp Business Account ID>` (needed for the no-send template-approval check in Admin > Settings > Integrations)

The read-only template check uses Meta Graph API template-management access in addition to the permission needed to send messages. If the token lacks that optional management permission, message sending can still be attempted and Meta's sanitized API error will be shown; approval is not inferred from an HTTP send acceptance.

For a local PowerShell session, set non-secret values and enter the token with masked input before starting/restarting the PHP server:

```powershell
$env:WHATSAPP_PROVIDER = 'cloud_api'
$env:WHATSAPP_PHONE_NUMBER_ID = '<Meta phone number ID>'
$env:WHATSAPP_OTP_TEMPLATE_NAME = '<approved template name>'
$env:WHATSAPP_OTP_TEMPLATE_LANGUAGE = 'en_US'
$env:WHATSAPP_API_VERSION = 'v25.0'
$secureToken = Read-Host 'Meta WhatsApp access token' -AsSecureString
$env:WHATSAPP_ACCESS_TOKEN = (New-Object System.Net.NetworkCredential('', $secureToken)).Password
php -S localhost:8080 -t 'D:\ksr_guru_ji'
```

This only configures the current PowerShell process and its child PHP process; restart the server after changing variables. For production, use the host's secret manager/service environment rather than a checked-in `.env` file. Never paste the token into source control, chat, browser forms, or logs.

## Verification and failure behavior

The admin Integrations page reports only whether the provider and required values are present; it never displays the token. Meta validates template availability/approval when a message is submitted. Provider logs contain only sanitized HTTP/error codes, not raw response bodies. A successful API response means Meta accepted the request, not that the phone received it; delivery confirmation requires the WhatsApp `messages` status webhook to be configured and monitored separately.

Codes are hashed in the database, expire after five minutes, allow at most five verification attempts, and are limited to a 45-second resend cooldown, three sends per account/purpose per 15 minutes, and five sends per IP per 15 minutes. If delivery is unavailable, verification fails closed: users may use password login where available; new registrations remain pending and must retry or contact support. No OTP is shown in logs or the page as a fallback.
