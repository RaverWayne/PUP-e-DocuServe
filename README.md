# PUP e-DocuServe (Release 1.1)

A responsive, containerized web application for streamlined student document requests at PUP Biñan Campus. Replaces the core functionality of the previous "PUP-DocuServe" prototype with a full-stack PHP 8.3 / MySQL 9.1 solution designed for reliable deployment on Railway.

**System Status:** **80% Complete** — Core portals (Student, Registrar, SuperAdmin) are fully functional and deployed (Sprint 2 goal met); Remaining 20% covers optimization, SEO, legal, and robust accessibility polishing (Sprint 3 scope).

**Production URL:** [https://edocuserve.up.railway.app](https://edocuserve.up.railway.app)
*(Note: Requires VPN/On-Campus network as per campus security protocol for non-public data)*

---

## 🛠️ Unified System Status & Completion

Status is based on the 10 High-Level BRD (Business Requirement Document) Modules used in Sprint 2 for the MVP. Percentages show verified, deployed functionality.

| Module | BRD Category | System Status | % | Core Deployed Features |
|:---|:---|:---|:-:|:---|
| **01** | **Auth & User Mgmt** | Functional | **90%** | BCrypt login, student registration, password reset flows. CSRF + session timeouts on all portals. Login rate limiting active. Only minor registration page edits pending. |
| **02** | **Student Portal** | Functional | **85%** | Public catalog preview, search/filter catalog. Multi-item requests. Clearance check. Walk-in/Bank Slip/GCash payment flows with proof upload. Status tracking. |
| **03** | **Admin / Registrar** | Functional | **90%** | Payment verification gate. Queue processing, bulk status transitions. SLA column (3/5/10 days), Admin Name tracking. Student account lifecycle (approval, deactivation). |
| **04** | **SuperAdmin** | Functional | **90%** | Admin account lifecycle. Comprehensive document catalog CRUD (requires\_clearance control). Public announcements management. Audit logs. System settings with live SMTP/Email config editor. |
| **05** | **Reports & Export** | Functional | **85%** | Actionable Chart.js (v4.4) dashboards (trends, status, payment breakdown) on Admin/SuperAdmin. Live Excel (.xlsx) and PDF (TCPDF) export endpoints. PDF templates now stored on Cloudinary. |
| **06** | **Security Hardening** | In Progress | **70%** | App-wide CSRF coverage, session hijacking/fixation controls, bcrypt hashing, security headers (.htaccess), and HTTPS redirect live. Pending full CSRF enforcement on non-form POSTs and DB integrity (Sprint 3 target). |
| **07** | **Cloud Infra & Mail** | In Progress | **95%** | Production-grade Brevo HTTPS API mail integrated. App reads all `DB_` environment variables with local WAMP fallback. Railway deploy is configuration-agnostic. Final step is minor credential rotation. |
| **08** | **Container & Deploy** | **Done** | **90%** | Railway production environment is **LIVE** using Dockerfile build. Database migrated to production MySQL. Continuous Deployment pipeline active from `dev` branch. Verified connection to production Cloudinary bucket. |
| **09** | **Legal & SEO** | In Progress | **30%** | Privacy Policy and Terms of Use modals built. App is functionally ready. Missing full pages for legal clarity, custom 404 handler, and SEO basics (robots.txt, sitemap, meta tags). |
| **10** | **Responsive & Polish** | Stale | **15%** | Basic mobile compatibility via Bootstrap 5.3 CDN. **MAJOR DEBT:** No mobile sidebar toggle, no production compression for uploaded images, and zero WCAG accessibility auditing. Acknowledged debt for post-MVP. |
| **Total** | **MVP Release 1.1** | **Deployed** | **~80%** | Weighted average of the above deliverables. Core student process and all three management dashboards are functional and verified in the production environment. |

---

## 💻 Technical Stack (Release 1.1)

This project has been updated to remove the legacy PHPMailer (SMTP) stack and local file uploads, replacing them with modern, production-agnostic cloud integrations for reliable Railway hosting.

*   **Runtime:** PHP 8.3 (Production via official `php:8.3-cli` image), MySQL 9.1 (Production/Migration), Composer (PHP Dependency Manager). *Zero reliance on system-bundled PHPMailer or obsolete extension files.*
*   **Database:** 11 core tables (mixed InnoDB/MyISAM storage, no DB-level foreign keys — **migration to InnoDB+FK is Sprint 3 target**). Migrations applied sequentially.
*   **Email:** Sent strictly via the **Brevo (formerly Sendinblue) Transactional HTTPS API** using cURL. PHPMailer / direct SMTP commands are deleted/deactivated from the codebase to bypass Railway's port limitations.
*   **Cloud Storage:** All student-uploaded files (Bank Slips, GCash screenshots, Profile Avatars) and SuperAdmin templates are stored and served via **Cloudinary’s Programmable Media** bucket. `assets/uploads/` is used only as a development fallback.
*   **UI/Frontend:** Bootstrap 5.3 (via CDN), Chart.js 4.4, custom JavaScript, and TCPDF for PDF generation with custom templating. responsive on desktop; stale on critical mobile features (sidebar).
*   **Containerization:** `Dockerfile` is verified and used for Railway Hobby/Hobby+ deployment, exposing port 8080 and running the built-in PHP server.

---

## 🚀 Deployed URLs (Sprint 2/MVP)

Use the credentials provided by the university during testing.

*   **Production:** [https://edocuserve.up.railway.app](https://edocuserve.up.railway.app)
    *   Public Catalogs & Announcements
    *   `/auth/login.php` -> Dynamic redirects based on role (Student, Admin, SuperAdmin)
*   **WAMP Fallback (Development):** Localhost at default WAMP ports. `config/db.php` detects WAMP settings.

---

## 📁 Project Structure

```
edocuserve/
├── auth/                  # Auth portal (login, register, forgot/reset password)
├── student/                # Student portal (requests, account, feedback, walk-in payment)
│   └── csrf.php            # CSRF token helper for student forms
├── admin/                  # Registrar panel (requests, students, walk-in)
│   └── csrf.php             # CSRF token helper for admin forms
├── superadmin/             # SuperAdmin panel (admins, documents, announcements, settings)
│   ├── csrf.php             # CSRF token helper
│   ├── system_settings.php   # Per-section save buttons
│   ├── reports.php          # Chart.js dashboards
│   ├── export.php           # Excel (PhpSpreadsheet) / PDF (TCPDF) export
│   ├── system_logs.php       # Audit trail viewer
│   ├── manage_admins.php    # Admin CRUD
│   ├── manage_documents.php # Document catalog CRUD
│   ├── manage_requests.php  # Request queue manager
│   ├── announcements.php    # Public announcements
│   ├── sidebar.php          # Sidebar navigation
│   └── debug_mail.php       # Email debugging endpoint
├── includes/               # Shared code
│   ├── cloudinary.php       # Cloudinary SDK singleton helper
│   ├── slip_url.php         # Bank slip URL generator (Cloudinary)
│   ├── profile_url.php      # Profile photo URL generator (Cloudinary)
│   ├── release_date.php     # TAT/release date calculator (holidays)
│   ├── session_timeout.php   # Session inactivity check
│   ├── legal_modals.php      # Privacy/terms Bootstrap modals
│   └── mailer.php           # Brevo HTTPS API mailer
├── config/db.php           # DB config (.env loader + WAMP fallback)
├── database/
│   ├── edocuserve.sql       # 11-table schema
│   ├── migration_sql.sql     # Structured migrations (documents, requests, users, settings)
│   └── reseed_utf8.php       # SQL charset repair script
├── Dockerfile              # Railway Docker build (PHP 8.3-cli)
├── .htaccess               # Security headers, HTTPS redirect, hidden file deny
├── composer.json           # PhpSpreadsheet + Cloudinary deps
├── README.md               # This document
├── generated contents/     # BRD & Technical Specs docs
│   ├── About the system/
│   ├── BRD/
│   └── Technical Specifications/
└── assets/uploads/         # Local fallback only; production uses Cloudinary
```

---

## 🌐 Environment Variables

The app reads all configuration from environment variables with local WAMP fallback defaults. For Railway, create an `.env` file or set environment variables. All values are read via `getenv()` in `config/db.php`.

| Variable | Required? | Description | WAMP Default | Railway Default |
|:---|:---:|:---|:---|:---|
| `DB_HOST` | Yes* | MySQL host | localhost | Railway managed |
| `DB_NAME` | Yes* | Database name | edocuserve | Railway managed |
| `DB_USER` | Yes* | Database user | root | Railway managed |
| `DB_PASS` | Yes* | Database password | (empty) | Railway managed |
| `APP_URL` | Yes* | Base URL for links | http://localhost | Your Railway URL |
| `CLOUDINARY_CLOUD_NAME` | Yes* | Cloudinary cloud name | — | Railway env |
| `CLOUDINARY_API_KEY` | Yes* | Cloudinary API key | — | Railway env |
| `CLOUDINARY_API_SECRET` | Yes* | Cloudinary API secret | — | Railway env |

*Required unless using WAMP fallback (local development). In production Railway, these are injected automatically — no manual `.env` needed.

**TLS:** Railway Free plan does **not** allow SMTP connections on port 587. That's why email is routed through the Brevo HTTPS API (api.brevo.com), not direct SMTP. PHPMailer and the old `smtp_test/phpmailer` directory have been removed.

**Note:** The `smtp_pass` field in `system_settings` may be empty in production — set it through the admin panel under *Settings → Email → SMTP Pass*, or it will resolve to empty.

---

## 🚀 Deployed URLs (Sprint 2/MVP)

Use the credentials provided by the university during testing.

- **Production:** [https://pup-e-docuserve-production.up.railway.app/](https://pup-e-docuserve-production.up.railway.app/)
  - Public Catalogs & Announcements
  - `/auth/login.php` → Dynamic redirect based on role (Student / Admin / SuperAdmin)
- **WAMP Fallback (Development):** Localhost at default WAMP ports. `config/db.php` auto-detects WAMP settings.

---

## 🏗️ Sprint 3 & Post-Launch Roadmap

Core MVP goals for a functional system were met in Sprint 2. The remaining 20% focuses on stabilizing, optimizing, and securing the established application.

1.  **Security:** Full CSRF enforcement on *every* POST endpoint (currently form-only), database migration to full InnoDB tables with proper, transactional Foreign Key constraints.
2.  **UX/Responsive Debt:** Implement the mobile sidebar toggle for functional administration on small screens and add image compression/pipeline optimization for Cloudinary uploads.
3.  **SEO & Legal:** Create dedicated `/privacy` and `/terms` URLS (replacing modal), add custom 404, robots.txt, and sitemap. Populate legal boilerplate as provided by university counsel.
4.  **Accessibility (WCAG):** Preliminary auditing for color contrast and keyboard navigation, specifically on management data tables.
5.  **Performance:** Audit TCPDF performance, as large bulk PDF generation is a known ceiling ( O(N) PDF generation with no bulk optimization). Mark as `ponytail: custom bulk generation is ceiling`.