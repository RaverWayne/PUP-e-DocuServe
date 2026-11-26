# PUP e-DocuServe (Release 1.1)

A responsive web application for streamlined student document requests at PUP Biñan Campus. Full-stack PHP 8.3 / MySQL solution deployed on Railway with Cloudinary file storage and Brevo transactional email.

**Production URL:** [https://edocuserve.up.railway.app](https://edocuserve.up.railway.app)

---

## System Status & Completion

Status is based on the 10 High-Level BRD Modules from Sprint 2. Percentages reflect verified, deployed functionality as of November 2026.

| Module | BRD Category | Status | % | Notes |
|:---|:---|:---|:-:|:---|
| **01** | **Auth & User Mgmt** | Functional | **95%** | BCrypt login, email verification before account creation, student registration, password reset flows. CSRF + session timeouts on all portals. Rate limiting (5 attempts / 15-min lockout, per-IP and per-email). |
| **02** | **Student Portal** | Functional | **90%** | Public catalog page, search/filter catalog. Multi-item requests with live total. Clearance check. Walk-in/Bank Slip payment flows with Cloudinary slip upload. Status tracking. |
| **03** | **Admin / Registrar** | Functional | **90%** | Payment verification gate. Queue processing, bulk status transitions. SLA column, Admin Name tracking. Full student account lifecycle (approval, deactivation, deletion, password reset). Notification settings. |
| **04** | **SuperAdmin** | Functional | **95%** | Admin account lifecycle. Document catalog CRUD with per-document requirements text. Announcements management. Audit logs. System settings including customizable landing page content. Brevo API key management with live test. Notification recipients. |
| **05** | **Reports & Export** | Functional | **85%** | Chart.js (v4.4) dashboards on Admin/SuperAdmin. Excel (.xlsx via PhpSpreadsheet) and PDF (TCPDF) export. PDF templates stored on Cloudinary. |
| **06** | **Security Hardening** | Functional | **85%** | CSRF on all student, admin, and superadmin POST forms. Session timeout for all roles. Login rate limiting live. HTTPS redirect, security headers (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`) in `.htaccess`. Remaining gap: `manage_requests.php` POST modal does not pass CSRF token. |
| **07** | **Cloud Infra & Mail** | Functional | **95%** | Brevo HTTPS API integrated. Cloudinary for all file uploads. `config/db.php` reads `DATABASE_URL`, `MYSQL_*` env vars, or `.env` file with WAMP fallback. Admin email alerts on new requests and registrations. |
| **08** | **Container & Deploy** | Done | **90%** | Railway production environment live via Dockerfile (PHP 8.3-cli). Database on Railway MySQL. Continuous deployment from `dev` branch. Cloudinary bucket connected. |
| **09** | **Legal & SEO** | In Progress | **30%** | Privacy Policy and Terms of Use modals built. Missing dedicated pages, custom 404, robots.txt, sitemap, and meta tags. |
| **10** | **Responsive & Polish** | Stale | **15%** | Basic mobile compatibility via Bootstrap 5.3 CDN. No mobile sidebar toggle and no WCAG accessibility audit. Acknowledged debt for post-MVP. |
| **Total** | **MVP Release 1.1** | **Deployed** | **~85%** | All three management dashboards and the full student request flow are functional and verified in production. |

---

## Technical Stack

| Layer | Technology |
|:---|:---|
| Runtime | PHP 8.3 (Railway via `php:8.3-cli` Docker image) |
| Database | MySQL 9.1 — 13 tables (mixed InnoDB/MyISAM; DB-level FK migration is a future task) |
| Email | Brevo Transactional HTTPS API (`curl` → `api.brevo.com`). PHPMailer and direct SMTP removed. |
| File Storage | Cloudinary Programmable Media (profile photos, bank slips, PDF templates). `assets/uploads/` is local fallback only. |
| Frontend | Bootstrap 5.3 (CDN, SRI-hashed), Font Awesome 6.4 (CDN), Chart.js 4.4 (CDN, reports only) |
| PDF | TCPDF 6.x (local vendor copy) |
| Spreadsheet | PhpSpreadsheet 5.x (via Composer) |
| Container | Dockerfile — installs `pdo_mysql`, `curl`, `zip`, `gd`; runs `composer install --no-dev` |

---

## Project Structure

```
edocuserve/
├── auth/                   # Auth portal
│   ├── login.php           # Unified login (Student / Admin / SuperAdmin) with rate limiting
│   ├── register.php        # Student self-registration → stores to email_verifications
│   ├── verify_email.php    # Handles verification link; inserts into users on success
│   ├── forgot_password.php
│   ├── reset_password.php
│   └── csrf.php
├── student/                # Student portal
│   ├── new_request.php     # Document request submission
│   ├── requests.php        # Request list + Cloudinary slip upload + cancel
│   ├── feedback.php        # Satisfaction survey
│   ├── get_slip_url.php    # AJAX: Cloudinary signed URL for slip
│   └── csrf.php
├── admin/                  # Registrar panel
│   ├── manage_requests.php # Request queue + status updates + email
│   ├── students.php        # Student list + account lifecycle
│   ├── notification_settings.php
│   ├── sidebar.php
│   └── csrf.php
├── superadmin/             # SuperAdmin panel
│   ├── manage_admins.php
│   ├── manage_documents.php
│   ├── announcements.php
│   ├── reports.php
│   ├── export.php          # Excel + PDF export
│   ├── system_logs.php
│   ├── system_settings.php # All system config + landing page content
│   ├── notification_settings.php
│   ├── brevo_test.php      # AJAX: live Brevo API key test
│   ├── sidebar.php
│   └── csrf.php
├── includes/
│   ├── mailer.php          # sendMail() + 7 email templates
│   ├── cloudinary.php      # Cloudinary SDK singleton
│   ├── profile_url.php     # getProfileUrl() helper
│   ├── slip_url.php        # getSlipUrl() helper
│   ├── get_profile_url.php # AJAX: signed profile photo URL
│   ├── get_slip_url.php    # AJAX: signed bank slip URL
│   ├── admin_topbar.php    # Shared admin/superadmin top-right profile menu
│   ├── session_timeout.php # 30-min rolling timeout (admin/superadmin)
│   ├── release_date.php    # PH holiday-aware working-day calculator
│   ├── legal_modals.php    # Privacy/Terms Bootstrap modals
│   └── tcpdf/              # TCPDF vendor library
├── config/
│   ├── db.php              # PDO connection (DATABASE_URL → MYSQL_* vars → .env → WAMP)
│   └── db.example.php
├── database/
│   └── edocuserve.sql      # 13-table schema + seed data
├── catalog.php             # Public read-only document catalog (no auth)
├── index.php               # Public landing page
├── Dockerfile              # Railway Docker build (PHP 8.3-cli)
├── php.ini                 # PHP settings applied in Docker CMD
└── composer.json           # PhpSpreadsheet + Cloudinary PHP SDK
```

---

## Environment Variables

`config/db.php` resolves database credentials in this order: `DATABASE_URL` / `MYSQL_URL` (full connection string) → individual `MYSQLHOST` / `MYSQLPORT` / `MYSQLDATABASE` / `MYSQLUSER` / `MYSQLPASSWORD` vars → `.env` file → WAMP defaults.

| Variable | Required | Description |
|:---|:---:|:---|
| `DATABASE_URL` or `MYSQL_URL` | Yes* | Full MySQL connection string (Railway injects automatically) |
| `CLOUDINARY_CLOUD_NAME` | Yes* | Cloudinary cloud name |
| `CLOUDINARY_API_KEY` | Yes* | Cloudinary API key |
| `CLOUDINARY_API_SECRET` | Yes* | Cloudinary API secret |

*Required in production. Not needed for local WAMP development.

Brevo API key and all other app settings (sender identity, notification recipients, landing page content, etc.) are managed through the SuperAdmin panel and stored in `system_settings` — no environment variable needed for email.

---

## Deployed URLs

- **Production:** [https://edocuserve.up.railway.app](https://edocuserve.up.railway.app) — Public catalog, announcements, and login
- **Login routing:** `/auth/login.php` → redirects by role (Student / Admin / SuperAdmin)
- **WAMP (local dev):** `http://localhost/edocuserve/` — `config/db.php` auto-detects WAMP

---

## Sprint 3 & Post-Launch Roadmap

| Priority | Item |
|:---|:---|
| Security | Enforce CSRF on `manage_requests.php` POST modal (pass token through JS fetch payload) |
| Security | Migrate all tables to InnoDB with proper FK constraints |
| UX | Mobile sidebar toggle for administration on small screens |
| Legal | Dedicated `/privacy` and `/terms` pages (replace modals) |
| SEO | `robots.txt`, sitemap, `<meta name="description">`, Open Graph tags on public pages |
| SEO | Custom 404 handler (`ErrorDocument 404`) |
| Accessibility | WCAG color contrast and keyboard navigation audit on data tables |
| Performance | Audit TCPDF bulk PDF generation (O(N), no batching optimization) |
| Cleanup | Remove or implement the `documentary_stamp` column (always 0) |
| Future | Wire up the unused `notifications` table for in-app notification tracking |
