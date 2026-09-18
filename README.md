# PUP e-DocuServe

![PUP Logo](assets/images/pup-logo.png)

**A Web-Based Document Request and Management System for Polytechnic University of the Philippines**

## 📋 Overview

PUP e-DocuServe is a comprehensive document management system designed to streamline document request and processing workflows for students, faculty, and administrative staff at the Polytechnic University of the Philippines. The system provides role-based access control, automated document processing, and secure file management capabilities.

## ✨ Features

### 👥 Multi-Role System
- **Students**: Request documents, track status, make payments
- **Administrators**: Process requests, manage documents, generate reports  
- **Super Administrators**: Full system control, user management, system monitoring

### 📄 Document Management
- Request various document types (TOR, Diploma, Certification, etc.)
- Online payment integration for document fees
- Secure file upload and storage system
- Document status tracking with real-time updates

### 🔐 Security & Authentication
- Role-based access control (RBAC)
- Secure user authentication and session management
- File upload validation and sanitization
- Activity logging for audit trails

### 📊 Reporting & Analytics
- Comprehensive system logs
- Financial reports and transaction tracking
- User activity monitoring
- Document request statistics

### 🎛️ Administrative Features
- User account management
- Announcement system
- Walk-in request processing
- Document template management

## 🏗️ System Architecture

### Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.3
- **Database**: MySQL 9.1.0
- **Server**: Apache HTTP Server (local) / Railway (Test and above)
- **PDF Generation**: TCPDF (bundled)
- **Email**: PHPMailer (bundled) via Brevo SMTP
- **Payment Processing**: Integration-ready payment gateways

### Directory Structure
```
PUP-e-DocuServe/
├── admin/              # Administrator portal
├── student/            # Student portal
├── SuperAdmin/         # Super Administrator portal
├── assets/             # Static assets (images, uploads)
├── config/             # Configuration files
├── includes/           # Core libraries and utilities
├── auth/               # Authentication modules
├── database/           # Schema + seed data (edocuserve.sql)
└── generated contents/ # System-generated documents
```

## 🚀 Installation

### Prerequisites
- PHP 8.3 or higher
- MySQL 9.1.0 (or a matching major version)
- Apache HTTP Server

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/RaverWayne/PUP-e-DocuServe.git
   cd PUP-e-DocuServe
   ```

2. **Configure the database**
   <!-- TODO: confirm actual DB name — README previously said edocuserve_db but schema file is edocuserve.sql -->
   - Create a MySQL database matching the name expected by the app
   - Import the schema and seed data from `database/edocuserve.sql`

3. **Configure environment**
   - Copy `config/db.example.php` to `config/db.php`
   - Update database credentials in `config/db.php`:
   ```php
   <?php
   $servername = "localhost";
   $username = "your_username";
   $password = "your_password";
   $dbname = "edocuserve_db";
   ?>
   ```

## 📖 Usage

### Student Access
1. Register/Login to the student portal
2. Browse available document types
3. Submit document requests
4. Upload required supporting documents
5. Make online payments
6. Track request status in real-time

### Administrator Access
1. Login to admin portal
2. Review pending document requests
3. Process and approve/reject requests
4. Generate documents using templates
5. Manage walk-in requests
6. Generate reports

### Super Administrator Access
1. Full system oversight
2. User account management
3. System configuration
4. Activity monitoring and logs
5. Announcement management

## 🔧 Configuration

### System Settings
The system can be configured through:
- `config/db.php` — Database configuration
- `.htaccess` — URL rewriting and security rules
- `includes/config.php` — Application-wide settings
- `system_settings` table — SMTP and other runtime settings (seeded per environment, not read from env vars)

## 👥 Authors

- **RaverWayne** - Initial development
- **Ritsouka - Coladilla** DevOps
- PUP e-DocuServe Development Team

---

<!-- TODO: update to the actual last commit/release date before pushing -->
**Last Updated**: September 2026  
**Version**: 1.0.0  
**Status**: Active Development
