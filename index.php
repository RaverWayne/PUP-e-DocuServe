<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: student/index.php");
    exit();
}
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'];
    header("Location: " . ($role === 'superadmin' ? 'superadmin/index.php' : 'admin/index.php'));
    exit();
}

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$announcements = $pdo->query(
    "SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC"
)->fetchAll();

// Catalog count teaser (public — documents table only)
$catalog_count = $pdo->query("SELECT COUNT(*) FROM documents WHERE is_active = 1")->fetchColumn();

function s(string $key, array $settings, string $fallback = ''): string {
    return htmlspecialchars($settings[$key] ?? $fallback, ENT_QUOTES, 'UTF-8');
}
function nl2br_safe(string $key, array $settings, string $fallback = ''): string {
    return nl2br(htmlspecialchars($settings[$key] ?? $fallback, ENT_QUOTES, 'UTF-8'));
}
function steps_list(string $key, array $settings, string $fallback = ''): array {
    $raw = $settings[$key] ?? $fallback;
    return array_values(array_filter(array_map('trim', explode("\n", $raw))));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP e-DocuServe | Online Document Request - Binan Campus</title>
    <link rel="icon" type="image/png" href="assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --pup-red:       #6D1A1A;
            --pup-red-light: #8B2020;
            --pup-gold:      #FFD700;
            --pup-dark:      #1a1a1a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f0f0;
            color: #333;
            font-size: 15px;
        }

        .navbar-pup {
            background: var(--pup-red);
            padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 999;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .navbar-pup .brand {
            display: flex; align-items: center; gap: 12px; text-decoration: none;
        }
        .navbar-pup .brand img { height: 42px; width: 42px; object-fit: contain; }
        .navbar-pup .brand-text { color: white; font-size: 15px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 12px; font-weight: 400; opacity: .85; }
        .nav-actions { display: flex; gap: 8px; }
        .btn-nav-outline {
            background: transparent; color: white;
            border: 1px solid rgba(255,255,255,0.6);
            padding: 6px 18px; border-radius: 4px; font-size: 14px;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-nav-outline:hover { background: rgba(255,255,255,0.15); color: white; }
        .btn-nav-gold {
            background: var(--pup-gold); color: var(--pup-dark);
            border: none; padding: 6px 18px; border-radius: 4px;
            font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s;
        }
        .btn-nav-gold:hover { background: #e6c200; color: var(--pup-dark); }

        .hero {
            background: linear-gradient(135deg, var(--pup-red) 0%, #5a0000 100%);
            color: white; text-align: center; padding: 56px 20px 48px;
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.05) 0%, transparent 60%);
            pointer-events: none;
        }
        .hero-logo {
            width: 88px; height: 88px; object-fit: contain;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        }
        .hero h1 { font-size: 32px; font-weight: 700; margin-bottom: 6px; letter-spacing: .5px; }
        .hero h1 span { color: var(--pup-gold); }
        .hero .subtitle     { font-size: 15px; opacity: .85; margin-bottom: 4px; }
        .hero .subtitle-sm  { font-size: 14px; opacity: .7;  margin-bottom: 4px; }
        .hero .tagline      { font-size: 15px; opacity: .9; margin: 14px 0 22px; font-style: italic; }
        .btn-instructions {
            background: var(--pup-gold); color: var(--pup-dark);
            border: none; padding: 10px 28px; border-radius: 5px;
            font-size: 14px; font-weight: 600; text-decoration: none;
            display: inline-block; transition: all 0.2s;
        }
        .btn-instructions:hover {
            background: #e6c200; color: var(--pup-dark);
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .announcements-section {
            background: #fff8e1; border-top: 3px solid var(--pup-gold); padding: 24px 20px;
        }
        .ann-heading {
            max-width: 1200px; margin: 0 auto 14px;
            font-size: 13px; font-weight: 700; color: var(--pup-red);
            text-transform: uppercase; letter-spacing: .6px;
            display: flex; align-items: center; gap: 8px;
        }
        .announcements-row { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; }
        .ann-item {
            background: white; border: 1px solid #f0d080;
            border-left: 4px solid var(--pup-gold); border-radius: 6px; padding: 14px 18px;
        }
        .ann-item-title   { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 4px; }
        .ann-item-content { font-size: 14px; color: #555; line-height: 1.7; }
        .ann-item-meta    { font-size: 12px; color: #aaa; margin-top: 6px; }

        .advisory-section { padding: 32px 20px; background: #f0f0f0; }
        .advisory-row {
            display: flex; gap: 20px; max-width: 1200px; margin: 0 auto;
        }
        .advisory-box {
            flex: 1; background: #ffe8e8;
            border: 1px solid #f5c0c0; border-radius: 6px; padding: 24px 28px; text-align: center;
        }
        .advisory-box h5 {
            color: var(--pup-red); font-weight: 700; font-size: 14px;
            margin-bottom: 12px; text-transform: uppercase; letter-spacing: .4px;
        }
        .advisory-box p { color: #6D1A1A; font-size: 14px; line-height: 1.8; }
        .advisory-box p strong { font-weight: 700; }
        .info-box {
            flex: 1; background: #eaf4e8;
            border: 1px solid #b8ddb4; border-radius: 6px; padding: 24px 28px; text-align: center;
        }
        .info-box h5 {
            color: #2d6a27; font-weight: 700; font-size: 14px;
            margin-bottom: 12px; text-transform: uppercase; letter-spacing: .4px;
        }
        .info-box > p { color: #2d6a27; font-size: 14px; font-style: italic; margin-bottom: 12px; }
        .payment-steps { background: white; border-radius: 6px; padding: 14px 16px; text-align: left; }
        .payment-steps p { color: #333; font-size: 14px; margin-bottom: 5px; line-height: 1.6; }

        .catalog-teaser-section {
            background: white; border-top: 3px solid var(--pup-red); padding: 36px 20px;
        }
        .catalog-teaser-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            gap: 32px; flex-wrap: wrap;
        }
        .catalog-teaser-text { flex: 1; min-width: 260px; }
        .catalog-teaser-label {
            font-size: 11px; font-weight: 700; color: var(--pup-red);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .catalog-teaser-heading {
            font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 10px;
        }
        .catalog-teaser-text p {
            font-size: 14px; color: #666; line-height: 1.8; margin-bottom: 14px; max-width: 560px;
        }
        .teaser-pills { display: flex; gap: 10px; flex-wrap: wrap; }
        .teaser-pill {
            background: #f4f4f4; border: 1px solid #ddd;
            border-radius: 20px; padding: 4px 14px;
            font-size: 13px; color: #555;
            display: flex; align-items: center; gap: 6px;
        }
        .teaser-pill i { color: var(--pup-red); font-size: 12px; }
        .catalog-teaser-action { text-align: center; }
        .btn-catalog {
            background: var(--pup-red); color: white;
            border: none; padding: 13px 32px; border-radius: 5px;
            font-size: 15px; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.2s;
        }
        .btn-catalog:hover { background: var(--pup-red-light); color: white; transform: translateY(-1px); }
        .teaser-note { font-size: 12px; color: #aaa; margin-top: 8px; margin-bottom: 0; }

        .features-section { background: #f8f8f8; padding: 36px 20px; }
        .features-row { display: flex; gap: 20px; max-width: 1200px; margin: 0 auto; }
        .feature-card {
            flex: 1; padding: 22px 24px; background: white;
            border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .feature-card h5 { font-size: 16px; font-weight: 700; color: var(--pup-dark); margin-bottom: 8px; }
        .feature-card p  { font-size: 14px; color: #666; line-height: 1.7; margin-bottom: 14px; }
        .btn-view-details {
            background: #5a9fd4; color: white; border: none;
            padding: 6px 16px; border-radius: 4px; font-size: 13px;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-view-details:hover { background: #4a8fc4; color: white; }

        .footer-pup {
            background: #f8f8f8; border-top: 1px solid #ddd;
            padding: 14px 20px; text-align: center; font-size: 13px; color: #888;
        }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }
        .footer-pup a:hover { text-decoration: underline; }

        .modal-header { background: var(--pup-red); color: white; }
        .modal-header .btn-close { filter: invert(1); }

        @media (max-width: 768px) {
            .advisory-row, .features-row { flex-direction: column; }
            .catalog-teaser-inner { flex-direction: column; text-align: center; }
            .teaser-pills { justify-content: center; }
            .hero h1 { font-size: 24px; }
        }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="assets/images/pup-logo.png" alt="PUP Logo" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Binan Campus &mdash; Online Document Request</span>
        </div>
    </a>
    <div class="nav-actions">
        <a href="auth/register.php" class="btn-nav-outline">Register</a>
        <a href="auth/login.php"    class="btn-nav-gold">Login</a>
    </div>
</nav>

<div class="hero">
    <img src="assets/images/pup-logo.png" alt="PUP Logo" class="hero-logo" onerror="this.style.display='none'">
    <h1>PUP <span>e-DocuServe</span></h1>
    <p class="subtitle">Online Document Request System</p>
    <p class="subtitle-sm">For Students and Graduates of PUP Binan Campus</p>
    <p class="subtitle-sm">
        For concerns, email us at
        <a href="mailto:<?= s('office_email', $settings, 'registrar.binan@pup.edu.ph') ?>"
           style="color: var(--pup-gold);">
            <?= s('office_email', $settings, 'registrar.binan@pup.edu.ph') ?>
        </a>
    </p>
    <p class="tagline"><?= s('landing_tagline', $settings, 'Request, Pay, Submit the Requirements, Monitor, and Claim.') ?></p>
    <a href="#" class="btn-instructions" data-bs-toggle="modal" data-bs-target="#instructionsModal">
        <i class="fas fa-info-circle me-2"></i>Read Instructions
    </a>
</div>

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

<div class="advisory-section">
    <div class="advisory-row">
        <div class="advisory-box">
            <h5>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= s('landing_advisory_title', $settings, 'Advisory to Our Clients') ?>
            </h5>
            <p><?= nl2br_safe('landing_advisory_body', $settings,
                'As part of our monitoring and evaluation, and to improve the quality of our system and services, ' .
                'all requests in the PUP e-DocuServe with incomplete and invalid requirements shall be automatically ' .
                'deleted after 90 days of noncompliance. Please be advised that the deletion from the system may be ' .
                'done without prior notice or approval from the requesting client.' . "\n\nPlease be guided accordingly.") ?>
            </p>
        </div>
        <div class="info-box">
            <h5>
                <i class="fas fa-clipboard-list me-2"></i>
                <?= s('landing_howto_title', $settings, 'How to Submit Your Document Request') ?>
            </h5>
            <p><em>Follow these steps to successfully submit your request:</em></p>
            <div class="payment-steps">
                <?php
                $howto_steps = steps_list('landing_howto_steps', $settings,
                    "Register and complete your profile.\n" .
                    "Click New Request and select your documents.\n" .
                    "Pay via bank slip and upload proof of payment.\n" .
                    "Monitor your request status via email notifications.\n" .
                    "Claim your documents at the Registrar's Office."
                );
                foreach ($howto_steps as $i => $step): ?>
                    <p><?= ($i + 1) ?>. <?= htmlspecialchars($step) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="catalog-teaser-section">
    <div class="catalog-teaser-inner">
        <div class="catalog-teaser-text">
            <div class="catalog-teaser-label">Document Catalog</div>
            <div class="catalog-teaser-heading">What documents can you request?</div>
            <p>
                View all available documents, fees, processing times, and submission requirements
                before you visit the office. Know what to bring so your request moves forward without delays.
            </p>
            <div class="teaser-pills">
                <span class="teaser-pill"><i class="fas fa-check-circle"></i><?= $catalog_count ?> documents currently available</span>
                <span class="teaser-pill"><i class="fas fa-file-invoice-dollar"></i>Fees per PUP Citizen's Charter</span>
                <span class="teaser-pill"><i class="fas fa-clipboard-check"></i>Submission requirements included</span>
            </div>
        </div>
        <div class="catalog-teaser-action">
            <a href="catalog.php" class="btn-catalog">
                <i class="fas fa-search"></i> View Document Catalog
            </a>
            <p class="teaser-note">No login required</p>
        </div>
    </div>
</div>

<div class="features-section">
    <div class="features-row">
        <div class="feature-card">
            <h5><i class="fas fa-file-alt me-2" style="color:var(--pup-red);"></i>Document Requesting</h5>
            <p>Requesting of documents is now available online. Create an account and complete your profile to get access to the PUP e-DocuServe Online Document Request Page.</p>
            <a href="auth/register.php" class="btn-view-details">Get Started &raquo;</a>
        </div>
        <div class="feature-card">
            <h5><i class="fas fa-credit-card me-2" style="color:var(--pup-red);"></i>Easy Payment</h5>
            <p>Payment for document requests is easy. Pay through any bank and upload your bank slip directly in the system as proof of payment for verification.</p>
            <a href="#" class="btn-view-details" data-bs-toggle="modal" data-bs-target="#instructionsModal">View details &raquo;</a>
        </div>
        <div class="feature-card">
            <h5><i class="fas fa-search me-2" style="color:var(--pup-red);"></i>Request Monitoring</h5>
            <p>Check the latest status updates on your request. Receive email notifications when your payment is verified, your documents are being processed, and when they are ready for pick-up.</p>
            <a href="auth/login.php" class="btn-view-details">View details &raquo;</a>
        </div>
    </div>
</div>

<div class="footer-pup">
    &copy; <?= date('Y') ?> Polytechnic University of the Philippines &ndash; Binan Campus |
    <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Use</a> |
    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Statement</a>
</div>

<div class="modal fade" id="instructionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>How to Use PUP e-DocuServe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ol style="font-size: 14px; line-height: 2.1; padding-left: 20px;">
                    <?php
                    $modal_steps = steps_list('landing_instructions_steps', $settings,
                        "Click Register and fill out your personal and educational information.\n" .
                        "Log in using your registered email and password.\n" .
                        "Go to New Request and select the documents you need.\n" .
                        "Note the total amount and pay through your bank. Keep the bank slip.\n" .
                        "Upload a clear photo or scan of your bank slip in the system.\n" .
                        "Wait for the registrar to verify your payment. You will be notified via email.\n" .
                        "Once your documents are ready, you will receive an email notification.\n" .
                        "Claim your documents at the Registrar's Office, PUP Binan Campus."
                    );
                    foreach ($modal_steps as $step): ?>
                        <li><?= htmlspecialchars($step) ?></li>
                    <?php endforeach; ?>
                </ol>
                <div class="alert alert-warning mt-3" style="font-size: 14px;">
                    <strong>Note:</strong> <?= s('landing_instructions_note', $settings,
                        'Requests with incomplete or invalid requirements will be automatically deleted after 90 days of noncompliance.') ?>
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
<?php include 'includes/legal_modals.php'; ?>
</body>
</html>
