# KSR GURU JI — InfinityFree Deployment Guide

**Target:** `ksrguruji.ifree.page` · User `if0_43170510` · Web root `/htdocs/` · Hosting volume `vol9_6`

This guide fixes the **HTTP 413 – Request Entity Too Large** error that occurs when
uploading the whole project ZIP (>100 MB) through the VS Code `ftp-simple` extension.

> **Root cause:** `ftp-simple` uploads the entire ZIP as a single request. InfinityFree's
> web server rejects request bodies above its limit → HTTP 413. The fix is to stop
> uploading the ZIP and instead upload the **extracted contents in small batches**
> using FileZilla, which sends one file per request.

---

## 1. InfinityFree file-size limits (check BEFORE uploading)

The web servers enforce hard per-file limits. **Any file over the limit is silently
auto-deleted after upload.**

| File type              | Max size       |
| ---------------------- | -------------- |
| `.html`, `.php`, `.js` | **1 MB** each  |
| `.htaccess`            | **10 kB** each |
| All other files        | **10 MB** each |

Source: [InfinityFree – Why are my files deleted after uploading them?](https://forum.infinityfree.com/t/why-are-my-files-deleted-after-uploading-them/49310)

Also watch the **inode (file count) limit** — a free account allows roughly 30,000
files. This project stages ~6,336 files, so there is ample headroom.

The deploy script automatically flags any file that breaks these limits. One file in
the original project violated the rule and has been excluded:

- `images/Project_profiles.pdf` — **16.63 MB** (over the 10 MB non-code limit).
  It is a document, not a product asset. If you truly need it on the live site,
  compress/split it first, or host it externally (Google Drive/Dropbox) and link to it.

---

## 2. Prepare the staging + batches (already done)

The script `deploy-infinityfree.ps1` has already produced:

```
deploy\stage\            <- clean production tree (no dev files)
deploy\batches\batch-01\ <- numbered upload batches, ~20 MB each
deploy\batches\batch-02\
...
deploy\batches\batch-13\
```

To regenerate at any time:

```powershell
cd d:\ksr_guru_ji
powershell -ExecutionPolicy Bypass -File .\deploy-infinityfree.ps1
```

**Excluded automatically:** `.git/`, `node_modules/`, `.vscode/`, `.env*`,
`.env.local`, `deploy/`, `*.log`, `*.bak`, `*.tmp`, `*.swp`, backups, and the
oversized `Project_profiles.pdf`.

---

## 3. Install FileZilla

1. Download from <https://filezilla-project.org/> (the **FileZilla Client**, not Server).
2. Run the installer and complete setup.

---

## 4. Get your FTP credentials from InfinityFree

In the InfinityFree control panel:

1. Open your hosting account → **Files** → **FTP Accounts**
   (or the **FTP Details** section on the account overview).
2. Note:
   - **FTP Hostname** — usually `ftpupload.net` or `ftp.<your-domain>`
   - **FTP Username** — `if0_43170510`
   - **FTP Password** — the password shown in the panel
   - **FTP Port** — `21`

---

## 5. Connect FileZilla

| Setting    | Value                                                                                                                                            |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Host       | your FTP hostname from step 4                                                                                                                    |
| Username   | `if0_43170510`                                                                                                                                   |
| Password   | your FTP password                                                                                                                                |
| Port       | `21`                                                                                                                                             |
| Encryption | **Use explicit FTP over TLS if available** (InfinityFree supports TLS). If connection fails, fall back to "Only use plain FTP" — but prefer TLS. |

Click **Quickconnect**. Accept the certificate prompt if shown.

---

## 6. Upload the batches into `/htdocs/`

1. In the **remote** (right) panel, double-click into **`/htdocs/`**.
2. For each batch folder `deploy\batches\batch-01` … `batch-13`:
   - Open the batch folder in the **local** (left) panel.
   - Select **all files and folders inside** the batch (not the batch folder itself).
   - Drag them into `/htdocs/`.
   - When asked to **overwrite/merge**, choose **Overwrite** and **"Always use this
     action"** so folder contents merge correctly across batches.
3. **Verify `index.php` sits directly inside `/htdocs/`** — not inside a subfolder.
   Final path must be `/htdocs/index.php`.

> **Do NOT upload the ZIP.** Only upload the extracted batch contents.

### If a transfer fails

FileZilla's **Failed transfers** tab (bottom) lists anything that didn't complete.
Right-click → **Reset and requeue** to retry. Common causes: connection timeout on
large batches — just retry; the rest stays intact.

---

## 7. Import the database via phpMyAdmin

InfinityFree does **not** allow importing a DB from a PHP script on the free plan;
use the control panel's phpMyAdmin.

1. In the InfinityFree control panel → **MySQL Databases**.
2. Create a database (if not already created). Note the auto-assigned name — it will
   look like `if0_43170510_ksr_guru` (the panel prefixes your account username).
3. Note the **MySQL hostname** — it is **NOT `localhost`**. It looks like
   `sqlXXX.infinityfree.com` (e.g. `sql123.infinityfree.com`).
4. Open **phpMyAdmin** from the MySQL Databases panel.
5. Select your database on the left, then **Import**.
6. Import these SQL files in order (they are in the project root / subfolders):
   - `database.sql` — core e-commerce schema (products, categories, cart, orders)
   - `patient_portal.sql` — patient portal tables
   - `payment_lifecycle_migration.sql` — payment/order status columns
   - `WhatsApp/database/ksr_guru_ji.sql` — WhatsApp + Doctor Assist tables
   - `WhatsApp/telehealth/doctor_assist_schema.sql` — telehealth schema
   - `ai-enterprise/schema.sql` — AI enterprise tables
   - `cleaning_product_image_paths.sql` — image-path cleanup (run after core import)
   - Any seed/data files under `WhatsApp/telehealth/data.sql`
7. If an import fails because of an unknown collation, set the import charset to
   `utf8mb4` and re-run. InfinityFree's MySQL version may not accept every dump option —
   remove `DEFINER=...` clauses if you see privilege errors.

---

## 8. Configure the live database credentials

**Every connection setting has already been switched off localhost/root in the source.**
You now need to put the **real** InfinityFree values into the connection files, or set
them as environment variables in the host's PHP config.

Files to update (search for `sqlXXX.infinityfree.com` and
`YOUR_INFINITYFREE_DB_PASSWORD`):

| File                                      | What to set                                |
| ----------------------------------------- | ------------------------------------------ |
| `config.php`                              | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| `db.php`                                  | same                                       |
| `includes/config.php`                     | same                                       |
| `includes/db.php`                         | same                                       |
| `WhatsApp/config/database.php`            | same                                       |
| `WhatsApp/telehealth/Database.php`        | same (+ `DOCTOR_ASSIST_DB` if separate)    |
| `ai-enterprise/app.php`                   | same + `APP_BASE_URL`, `APP_PARENT_URL`    |
| `WhatsApp/telehealth/medicine/tablet.php` | `DB_HOST/NAME/USER/PASS` constants         |

Replace:

```
DB_HOST  = sqlXXX.infinityfree.com     (exact host from MySQL Databases panel)
DB_NAME  = if0_43170510_ksr_guru       (exact DB name from panel)
DB_USER  = if0_43170510                (your account username)
DB_PASS  = <your real DB password>     (from the panel — keep it private!)
```

> ⚠️ **Security:** The password `JKRathod@406` was previously hardcoded in the source
> and has been exposed in chat. **Change it now** in the InfinityFree MySQL Databases
> panel (there is a "Change Password" action per database). Never commit real
> credentials to the repo — set them only on the host or via environment variables.
> Do **not** use the local MySQL `root` account for the hosted database.

---

## 9. Review `.htaccess`

- Root `.htaccess` — already safe for live hosting. It denies access to dotfiles and
  to `*.sql`, `*.log`, `*.bak`, dev scripts, and internal dumps. No localhost-only
  rules block visitors.
- `WhatsApp/storage/*/.htaccess` — each denies all direct web access to uploaded
  media and PHP/SQL files. These are correct security protections; keep them.
- If you ever add a `Require local` / `Deny from all` without an `IfModule` guard,
  remove it — it would block the live site.

---

## 10. Post-deployment verification checklist

Test each of these on `https://ksrguruji.ifree.page`:

- [ ] **Homepage** loads (`/htdocs/index.php`)
- [ ] **Registration** — new user signup works
- [ ] **Login** — user login works
- [ ] **Product categories** — category pages load products
- [ ] **Cart** — add / update / remove items
- [ ] **Checkout** — order placed and confirmed
- [ ] **Payment status** — payment callback/webhook updates order
- [ ] **Order tracking** — `track-order.php` finds the order
- [ ] **Administration** — admin login + dashboard
- [ ] **Delivery** — delivery panel loads orders
- [ ] **Doctor Assist** — `WhatsApp/telehealth/...` pages load
- [ ] **Telehealth links** — all telehealth navigation works
- [ ] **DB connectivity** — no "Database connection failed" errors; verify via a page
      that queries the DB (e.g. category listing)

> **Do NOT accept real orders or patient information** until every item above passes
> and hosting/database compatibility is confirmed.

---

## 11. Uploading future updates

- For **small changes** (one or a few files), the VS Code `ftp-simple` extension is
  fine — it only 413s on very large single requests.
- For **larger changes**, re-run the deploy script and use FileZilla again, or upload
  only the changed files via FileZilla.
- Keep database credentials **out of the repo** and change any password that has been
  shared in chat.
