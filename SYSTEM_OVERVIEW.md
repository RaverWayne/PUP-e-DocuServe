# PUP e-DocuServe — System Overview

**Polytechnic University of the Philippines — Biñan Campus**
Online Document Request System

> Last verified against live codebase: **November 26, 2026**
> Deployment status: **Local WAMP (dev) + Railway production active** — Brevo email live; Cloudinary file storage active; HTTPS enforced

---

## 1. What the System Does

PUP e-DocuServe is a web-based document request management system for PUP Biñan Campus students and graduates. It replaces manual, in-person-only document requests by allowing students to:

- Register and create a verified student account (email verification required before admin review)
- Browse and select from document types (transcripts, certifications, CAV, etc.)
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
│                   (Railway hosting — LIVE)                   │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS (enforced via .htaccess)
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
  auth/verify_email.php  requests.php        manage_requests.php
  auth/logout.php        feedback.php        students.php
                         edit_profile.php    account_settings.php
                         account_settings.php notification_settings.php
                                         └─ superadmin/
                                             index.php
                                             manage_requests.php
                                             students.php
                                             manage_admins.php
                                             manage_documents.php
                                             announcements.php
                                             reports.php
                                             export.php
                                             system_logs.php
                                             system_settings.php
                                             notification_settings.php
                                             brevo_test.php
                                             account_settings.php
       │                  │                   │
       └──────────────────┼───────────────────┘
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   Shared Infrastructure                      │
│  config/db.php              includes/mailer.php (Brevo)     │
│  includes/release_date.php  includes/session_timeout.php    │
│  includes/cloudinary.php    includes/admin_topbar.php       │
│  includes/profile_url.php   includes/slip_url.php           │
│  includes/get_profile_url.php  includes/get_slip_url.php    │
│  includes/legal_modals.php  includes/tcpdf/                 │
│  admin/csrf.php  student/csrf.php  superadmin/csrf.php      │
│  auth/csrf.php                                              │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              MySQL Database — edocuserve                     │
│  users · admins · requests · request_items · documents      │
│  feedback · announcements · system_settings · system_logs   │
│  email_verifications · login_attempts · request_history     │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              Email Delivery — Brevo HTTPS API                │
│  curl → api.brevo.com/v3/smtp/email (port 443)              │
│  Configured: brevo_api_key stored in system_settings        │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│          Cloud File Storage — Cloudinary                     │
│  Profile photos, bank slips, PDF templates                   │
│  assets/uploads/ used only as local fallback                 │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Technology Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.3 |
| Database | MySQL 9.1 (via PDO, `utf8mb4`) |
| Frontend | Bootstrap 5.3 (CDN, SRI-hashed), Font Awesome 6.4 (CDN), Chart.js 4.4 (CDN, reports only) |
| Email | Brevo HTTPS API (`curl` to `api.brevo.com`) — `includes/phpmailer/` deleted; direct SMTP removed |
| File Storage | Cloudinary Programmable Media (profile photos, bank slips, PDF templates) |
| PDF generation | TCPDF 6.x (local vendor copy in `includes/tcpdf/`) |
| Spreadsheet export | PhpSpreadsheet 5.x (via Composer) |
| Dev environment | WAMP (Windows, Apache, MySQL, PHP) — local |
| Production hosting | Railway (Hobby plan) — PHP 8.3-cli Docker build, MySQL plugin |
| Container | Dockerfile (php:8.3-cli base; pdo_mysql, curl, zip, gd extensions installed) |

---

## 4. User Roles

### 4.1 Student
Anyone who registers with a valid PUP Biñan student number. The registration flow now requires email address verification before the account is created and queued for admin review.

**Can:**
- Register, verify their email, and log in
- View and edit their own profile and educational information
- Upload a profile photo
- Browse the public document catalog (`catalog.php`) and submit document requests
- Choose payment method (Walk-in Cashier or Walk-in Bank Slip)
- Upload a bank payment slip as proof of payment
- Cancel their own request if it is still unpaid
- Track their request status and view admin notes
- Change their password
- Submit a satisfaction survey (once per account)

**Cannot:** Access any admin or superadmin pages.

---

### 4.2 Admin (Registrar Staff)
Staff accounts created by the Super Admin. Login shared with `auth/login.php`; routing by `$_SESSION['admin_role']`.

**Can:**
- View dashboard with request and payment summary stats
- Manage all student requests: update payment status, request status, tentative release date, admin notes, custom requirements
- View uploaded bank slips and confirm walk-in payments
- Search and filter requests by status
- Approve, reject, deactivate, reactivate, or delete student accounts; reset student passwords
- Configure notification recipients (which email addresses receive new-request and new-registration alerts)
- Change their own profile photo and password

**Cannot:** Create admin accounts, manage documents, manage announcements, view system logs, or access system settings.

---

### 4.3 Super Admin (System Owner)
One elevated account with full platform control.

**Can do everything an Admin can, plus:**
- Create, activate, deactivate, and reset passwords for admin accounts
- Manage the document catalog (add, edit, activate/deactivate documents; set per-document requirements text)
- Post and delete system announcements (shown on landing page and student dashboard)
- Configure all system settings: school year, landing page tagline/advisory, office hours, bank details, maintenance mode
- Configure Brevo API key and sender identity; test the API connection live (`brevo_test.php`)
- Configure notification recipients and toggle new-request email alerts
- Enable maintenance mode (blocks student login; admins unaffected)
- View date-filtered reports with charts; export requests and students as Excel (.xlsx) or PDF (TCPDF)
- View the full system audit log

---

## 5. Database Schema

The database has **13 tables** as of November 2026.

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
| `date_of_birth`, `address`, `mobile_number` | Various | Personal info |
| `course`, `year_admitted`, `admitted_as`, `program_status` | Various | Educational info |
| `high_school`, `hs_year_grad`, `elementary`, `elem_year_grad` | Various | Previous schools |
| `profile_photo` | VARCHAR(255) | Cloudinary public ID or local filename |
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
| `profile_photo` | VARCHAR(255) | Cloudinary public ID or local filename |
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
| `bank_slip_path` | VARCHAR(255) | Cloudinary public ID, local filename, or `'walkin'` literal |
| `payment_status` | ENUM(`Unpaid`, `Pending Verification`, `Paid`) | |
| `request_status` | ENUM(`Pending`, `Processing`, `Ready for Pickup`, `Claimed`, `Cancelled`) | |
| `tentative_release_date` | DATE | PH holiday-aware working-day calculation |
| `admin_notes` | TEXT | Visible to student in View Details |
| `custom_requirements` | TEXT | Visible to student in View Details |
| `processed_by` | INT FK → `admins.id` | Nullable |
| `admin_name` | VARCHAR(200) | Denormalized name of the last admin who processed |
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
| `processing_days` | INT | Legacy single value (kept for backward compat) |
| `processing_min_days`, `processing_max_days` | INT | Used for release date calculation and display |
| `description` | TEXT | Optional |
| `requirements` | TEXT | Per-document submission requirements shown to students |
| `requires_clearance` | TINYINT(1) | If `1`, student must have clearance before requesting |
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
Key-value configuration store. Selected keys as of November 2026:

| Key | Purpose |
|-----|---------|
| `school_year` | Displayed in UI |
| `office_hours`, `office_contact`, `office_email` | Contact details shown in UI |
| `processing_notice` | Advisory text on New Request page |
| `bank_name`, `bank_account_name`, `bank_account_number` | LBP details shown to students |
| `maintenance_mode` | `0`/`1` — blocks student login when `1` |
| `pdf_header_image`, `pdf_footer_image` | Cloudinary public IDs for PDF letterhead |
| `brevo_api_key` | Brevo transactional API key (replaces smtp_* fields) |
| `smtp_from_name`, `smtp_from_email` | Email sender identity used by `mailer.php` |
| `notify_on_new_request` | `0`/`1` — enables admin email alerts on new requests and registrations |
| `notification_recipients` | Comma/newline-separated list of admin email addresses for alerts |
| `landing_tagline`, `landing_advisory_title`, `landing_advisory_body` | Customizable landing page content |

### `system_logs`
Audit trail of all Admin and Super Admin mutations.

| Column | Notes |
|--------|-------|
| `action` | Description string (e.g., "Added new admin account") |
| `performed_by`, `performed_by_id`, `performed_by_role` | Who did it |
| `target` | What was affected (e.g., "Admin ID 3") |
| `details` | Optional extra context |
| `created_at` | Timestamp |

### `email_verifications`
Temporary table holding pre-registration payloads pending email confirmation.

| Column | Notes |
|--------|-------|
| `token` | 64-char random token (UNIQUE) |
| `student_number` | From the registration form |
| `email` | The address being verified (UNIQUE — prevents duplicate pending verifications) |
| `form_data` | JSON-encoded registration payload including pre-hashed password |
| `expires_at` | 24 hours from token generation |
| `created_at` | Timestamp |

Rows are deleted immediately on use (success or expiry). The actual `users` row is inserted by `auth/verify_email.php` only after the token is validated.

### `login_attempts`
Tracks failed login attempts for rate limiting.

| Column | Notes |
|--------|-------|
| `ip_address` | VARCHAR(45) — supports IPv6 |
| `email` | The email that was attempted |
| `attempted_at` | Datetime |

Indexed on `(ip_address, attempted_at)` and `(email, attempted_at)`. Rows older than 24 hours are purged on each login request. Lockout threshold: 5 failures within 15 minutes → 15-minute lockout (checked independently for IP and for email).

### `request_history`
Audit log of request status changes.

| Column | Notes |
|--------|-------|
| `request_id` | INT FK → `requests.id` |
| `old_status`, `new_status` | VARCHAR(50) |
| `changed_by` | Admin name string |
| `changed_at` | Datetime |
| `notes` | Optional context |

### `notifications`
⚠️ **Dead table** — exists in the schema but is never read from or written to by any PHP file. Admin email alerts fire directly from `mailer.php` via `notifyAdminsNewRequest()` and `notifyAdminsNewRegistration()`. Reserved for future notification tracking.

---

## 6. Core Workflows

### Student Registration & Email Verification & Approval
1. Student fills `auth/register.php` (validated: student number format, duplicate checks, password rules, 11-digit mobile).
2. Form data is NOT yet inserted into `users`. Instead, the payload (including pre-hashed password) is JSON-encoded and stored in `email_verifications` with a 24-hour token.
3. `emailVerifyAddress()` is sent to the student's email via Brevo.
4. Student clicks the verification link → `auth/verify_email.php` validates the token and inserts the row into `users` with `verification_status = 'Pending Verification'`.
5. Admin email notification (`notifyAdminsNewRegistration()`) is fired to all configured `notification_recipients`.
6. Admin or Super Admin opens `students.php`, reviews the account, clicks Approve or Reject.
7. On approval: `verification_status` set to `Active`; `emailAccountApproved()` sent.
8. On rejection: `verification_status` set to `Rejected`; `emailAccountRejected()` sent.

### Document Request Submission
1. Verified student visits `student/new_request.php`.
2. Selects documents (checkboxes + quantity); JS calculates running total live.
3. Selects payment method: Walk-in (Cashier) or Walk-in (Bank Slip).
4. Selects purpose; optionally types free-text for "Others".
5. Clicks Submit — Order Summary confirmation modal appears (JS-populated).
6. Confirms → POST handler: generates `YYYYMMDD-XXXX` control number, calls `calculateReleaseDate()` (PH holiday-aware), inserts `requests` row + `request_items` rows.
7. `notifyAdminsNewRequest()` sends an email summary to all configured `notification_recipients`.
8. If first-time student: redirected to `student/feedback.php`; otherwise shows success screen.

### Payment Verification
**Bank Slip path:**
1. Student uploads a JPG bank slip via `student/requests.php` modal (extension + MIME + `finfo` triple-check).
2. Slip is uploaded to Cloudinary; `bank_slip_path` stores the Cloudinary public ID.
3. `payment_status` set to `Pending Verification`.
4. Admin opens `admin/manage_requests.php`, views the slip (fetched via `includes/get_slip_url.php` → Cloudinary signed URL), sets `payment_status = 'Paid'`.

**Walk-in (Cashier) path:**
1. Student selects "Walk-in (Cashier)" — `bank_slip_path` stored as literal `'walkin'`.
2. Student pays physically at the cashier.
3. Admin opens `admin/manage_requests.php`, confirms payment → `payment_status = 'Paid'`.

### Request Status Updates & Email Notifications
1. Admin (or Super Admin) opens the Manage Request modal in `manage_requests.php`.
2. Changes `request_status` (Pending → Processing → Ready for Pickup → Claimed / Cancelled).
3. On save: if `request_status` changed, `emailRequestStatusUpdate()` fires via `sendMail()`.
4. Email shows: control number, new status badge, documents, admin notes, custom requirements, release date, and contextual callout block.
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
- Applies an extra forward-shift if the result lands on a non-working day.

---

## 7. Email System

All outgoing email goes through `includes/mailer.php` → `sendMail()` via Brevo HTTPS API (`curl` to `https://api.brevo.com/v3/smtp/email`). `includes/phpmailer/` has been deleted. SMTP is not used because Railway's network blocks outbound SMTP ports.

**Configuration:** `brevo_api_key`, `smtp_from_name`, and `smtp_from_email` are read from `system_settings`. If `brevo_api_key` is empty, emails are silently skipped with an `error_log` entry — pages never crash.

**Templates implemented:**

| Function | Trigger | Subject line |
|----------|---------|-------------|
| `emailVerifyAddress()` | Student submits registration form | "Verify Your Email — PUP e-DocuServe" |
| `emailAccountApproved()` | Admin approves student account | "Account Approved — PUP e-DocuServe" |
| `emailAccountRejected()` | Admin rejects student account | "Account Registration Update — PUP e-DocuServe" |
| `emailRequestStatusUpdate()` | Admin changes request status | "Request Status Update — {control_number}" |
| `emailPasswordReset()` | Student requests password reset | "Password Reset — PUP e-DocuServe" |
| `emailAdminNewRequest()` | Student submits a new request | "New Request — {control_number}" |
| `emailAdminNewRegistration()` | Student completes email verification | "New Student Registration — PUP e-DocuServe" |

`notifyAdminsNewRequest()` and `notifyAdminsNewRegistration()` read `notify_on_new_request` and `notification_recipients` from `system_settings` before firing.

---

## 8. Security

### Controls in place
| Control | Coverage |
|---------|---------|
| bcrypt password hashing (`PASSWORD_BCRYPT`) | All user and admin passwords |
| Session-based authentication | All protected pages |
| Role-based access control | Student vs. admin vs. superadmin routing enforced server-side |
| Session timeout (30-minute rolling inactivity) | All admin and superadmin pages via `includes/session_timeout.php` |
| Session timeout (10-minute inactivity) | Student portal pages |
| CSRF protection (`csrf_verify()`) | All POST endpoints across student, admin, and superadmin portals |
| Login rate limiting | 5 failures / 15-minute window → 15-min lockout (checked per-IP and per-email via `login_attempts` table) |
| Email verification before account creation | `email_verifications` table; `auth/verify_email.php` |
| File upload validation (extension + MIME + `finfo` content check) | Bank slip upload, profile photo upload, PDF template upload |
| Input sanitization (`htmlspecialchars()`, `filter_var()`, `intval()`) | All user-facing output and DB inputs |
| Parameterized queries (PDO prepared statements) | All DB reads and writes |
| SRI hashes on CDN resources | Bootstrap, Font Awesome |
| HTTPS enforcement | `.htaccess` RewriteRule (skips localhost / 127.0.0.1) |
| `X-Frame-Options: SAMEORIGIN` | `.htaccess` |
| `X-Content-Type-Options: nosniff` | `.htaccess` |
| `X-XSS-Protection: 1; mode=block` | `.htaccess` |
| `Referrer-Policy: strict-origin-when-cross-origin` | `.htaccess` |
| `Permissions-Policy` (geo, mic, camera denied) | `.htaccess` |
| Anti-enumeration on forgot-password | Same response regardless of email existence |
| Admin email domain enforcement | New admin accounts must end in `@pup.edu.ph` |

### Remaining gaps
| Gap | Files affected |
|-----|---------------|
| CSRF not enforced on `admin/manage_requests.php` POST (request status updates) | `admin/manage_requests.php`, `superadmin/manage_requests.php` — POST is processed but no `csrf_verify()` call |
| `documentary_stamp` column always 0 | `requests` table — pending removal or implementation |
| Mixed storage engines (MyISAM / InnoDB) | `email_verifications`, `users`, `requests`, `request_items` use MyISAM; no DB-level FK enforcement |

---

## 9. File Structure

```
edocuserve/
├── index.php                      # Public landing page + announcement display
├── catalog.php                    # Public read-only document catalog
├── .htaccess                      # Security headers + HTTPS redirect
├── .env                           # Local env vars (gitignored)
├── Dockerfile                     # Railway Docker build (PHP 8.3-cli)
├── php.ini                        # PHP settings applied in Docker CMD
├── composer.json                  # PhpSpreadsheet + Cloudinary PHP SDK
├── README.md
├── SYSTEM_OVERVIEW.md
│
├── auth/
│   ├── login.php                  # Unified login for all 3 roles; rate limiting via login_attempts
│   ├── register.php               # Student self-registration (stores to email_verifications)
│   ├── verify_email.php           # Handles verification link; inserts into users on success
│   ├── logout.php
│   ├── forgot_password.php        # Token generation + email
│   ├── reset_password.php         # Token validation + password update
│   └── csrf.php                   # CSRF helper (auth scope)
│
├── student/
│   ├── index.php                  # Profile view + photo upload
│   ├── edit_profile.php           # Profile edit form
│   ├── account_settings.php       # Password change
│   ├── new_request.php            # Document request submission
│   ├── requests.php               # Request list + slip upload + cancel
│   ├── feedback.php               # Post-request satisfaction survey
│   ├── get_slip_url.php           # AJAX endpoint: Cloudinary signed URL for slip
│   └── csrf.php                   # CSRF helper (student scope)
│
├── admin/
│   ├── index.php                  # Admin dashboard
│   ├── manage_requests.php        # Request management + status updates + email
│   ├── students.php               # Student list + account approval/deactivation
│   ├── account_settings.php       # Admin profile photo + password
│   ├── notification_settings.php  # Notification recipients + toggle
│   ├── sidebar.php                # Shared sidebar partial
│   └── csrf.php                   # CSRF helper (admin scope)
│
├── superadmin/
│   ├── index.php                  # Super Admin dashboard
│   ├── manage_requests.php        # Full request management
│   ├── students.php               # Student list + approval
│   ├── manage_admins.php          # Create/manage admin accounts
│   ├── manage_documents.php       # Document catalog management
│   ├── announcements.php          # Post/delete announcements
│   ├── reports.php                # Charts + data reports
│   ├── export.php                 # Excel (PhpSpreadsheet) + PDF (TCPDF) export
│   ├── system_logs.php            # Audit log viewer
│   ├── system_settings.php        # All system config + landing page content
│   ├── notification_settings.php  # Notification recipients + Brevo sender settings
│   ├── brevo_test.php             # AJAX endpoint: live Brevo API key validation
│   ├── account_settings.php       # SuperAdmin profile photo + password
│   ├── sidebar.php                # Shared sidebar partial
│   └── csrf.php                   # CSRF helper (SuperAdmin scope)
│
├── config/
│   ├── db.php                     # PDO connection — reads DATABASE_URL, MYSQL_*, or .env fallback
│   └── db.example.php             # Credential template for collaborators
│
├── includes/
│   ├── mailer.php                 # sendMail() + all email templates
│   ├── release_date.php           # PH holiday-aware working-day calculator
│   ├── session_timeout.php        # 30-min rolling timeout for admin/superadmin
│   ├── cloudinary.php             # Cloudinary PHP SDK singleton helper
│   ├── profile_url.php            # getProfileUrl() — Cloudinary or local fallback
│   ├── slip_url.php               # getSlipUrl() — Cloudinary or local fallback
│   ├── get_profile_url.php        # AJAX endpoint: signed profile photo URL
│   ├── get_slip_url.php           # AJAX endpoint: signed bank slip URL
│   ├── admin_topbar.php           # Shared admin/superadmin top-right profile menu
│   ├── legal_modals.php           # Privacy/Terms Bootstrap modals
│   └── tcpdf/                     # TCPDF vendor library (PDF generation)
│       ├── tcpdf.php
│       └── config/tcpdf_config.php
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   │   └── pup-logo.png
│   └── uploads/                   # Local fallback only; production uses Cloudinary
│
├── database/
│   ├── edocuserve.sql             # Full 13-table schema + seed data
│   └── edocuserve_backup.sql      # Backup snapshot
│
└── vendor/                        # Composer-managed dependencies (PhpSpreadsheet, Cloudinary SDK)
```

---

## 10. Deployment

### Environment Variables
`config/db.php` resolves database credentials in this order:
1. `DATABASE_URL` or `MYSQL_URL` (full connection string — Railway standard)
2. Individual vars: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
3. `.env` file in project root (loaded manually if present)
4. WAMP defaults (`localhost`, `root`, empty password, `edocuserve`)

| Variable | Required for Railway | Description |
|:---|:---:|:---|
| `DATABASE_URL` or `MYSQL_URL` | Yes | Full MySQL connection string |
| `CLOUDINARY_CLOUD_NAME` | Yes | Cloudinary cloud name |
| `CLOUDINARY_API_KEY` | Yes | Cloudinary API key |
| `CLOUDINARY_API_SECRET` | Yes | Cloudinary API secret |

Brevo API key and all other app settings are stored in `system_settings` and managed through the SuperAdmin panel — no environment variables needed for email.

### Dockerfile
Built from `php:8.3-cli`. Installs: `pdo_mysql`, `mysqli`, `curl`, `zip`, `gd` (with JPEG). Runs `composer install --no-dev --optimize-autoloader`. Starts the PHP built-in server on `${PORT:-8080}` with `php.ini` applied.

---

## 11. Known Limitations & Remaining Work

| Item | Current State | Notes |
|------|--------------|-------|
| CSRF on `manage_requests.php` POST | Not enforced (admin and superadmin) | Form uses modal + JS fetch; CSRF token not passed through the fetch payload |
| Mixed storage engines | MyISAM for several tables | No DB-level foreign key enforcement; migration to full InnoDB is a future task |
| `documentary_stamp` column | Always 0 | Remove or implement in a future sprint |
| `notifications` table | Exists but unused | Wired up to future in-app notification tracking |
| Mobile admin sidebar | No hamburger / responsive toggle | Fixed 220 px sidebar on all screen sizes |
| WCAG accessibility | Not audited | No color contrast or keyboard navigation audit done |
| Privacy Policy / Terms pages | Modal only | No dedicated `/privacy` or `/terms` URL |
| SEO | None | No meta descriptions, Open Graph tags, robots.txt, or sitemap |
| Custom 404 | None | No `ErrorDocument 404` handler |

---

*Document generated: September 23, 2026*
*Verified against live codebase: November 26, 2026*
