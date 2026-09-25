# PUP e-DocuServe — System Overview

**Polytechnic University of the Philippines — Biñan Campus**
Online Document Request System

> Last verified against live codebase: **September 25, 2026**
> Deployment status: **Local WAMP (dev) + Railway audit active (Sprint 4)** — Brevo email live; Dockerfile/Procfile/nixpacks audited; file uploads at risk on Railway

---

## 1. What the System Does

PUP e-DocuServe is a web-based document request management system for PUP Biñan Campus students and graduates. It replaces manual, in-person-only document requests by allowing students to:

- Register and create a verified student account
- Browse and select from 21 document types (transcripts, certifications, CAV, etc.)
- Choose a payment method and submit a request online
- Upload bank payment slips as proof of payment
- Track request status in real time and receive email notifications at every stage
- Claim their documents at the Registrar's Office once ready

The Registrar's staff manage the entire lifecycle — verifying students, processing payments, updating statuses, and controlling the document catalog — from a dedicated admin panel.

---

## 2. System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Public Internet                       │
│                 (Railway hosting — planned)                  │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS (planned)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   Web Server / PHP 8.3                       │
│                       index.php                              │
│              (routes unauthenticated access)                 │
└──────┬──────────────────┬───────────────────┬───────────────┘
       │                  │                   │
       ▼                  ▼                   ▼
  auth/login.php    Student session      Admin / SuperAdmin
  auth/register.php  └─ student/         session
  auth/forgot_*          index.php       └─ admin/
  auth/reset_*           new_request.php     index.php
  auth/logout.php        requests.php        manage_requests.php
                         feedback.php        walkin_requests.php
                         edit_profile.php    students.php
                         account_settings    account_settings.php
                         walkin_payment.php
                                         └─ SuperAdmin/
                                             index.php
                                             manage_requests.php
                                             walkin_requests.php
                                             students.php
                                             manage_admins.php
                                             manage_documents.php
                                             announcements.php
                                             reports.php
                                             export.php
                                             system_logs.php
                                             system_settings.php
                                             smtp_test.php
                                             account_settings.php
       │                  │                   │
       └──────────────────┼───────────────────┘
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   Shared Infrastructure                      │
│  config/db.php           includes/mailer.php                │
│  includes/mailer.php (Brevo)  includes/release_date.php    │
│  includes/tcpdf/         admin/csrf.php                     │
│  student/csrf.php        SuperAdmin/csrf.php                │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              MySQL Database — edocuserve                     │
│  users · admins · requests · request_items · documents      │
│  feedback · notifications · announcements                   │
│  system_settings · system_logs                              │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              Email Delivery — PHPMailer + SMTP               │
│  Current: unconfigured (silent fail mode)                    │
│  Planned: Brevo (smtp-relay.brevo.com, port 587, TLS)       │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Technology Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.3 |
| Database | MySQL 9.1 (via PDO, `utf8mb4`) |
| Frontend | Bootstrap 5.3 (CDN, SRI-hashed), Font Awesome 6.4 (CDN), Chart.js 4.4 (CDN, reports only) |
| Email | Brevo HTTPS API (`curl` to `api.brevo.com`) — `includes/phpmailer/` deleted; `includes/mailer.php` updated |
| PDF generation | TCPDF 6.x (local vendor copy in `includes/tcpdf/`) |
| Dev environment | WAMP (Windows, Apache, MySQL, PHP) — local; Railway Docker build audited |
| Planned hosting | Railway (Hobby plan) — PHP + MySQL |
| Current email provider | Brevo transactional API (key in `system_settings`) |

---

## 4. User Roles

### 4.1 Student
Anyone who registers with a valid PUP Biñan student number. Accounts start as `Pending Verification` and must be approved by an admin before full access.

**Can:**
- Register and log in
- View and edit their own profile + educational information
- Upload a profile photo
- Browse the document catalog and submit document requests
- Choose payment method (Walk-in Cashier or Walk-in Bank Slip)
- Upload a bank payment slip as proof of payment
- Cancel their own request if it is still unpaid
- Track their request status and view admin notes
- Change their password
- Submit system feedback (once per account)

**Cannot:** Access any admin or superadmin pages.

---

### 4.2 Admin (Registrar Staff)
Staff accounts created by the Super Admin. Login is shared with the same `auth/login.php` endpoint; routing by `$_SESSION['admin_role']`.

**Can:**
- View dashboard with request and payment summary stats
- Manage all student requests: update payment status, request status, tentative release date, admin notes, custom requirements
- View uploaded bank slips and confirm walk-in payments
- Search and filter requests by status
- Approve or reject pending student account registrations
- Generate reports (CSV/PDF)
- Change their own profile photo and password

**Cannot:** Create admin accounts, manage documents, manage announcements, view system logs, or access system settings.

---

### 4.3 Super Admin (System Owner)
One elevated account (`superadmin@pup-binan.edu.ph`) with full platform control.

**Can do everything an Admin can, plus:**
- Create, activate, deactivate, and reset passwords for admin accounts
- Manage the document catalog (add, edit, activate/deactivate documents)
- Post and delete system announcements (shown on landing page and student dashboard)
- Configure all system settings: school year, office hours, bank details, maintenance mode
- Configure SMTP email settings (host, port, credentials, encryption) with live test
- Enable maintenance mode (blocks student login; admins unaffected)
- View date-filtered reports with charts: daily trend, requests by status, most-requested documents, requests by purpose, requests by course
- Export requests and students as CSV or PDF (with custom letterhead header/footer)
- View the full system audit log

---

## 5. Database Schema

### `users`
Stores student/graduate accounts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `student_number` | VARCHAR(20) UNIQUE | Format: `YYYY-XXXXX-BN-0` |
| `last_name`, `first_name`, `middle_name`, `suffix` | VARCHAR | Name as enrolled at PUP |
| `email` | VARCHAR(150) UNIQUE | Login credential |
| `password` | VARCHAR(255) | bcrypt hash |
| `reset_token`, `reset_token_expires` | VARCHAR / DATETIME | Forgot-password flow |
| `date_of_birth`, `address`, `mobile_number`, `phone_number` | Various | Personal info |
| `course`, `year_admitted`, `admitted_as`, `program_status` | Various | Educational info |
| `high_school`, `hs_year_grad`, `elementary`, `elem_year_grad` | Various | Previous schools |
| `profile_photo` | VARCHAR(255) | Filename in `assets/uploads/` |
| `status` | ENUM(`Active`, `Inactive`) | Account active flag |
| `verification_status` | ENUM(`Pending Verification`, `Active`, `Rejected`) | Admin approval state |
| `created_at`, `updated_at` | TIMESTAMP | |

### `admins`
Stores admin and superadmin accounts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `name` | VARCHAR(200) | Full name |
| `email` | VARCHAR(150) UNIQUE | Must end in `@pup.edu.ph` (enforced in UI) |
| `password` | VARCHAR(255) | bcrypt hash |
| `profile_photo` | VARCHAR(255) | Filename in `assets/uploads/` |
| `role` | ENUM(`admin`, `superadmin`) | Determines dashboard routing |
| `status` | ENUM(`Active`, `Inactive`) | Inactive blocks login |
| `created_at`, `updated_at` | TIMESTAMP | |

### `requests`
One row per document request submission.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `control_number` | VARCHAR(30) UNIQUE | Format: `YYYYMMDD-XXXX` |
| `user_id` | INT FK → `users.id` | |
| `purpose` | VARCHAR(200) | Selected + optional free-text |
| `payment_method` | ENUM(`Walk-in (Cashier)`, `Walk-in (Bank Slip)`) | |
| `total_amount` | DECIMAL(10,2) | Sum of `request_items.subtotal` |
| `documentary_stamp` | DECIMAL(10,2) | Legacy field — currently 0 |
| `bank_slip_path` | VARCHAR(255) | Filename or `'walkin'` literal |
| `payment_status` | ENUM(`Unpaid`, `Pending Verification`, `Paid`) | |
| `request_status` | ENUM(`Pending`, `Processing`, `Ready for Pickup`, `Claimed`, `Cancelled`) | |
| `tentative_release_date` | DATE | PH holiday-aware working-day calculation |
| `admin_notes` | TEXT | Visible to student in View Details |
| `custom_requirements` | TEXT | Visible to student in View Details |
| `processed_by` | INT FK → `admins.id` | Nullable |
| `date_filed`, `date_verified`, `date_released`, `updated_at` | TIMESTAMP / DATE | |

### `request_items`
Line items for each request (supports multiple documents per request).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `request_id` | INT FK → `requests.id` | |
| `document_id` | INT FK → `documents.id` | |
| `quantity` | INT | Default 1 |
| `unit_price` | DECIMAL(10,2) | Snapshot at time of request |
| `subtotal` | DECIMAL(10,2) | `unit_price × quantity` |

### `documents`
The document catalog managed by the Super Admin.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `document_name` | VARCHAR(200) | |
| `category` | ENUM(`Transcript of Records`, `Certifications`, `Unclaimed`, `CAV`, `Others`) | |
| `price` | DECIMAL(10,2) | |
| `processing_days` | INT | Legacy single value |
| `processing_min_days`, `processing_max_days` | INT | Used for release date + display |
| `description` | TEXT | Optional |
| `is_active` | TINYINT(1) | `0` = hidden from catalog |

### `feedback`
Post-request student satisfaction survey.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `user_id` | INT UNIQUE | One submission per student enforced by unique key |
| `q1`–`q10` | TINYINT | 1–5 scale ratings for 10 questions |
| `suggestions` | TEXT | Open-ended |
| `submitted_at` | TIMESTAMP | |

### `announcements`
System announcements posted by Super Admin, shown on landing page and student dashboard.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AUTO | |
| `title`, `content` | VARCHAR / TEXT | |
| `is_active` | TINYINT(1) | Toggle visibility without deleting |
| `created_by` | VARCHAR(100) | Superadmin name string |
| `created_at`, `updated_at` | TIMESTAMP | |

### `system_settings`
Key-value configuration store used by the entire system.

| Key | Current Value | Purpose |
|-----|--------------|---------|
| `school_year` | `2025-2026` | Displayed in UI |
| `office_hours` | `Mon–Fri 8AM–5PM` | Displayed in UI |
| `office_contact`, `office_email` | Contact details | Displayed in UI |
| `processing_notice` | Advisory text | Shown on New Request page |
| `bank_name`, `bank_account_name`, `bank_account_number` | LBP details | Displayed to students |
| `maintenance_mode` | `0` / `1` | Blocks student login when `1` |
| `pdf_header_image`, `pdf_footer_image` | Filenames | Letterhead for PDF exports |
| `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure`, `smtp_from_name`, `smtp_from_email` | Currently empty | SMTP config pulled by `mailer.php` — Brevo values go here |

### `system_logs`
Audit trail of all Super Admin (and Admin) mutations.

| Column | Notes |
|--------|-------|
| `action` | Description string (e.g., "Added new admin account") |
| `performed_by`, `performed_by_id`, `performed_by_role` | Who did it |
| `target` | What was affected (e.g., "Admin ID 3") |
| `details` | Optional extra context |
| `created_at` | Timestamp |

### `notifications`
⚠️ **Dead table** — exists in the schema but is never read from or written to by any PHP file. All email notifications are fired directly from `manage_requests.php` via `sendMail()`. Reserved for future notification tracking.

---

## 6. Core Workflows

### Student Registration & Approval
1. Student fills `auth/register.php` (validated: student number format, duplicate checks, password rules, 11-digit mobile).
2. Account inserted with `verification_status = 'Pending Verification'`.
3. Student can log in immediately but sees a pending-verification notice.
4. Admin or Super Admin opens `students.php`, reviews the account, clicks Approve or Reject.
5. On approval: `verification_status` set to `Active`; `emailAccountApproved()` sent via Brevo.
6. On rejection: `verification_status` set to `Rejected`; `emailAccountRejected()` sent.
7. Rejected accounts are blocked at login with a specific error message.

### Document Request Submission
1. Verified student visits `student/new_request.php`.
2. Selects documents (checkboxes + quantity); JS calculates running total live.
3. Selects payment method: Walk-in (Cashier) or Walk-in (Bank Slip).
4. Selects purpose; optionally types free-text for "Others".
5. Clicks Submit — Order Summary confirmation modal appears (JS-populated: items, total, payment method, estimated release date).
6. Confirms → POST handler: generates `YYYYMMDD-XXXX` control number, calls `calculateReleaseDate()` (PH holiday-aware), inserts `requests` row + `request_items` rows.
7. If first-time student: redirected to `student/feedback.php`; otherwise shows success screen.

### Payment Verification
**Bank Slip path:**
1. Student uploads a JPG bank slip via `student/requests.php` modal (extension + MIME + `finfo` triple-check; 10 MB limit).
2. `payment_status` set to `Pending Verification`; file saved as `slip_{id}_{timestamp}.jpg`.
3. Admin opens `admin/manage_requests.php`, views the slip, sets `payment_status = 'Paid'`.
4. Student sees updated status.

**Walk-in (Cashier) path:**
1. Student selects "Walk-in (Cashier)" — `bank_slip_path` stored as literal `'walkin'`.
2. Student pays physically at the cashier.
3. Admin opens `admin/walkin_requests.php`, confirms payment → `payment_status = 'Paid'`.

### Request Status Updates & Email Notifications
1. Admin (or Super Admin) opens the Manage Request modal.
2. Changes `request_status` (Pending → Processing → Ready for Pickup → Claimed / Cancelled).
3. On save: if `request_status` changed, `sendMail()` fires with `emailRequestStatusUpdate()` template.
4. Email shows: control number, new status badge, documents, admin notes, custom requirements, release date, and contextual callout block (green "ready for pickup" box or red "cancelled" box).
5. Student sees the same information in "View Details" on `requests.php`.

### Forgot / Reset Password
1. Student submits email on `auth/forgot_password.php`.
2. Server generates a 64-char random token, stores it with a 1-hour expiry in `users.reset_token`.
3. Same success message regardless of whether the email exists (prevents enumeration).
4. `emailPasswordReset()` sent with a signed reset URL.
5. Student clicks link → `auth/reset_password.php` validates token + expiry, updates bcrypt hash, clears token.

### Release Date Calculation
`includes/release_date.php` — `calculateReleaseDate(int $days)`:
- Counts N working days forward from today.
- Skips Saturdays, Sundays, and Philippine public holidays (fixed + moveable: Easter-derived Maundy Thursday, Good Friday, Black Saturday).
- Applies an extra forward-shift if the result itself lands on a non-working day.

---

## 7. Email System

All outgoing email goes through `includes/mailer.php` → `sendMail()` via Brevo HTTPS API (`curl` to `https://api.brevo.com/v3/smtp/email`). `includes/phpmailer/` deleted; SMTP blocked on Railway.

**Templates implemented:**

| Function | Trigger | Subject line |
|----------|---------|-------------|
| `emailAccountApproved()` | Admin approves student account | "Account Approved — PUP e-DocuServe" |
| `emailAccountRejected()` | Admin rejects student account | "Account Registration Update — PUP e-DocuServe" |
| `emailRequestStatusUpdate()` | Admin changes request status | "Your Request {control_number} has been updated" |
| `emailPasswordReset()` | Student requests password reset | "Reset Your PUP e-DocuServe Password" |

**Current state:** `smtp_host` and `smtp_user` in `system_settings` are empty — emails are silently skipped.

**Planned:** Configure Brevo (`smtp-relay.brevo.com`, port 587, TLS) via SuperAdmin → System Settings. No code change needed — the SMTP settings form already persists to the DB.

---

## 8. Security

### What is already in place
| Control | Coverage |
|---------|---------|
| bcrypt password hashing (`PASSWORD_BCRYPT`) | All user and admin passwords |
| Session-based authentication | All protected pages |
| Role-based access control | Student vs. admin vs. superadmin routing enforced server-side |
| Session timeout (10 min inactivity) | Student portal only — `new_request.php`, `requests.php`, `index.php`, `account_settings.php`, `edit_profile.php` |
| CSRF protection (`csrf_verify()`) | `student/requests.php`, `student/account_settings.php`, `admin/account_settings.php`, `SuperAdmin/account_settings.php` |
| File upload validation (extension + MIME + `finfo` content check) | Bank slip upload, profile photo upload, PDF template upload |
| Input sanitization (`htmlspecialchars()`, `filter_var()`, `intval()`) | All user-facing output and DB inputs |
| Parameterized queries (PDO prepared statements) | All DB reads and writes |
| SRI hashes on CDN resources | Bootstrap, Font Awesome |
| `X-Content-Type-Options: nosniff` | `.htaccess` |
| Anti-enumeration on forgot-password | Same response regardless of email existence |
| Admin email domain enforcement | New admin accounts must end in `@pup.edu.ph` |

### Production-readiness audit (Sept 25, 2026)
| Issue | Status | File / Line |
|-------|--------|-------------|
| `calendar` ext missing → `easter_date()` fatal in loops | AUDITED; needs Dockerfile fix | `includes/release_date.php:43`; loops `admin/manage_requests.php:296`, `superadmin/manage_requests.php:274` |
| `display_errors`/`log_errors` not set globally | AUDITED; needs `php.ini` | `php.ini` (none); `superadmin/export.php:127` only |
| Session cookie `secure`/`samesite` missing | AUDITED; needs `session_set_cookie_params()` | Multiple `session_start()` files |
| File uploads to `assets/uploads/` ephemeral | AUDITED; 5 handlers, 15 files at risk | `admin/account_settings.php:39`, `student/index.php:51`, `student/requests.php:91`, etc. |
| `nixpacks.toml` may override Dockerfile | AUDITED (Railway) | `nixpacks.toml`: provider=["php"]; no installs |
| `Procfile` only loads pdo_mysql/mysqli | AUDITED | `Procfile`: `-d extension=pdo_mysql -d extension=mysqli` |

### Known gaps (to be fixed in Sprint 3)
| Gap | Files affected |
|-----|---------------|
| CSRF not applied to admin POST forms | `admin/manage_requests.php`, `admin/walkin_requests.php`, `SuperAdmin/manage_requests.php`, `SuperAdmin/walkin_requests.php`, `SuperAdmin/manage_admins.php`, `SuperAdmin/manage_documents.php`, `SuperAdmin/announcements.php`, `student/new_request.php`, `SuperAdmin/system_settings.php` |
| No session timeout for admin/superadmin | All `admin/*.php` and `SuperAdmin/*.php` files |
| No rate limiting on login | `auth/login.php` |
| No HTTPS enforcement | `.htaccess` |
| Missing security headers | `.htaccess` (`X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`) |

---

## 9. File Structure

```
edocuserve/
├── index.php                      # Public landing page + announcement display
├── .htaccess                      # HTTP headers (partial — HTTPS redirect not yet added)
├── .gitignore
├── SPRINT_PLAN.md
├── SYSTEM_OVERVIEW.md
│
├── auth/
│   ├── login.php                  # Unified login for all 3 roles
│   ├── register.php               # Student self-registration
│   ├── logout.php
│   ├── forgot_password.php        # Token generation + email
│   └── reset_password.php         # Token validation + password update
│
├── student/
│   ├── index.php                  # Profile view + photo upload
│   ├── edit_profile.php           # Profile edit form
│   ├── account_settings.php       # Password change
│   ├── new_request.php            # Document request submission
│   ├── requests.php               # Request list + slip upload + cancel
│   ├── feedback.php               # Post-request satisfaction survey
│   ├── walkin_payment.php         # Informational: cashier payment instructions
│   └── csrf.php                   # CSRF helper (student scope)
│
├── admin/
│   ├── index.php                  # Admin dashboard
│   ├── manage_requests.php        # Request management + status updates + email
│   ├── walkin_requests.php        # Walk-in payment confirmation
│   ├── students.php               # Student list + account approval
│   ├── account_settings.php       # Admin profile photo + password
│   └── csrf.php                   # CSRF helper (admin scope)
│
├── SuperAdmin/
│   ├── index.php                  # Super Admin dashboard
│   ├── manage_requests.php        # Full request management
│   ├── walkin_requests.php        # Walk-in payment confirmation
│   ├── students.php               # Student list + approval
│   ├── manage_admins.php          # Create/manage admin accounts
│   ├── manage_documents.php       # Document catalog management
│   ├── announcements.php          # Post/delete announcements
│   ├── reports.php                # Charts + data reports
│   ├── export.php                 # CSV + PDF data export
│   ├── system_logs.php            # Audit log viewer
│   ├── system_settings.php        # All system + SMTP config
│   ├── smtp_test.php              # Live SMTP connection test (AJAX)
│   ├── account_settings.php       # SuperAdmin profile photo + password
│   ├── sidebar.php                # Shared sidebar partial
│   └── csrf.php                   # CSRF helper (SuperAdmin scope)
│
├── config/
│   ├── db.php                     # PDO connection (WAMP local; env-var refactor planned)
│   └── db.example.php             # Credential template for collaborators
│
├── includes/
│   ├── mailer.php                 # sendMail() + all email templates
│   ├── release_date.php           # PH holiday-aware working-day calculator
│   ├── phpmailer/                 # PHPMailer vendor library
│   │   ├── PHPMailer.php
│   │   ├── SMTP.php
│   │   └── Exception.php
│   └── tcpdf/                     # TCPDF vendor library (PDF generation)
│       ├── tcpdf.php              # Entry point
│       ├── config/tcpdf_config.php
│       └── ...
│
├── assets/
│   ├── images/
│   │   └── pup-logo.png
│   └── uploads/                   # User-uploaded files (profile photos, bank slips, PDF templates)
│
└── database/
    └── edocuserve.sql             # Full schema + seed data
```

---

## 10. Deployment Plan (Planned — Sprint 3 & 4)

### Phase 1 — Infrastructure Prep (Sprint 3)

**`config/db.php` refactor:**
```php
// Planned — reads from environment variables with WAMP fallback
$host   = $_ENV['DB_HOST']   ?? 'localhost';
$dbname = $_ENV['DB_NAME']   ?? 'edocuserve';
$user   = $_ENV['DB_USER']   ?? 'root';
$pass   = $_ENV['DB_PASS']   ?? '';
```

**Brevo SMTP configuration:**
No code change needed. Go to SuperAdmin → System Settings → Email Notifications (SMTP) and enter:
- SMTP Host: `smtp-relay.brevo.com`
- Port: `587`
- Encryption: `TLS`
- Username: Brevo account email
- Password: Brevo SMTP key
- From Name: `PUP e-DocuServe`
- From Email: `noreply@pup.edu.ph` (or verified sender on Brevo)

Click "Test" to verify the connection before saving.

### Phase 2 — Railway Deployment (Sprint 4) — AUDITED SEPT 25, 2026

**Pre-deploy blockers (verified):** `calendar` ext missing → fatal loops; `gd` needed for TCPDF; `nixpacks.toml` overrides Dockerfile; `Procfile` too narrow; `php.ini` not loaded; uploads ephemeral; no session cookie settings; no error visibility globally. Fixes required before deploy.

### Phase 2 — Railway Deployment (Sprint 4)

1. Create a Railway project and provision a MySQL plugin.
2. Import `database/edocuserve.sql` to the Railway database.
3. Set environment variables in Railway dashboard: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. Deploy the PHP application (Railway uses Nixpacks to auto-detect PHP).
5. Configure the custom domain or use the Railway-generated `.railway.app` subdomain.
6. Add HTTPS force-redirect in `.htaccess`:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} !=on
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
7. Smoke-test the full flow end-to-end (register → approve → request → email → status update).

> ⚠️ **Note on file uploads:** Railway's filesystem may be ephemeral (files lost on redeploy). `assets/uploads/` stores profile photos, bank slips, and PDF templates. Before launch, confirm whether Railway persists uploads between deployments or plan to migrate uploaded files to an object storage service (e.g., S3-compatible bucket via AWS, Cloudflare R2, or similar). This requires code changes to `mailer.php` and upload handlers.

---

## 11. Known Limitations & Planned Improvements

| Item | Current State | Planned Fix | Sprint |
|------|--------------|-------------|--------|
| CSRF on admin/superadmin POST forms | 8 endpoints unprotected | Add `csrf_field()` + `csrf_verify()` to all | Sprint 3 |
| Session timeout for admin/superadmin | No timeout at all | 30-min `last_activity` rolling check | Sprint 3 |
| Login rate limiting | None | IP-based attempt counter + lockout after 5 failures | Sprint 3 |
| HTTPS enforcement | No redirect rule | `.htaccess` `RewriteRule` | Sprint 3 |
| Security headers | `nosniff` only | Add `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy` | Sprint 3 |
| Database config | Hardcoded `localhost`/`root` | Env-var refactor (`DB_HOST`, `DB_NAME`, etc.) | Sprint 3 |
| Brevo email | Unconfigured (silent fail) | Configure via System Settings | Sprint 3 |
| Railway deployment | Local WAMP only | Deploy to Railway | Sprint 4 |
| Privacy Policy page | `href="#"` placeholder | Create `privacy-policy.php` | Sprint 4 |
| Terms of Use page | `href="#"` placeholder | Create `terms.php` | Sprint 4 |
| Meta descriptions | None on any page | Add `<meta name="description">` to public pages | Sprint 4 |
| Open Graph tags | None | Add `og:*` tags to `index.php` and auth pages | Sprint 4 |
| Favicon on landing page | Missing on `index.php` | Add `<link rel="icon">` | Sprint 4 |
| Sitemap + robots.txt | Neither exists | Create both files | Sprint 4 |
| Custom 404 page | None | Create `404.php`, add `ErrorDocument` | Sprint 4 |
| Mobile admin sidebar | Fixed 220–230 px, no hamburger | Add responsive toggle | Sprint 4 |
| Analytics | None | Integrate GA4 or Plausible | Sprint 4 |
| Cookie consent | None | Add NPC-compliant banner | Sprint 4 |
| `notifications` table | Exists but unused | Future: wire up notification tracking if needed | Backlog |
| `documentary_stamp` column | Exists, always 0 | Future: implement stamp logic or remove column | Backlog |

---

*Document generated: September 23, 2026*
*Verified against live codebase at `c:\wamp64\www\edocuserve\`*
