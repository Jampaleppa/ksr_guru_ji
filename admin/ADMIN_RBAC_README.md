# KSR GURU JI — Admin Portal (RBAC & Security)

Improved administrator portal: **Super Admin**, **Admin** and full
role/permission control, WhatsApp OTP second-factor, audit logging.

---

## 1. Login flows

### Super Admin

```
Username / Email  →  Password  →  WhatsApp OTP  →  Verify OTP  →  Super Admin Dashboard
```

- Full system access.
- Second factor is a 6-digit WhatsApp OTP (5-min expiry, limited attempts & resends).

### Admin

```
Username / Email  →  Password  →  Admin Dashboard
```

- No OTP.
- Permission-based access (see the matrix below).
- **Cannot** create or modify a Super Admin.
- **Cannot** change security settings unless explicitly granted `settings.edit`.

---

## 2. Database tables

Created by `admin/database/admin_rbac_schema.sql` (idempotent):

| Table                    | Purpose                                                                        |
| ------------------------ | ------------------------------------------------------------------------------ |
| `admin_users`            | Administrator identities (username, email, password_hash, role, status, phone) |
| `admin_roles`            | `SUPER_ADMIN`, `ADMIN` (extensible)                                            |
| `admin_permissions`      | One row per `module.action` permission                                         |
| `admin_role_permissions` | Role ↔ permission matrix                                                       |
| `admin_sessions`         | Active admin sessions (ip, ua, csrf, expiry)                                   |
| `admin_otp`              | OTP records (hashed code, expiry, attempts)                                    |
| `admin_login_attempts`   | Login/OTP attempt history (rate limiting)                                      |
| `admin_audit_logs`       | Full audit trail of admin actions                                              |

### Install

```bash
php admin/database/install_admin_rbac.php
```

Default Super Admin: **`superadmin` / `ChangeMe@123`** → change immediately.
Demo Admin: **`demo.admin` / `DemoAdmin@123`** (delete in production).

---

## 3. Permission matrix

Each permission is `module.action` where action ∈
`view · create · edit · delete · approve · export`.

Modules: `dashboard, products, categories, orders, payments, customers,
vendors, pharmacy, doctors, appointments, prescriptions, delivery,
reports, settings, notifications, audit`.

- **SUPER_ADMIN** → `*` (full system access, cannot be restricted).
- **ADMIN** → the operational set (dashboard, products, categories, orders,
  payments, customers, pharmacy, doctors, appointments, prescriptions,
  delivery, reports). Editable per role from _Admin Management → Roles & Permissions_.

---

## 4. Files

| File                                               | Role                                                      |
| -------------------------------------------------- | --------------------------------------------------------- |
| `admin/database/admin_rbac_schema.sql`             | Schema + seed                                             |
| `admin/database/install_admin_rbac.php`            | Installer (CLI + web)                                     |
| `admin/inc/admin_auth.php`                         | Core auth: sessions, CSRF, rate-limit, permissions, audit |
| `admin/inc/admin_otp.php`                          | WhatsApp OTP lifecycle                                    |
| `admin/inc/whatsapp/WhatsAppProviderInterface.php` | Provider contract                                         |
| `admin/inc/whatsapp/LogWhatsAppProvider.php`       | Dev provider (logs to `logs/whatsapp_otp.log`)            |
| `admin/inc/whatsapp/CloudApiWhatsAppProvider.php`  | Meta WhatsApp Cloud API provider                          |
| `admin/inc/whatsapp/WhatsAppFactory.php`           | Selects provider from `settings`                          |
| `admin/inc/auth_check.php`                         | Page gatekeeper (auth + permission)                       |
| `admin/login.php`                                  | Two-stage login UI                                        |
| `admin/admins.php`                                 | Admin Management (Super Admin)                            |
| `admin/audit_logs.php`                             | Audit log viewer + CSV export                             |
| `admin/inc/sidebar.php`                            | Navigation (Admin Management link added)                  |

---

## 5. Security features

- `password_hash()` / `password_verify()` throughout.
- Secure sessions + `session_regenerate_id()` on login (fixation defence).
- Session idle timeout (2h) + client fingerprint binding.
- CSRF tokens on every state-changing form (`hash_equals`).
- Login rate limiting (5 fails / 15 min per IP).
- Per-account lock after repeated failures.
- OTP: 5-min expiry, 5 attempts, 45s resend cooldown, max 3 resends, hashed storage.
- Server-side role & permission checks on every page and action.
- Prepared PDO statements (emulated prepares disabled) + output escaping (`e()`).
- Every admin mutation is written to `admin_audit_logs`.

---

## 6. WhatsApp provider abstraction

The auth system only talks to `WhatsAppProviderInterface`, so the real
WhatsApp API plugs in without touching the OTP flow.

Configure at **Settings → Integrations**:

- `whatsapp_provider` = `log` (dev) or `cloud_api` (Meta Cloud API)
- `whatsapp_phone_number_id`, `whatsapp_access_token`, `whatsapp_api_version`

The factory reads these from the `settings` table and falls back to the
log provider (writing to `logs/whatsapp_otp.log`, and surfacing the code
in the login screen in DEV mode) when nothing is configured.

To add another vendor (e.g. Twilio), implement `WhatsAppProviderInterface`
and return it from `WhatsAppFactory::make()` — nothing else changes.

---

## 7. To extend with new permission-based pages

```php
$currentPage        = 'products';
$requiredPermission = 'products.view';
require_once __DIR__ . '/inc/auth_check.php';
// ...page code...
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !current_admin_can('products.edit')) {
    http_response_code(403); exit('Forbidden');
}
```
