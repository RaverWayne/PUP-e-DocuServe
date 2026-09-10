<?php
session_start();
/*header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
*/
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: student/index.php");
    exit();
}
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'];
    if ($role === 'superadmin') {
        header("Location: superadmin/index.php");
    } else {
        header("Location: admin/index.php");
    }
    exit();
}

// Fetch active announcements
$announcements = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP e-DocuServe | Online Document Request - Biñan Campus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
    <style>
        :root {
            --pup-red: #6D1A1A;
            --pup-red-light: #8B2020;
            --pup-gold: #FFD700;
            --pup-dark: #1a1a1a;
            --pup-gray: #f5f5f5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f0f0f0;
            color: #333;
        }

        /* NAVBAR */
        .navbar-pup {
            background-color: var(--pup-red);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        .navbar-pup .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .navbar-pup .brand img {
            height: 42px;
            width: 42px;
            object-fit: contain;
        }

        .navbar-pup .brand-text {
            color: white;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.3;
        }

        .navbar-pup .brand-text span {
            display: block;
            font-size: 11px;
            font-weight: 400;
            opacity: 0.85;
        }

        .navbar-pup .nav-actions {
            display: flex;
            gap: 8px;
        }

        .btn-nav-register {
            background: transparent;
            color: white;
            border: 1px solid white;
            padding: 6px 18px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-nav-register:hover {
            background: white;
            color: var(--pup-red);
        }

        .btn-nav-login {
            background: var(--pup-gold);
            color: var(--pup-dark);
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-nav-login:hover {
            background: #e6c200;
            color: var(--pup-dark);
        }

        /* HERO */
        .hero {
            background: linear-gradient(135deg, var(--pup-red) 0%, #5a0000 100%);
            color: white;
            text-align: center;
            padding: 60px 20px 50px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.05) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero-logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        }

        .hero h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .hero h1 span {
            color: var(--pup-gold);
        }

        .hero .subtitle {
            font-size: 13px;
            opacity: 0.85;
            margin-bottom: 4px;
        }

        .hero .subtitle-campus {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 4px;
        }

        .hero .tagline {
            font-size: 14px;
            opacity: 0.9;
            margin: 14px 0 20px;
            font-style: italic;
        }

        .btn-instructions {
            background: var(--pup-gold);
            color: var(--pup-dark);
            border: none;
            padding: 10px 28px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-instructions:hover {
            background: #e6c200;
            color: var(--pup-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* ADVISORY SECTION */
        .advisory-section {
            padding: 30px 20px;
            background: #f0f0f0;
        }

        .advisory-row {
            display: flex;
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .advisory-box {
            flex: 1;
            background: #ffe8e8;
            border: 1px solid #f5c0c0;
            border-radius: 6px;
            padding: 20px 24px;
            text-align: center;
        }

        .advisory-box h5 {
            color: var(--pup-red);
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-style: italic;
        }

        .advisory-box p {
            color: #6D1A1A;
            font-size: 13px;
            line-height: 1.6;
        }

        .advisory-box p strong {
            font-weight: 700;
        }

        .info-box {
            flex: 1;
            background: #eaf4e8;
            border: 1px solid #b8ddb4;
            border-radius: 6px;
            padding: 20px 24px;
            text-align: center;
        }

        .info-box h5 {
            color: #2d6a27;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-style: italic;
        }

        .info-box p {
            color: #2d6a27;
            font-size: 13px;
            font-style: italic;
            margin-bottom: 14px;
        }

        .info-box .payment-steps {
            background: white;
            border-radius: 6px;
            padding: 12px;
            text-align: left;
        }

        .info-box .payment-steps p {
            color: #333;
            font-size: 12px;
            font-style: normal;
            margin-bottom: 4px;
        }

        /* FEATURE CARDS */
        .features-section {
            background: white;
            padding: 40px 20px;
        }

        .features-row {
            display: flex;
            gap: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .feature-card {
            flex: 1;
            padding: 20px;
        }

        .feature-card h5 {
            font-size: 15px;
            font-weight: 700;
            color: var(--pup-dark);
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .btn-view-details {
            background: #5a9fd4;
            color: white;
            border: none;
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-view-details:hover {
            background: #4a8fc4;
            color: white;
        }

        /* FOOTER */
        .footer-pup {
            background: #f8f8f8;
            border-top: 1px solid #ddd;
            padding: 12px 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .footer-pup a {
            color: var(--pup-red);
            text-decoration: none;
        }

        .footer-pup a:hover {
            text-decoration: underline;
        }

        /* ANNOUNCEMENTS */
        .announcements-section {
            background: #fff8e1;
            border-top: 3px solid var(--pup-gold);
            padding: 24px 20px;
        }

        .announcements-section .ann-heading {
            max-width: 1100px;
            margin: 0 auto 16px;
            font-size: 14px;
            font-weight: 700;
            color: var(--pup-red);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .announcements-row {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ann-item {
            background: white;
            border: 1px solid #f0d080;
            border-left: 4px solid var(--pup-gold);
            border-radius: 6px;
            padding: 14px 18px;
        }

        .ann-item-title {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .ann-item-content {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .ann-item-meta {
            font-size: 11px;
            color: #aaa;
            margin-top: 6px;
        }

        /* MODAL */
        .modal-header {
            background: var(--pup-red);
            color: white;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        @media (max-width: 768px) {
            .advisory-row, .features-row {
                flex-direction: column;
            }
            .hero h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="assets/images/pup-logo.png" alt="PUP Logo" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
    </a>
    <div class="nav-actions">
        <a href="auth/register.php" class="btn-nav-register">Register</a>
        <a href="auth/login.php" class="btn-nav-login">Login</a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <img src="assets/images/pup-logo.png" alt="PUP Logo" class="hero-logo" onerror="this.style.display='none'">
    <h1>PUP <span>e-DocuServe</span></h1>
    <p class="subtitle">Online Document Request System</p>
    <p class="subtitle-campus">For Students and Graduates of PUP Biñan Campus</p>
    <p class="subtitle-campus">For concerns, email us at registrar.binan@pup.edu.ph</p>
    <p class="tagline">Request, Pay, Submit the Requirements, Monitor, and Claim.</p>
    <a href="#" class="btn-instructions" data-bs-toggle="modal" data-bs-target="#instructionsModal">
        Read Instructions
    </a>
</div>

<!-- ANNOUNCEMENTS -->
<?php if (!empty($announcements)): ?>
<div class="announcements-section">
    <div class="ann-heading">
        <i class="fas fa-bullhorn"></i> Announcements from the Registrar's Office
    </div>
    <div class="announcements-row">
        <?php foreach ($announcements as $ann): ?>
        <div class="ann-item">
            <div class="ann-item-title"><?= htmlspecialchars($ann['title']) ?></div>
            <div class="ann-item-content"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
            <div class="ann-item-meta"><i class="fas fa-clock me-1"></i><?= date('F d, Y', strtotime($ann['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ADVISORY -->
<div class="advisory-section">
    <div class="advisory-row">
        <div class="advisory-box">
            <h5>⚠ Advisory to Our Clients</h5>
            <p>
                As part of our monitoring and evaluation, and to improve the quality of our system and services,
                all requests in the <strong>PUP e-DocuServe</strong> with incomplete and invalid requirements
                shall be automatically deleted after <strong>90 days</strong> of noncompliance.
                Please be advised that the deletion from the system may be done without prior notice
                or approval from the requesting client.
                <br><br>
                Please be guided accordingly.
            </p>
        </div>
        <div class="info-box">
            <h5>📋 How to Submit Your Document Request</h5>
            <p><em>Follow these steps to successfully submit your request:</em></p>
            <div class="payment-steps">
                <p>1. Register and complete your profile.</p>
                <p>2. Click <strong>New Request</strong> and select your documents.</p>
                <p>3. Pay via bank slip and upload proof of payment.</p>
                <p>4. Monitor your request status via email notifications.</p>
                <p>5. Claim your documents at the Registrar's Office.</p>
            </div>
        </div>
    </div>
</div>

<!-- FEATURE CARDS -->
<div class="features-section">
    <div class="features-row">
        <div class="feature-card">
            <h5>📄 Document Requesting</h5>
            <p>Requesting of documents is now available online. Create an account and complete your profile to get access to the PUP e-DocuServe Online Document Request Page.</p>
            <a href="auth/register.php" class="btn-view-details">Get Started »</a>
        </div>
        <div class="feature-card">
            <h5>💳 Easy Payment</h5>
            <p>Payment for document requests is easy. Pay through any bank and upload your bank slip directly in the system as proof of payment for verification.</p>
            <a href="#" class="btn-view-details" data-bs-toggle="modal" data-bs-target="#instructionsModal">View details »</a>
        </div>
        <div class="feature-card">
            <h5>🔍 Request Monitoring</h5>
            <p>Check the latest status updates on your request. Receive email notifications when your payment is verified, your documents are being processed, and when they are ready for pick-up.</p>
            <a href="auth/login.php" class="btn-view-details">View details »</a>
        </div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> |
    <a href="#">Privacy Statement</a>
</div>

<!-- INSTRUCTIONS MODAL -->
<div class="modal fade" id="instructionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">How to Use PUP e-DocuServe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ol style="font-size: 14px; line-height: 2;">
                    <li>Click <strong>Register</strong> and fill out your personal and educational information.</li>
                    <li>Log in using your registered email and password.</li>
                    <li>Go to <strong>New Request</strong> and select the documents you need.</li>
                    <li>Note the total amount and pay through your bank. Keep the bank slip.</li>
                    <li>Upload a clear photo or scan of your bank slip in the system.</li>
                    <li>Wait for the registrar to verify your payment. You will be notified via email.</li>
                    <li>Once your documents are ready, you will receive an email notification.</li>
                    <li>Claim your documents at the <strong>Registrar's Office, PUP Biñan Campus</strong>.</li>
                </ol>
                <div class="alert alert-warning mt-3" style="font-size: 13px;">
                    <strong>Note:</strong> Requests with incomplete or invalid requirements will be automatically deleted after <strong>90 days</strong> of noncompliance.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="auth/register.php" class="btn btn-sm" style="background: var(--pup-red); color: white;">Register Now</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>
</body>
</html>