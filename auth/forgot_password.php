<?php
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

// Already logged in? redirect away
if (isset($_SESSION['user_id']))  { header("Location: ../student/index.php"); exit(); }
if (isset($_SESSION['admin_id'])) { header("Location: ../admin/index.php");   exit(); }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Look up student only (admins reset via system)
        $stmt = $pdo->prepare("SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Same message either way (prevent email enumeration)
        $success = "If that email is registered, a password reset link has been sent. Check your inbox (and spam folder).";

        if ($user) {
            // Generate a secure random token
            $token   = bin2hex(random_bytes(32));           // 64-char hex
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?")
                ->execute([$token, $expires, $user['id']]);

            // Build reset URL
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetUrl = $scheme . '://' . $host . '/edocuserve/auth/reset_password.php?token=' . $token;

            $html = emailPasswordReset($user['first_name'], $resetUrl, 60);
            sendMail($pdo, $user['email'], $user['first_name'], 'Reset Your PUP e-DocuServe Password', $html);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }
        body { background:#f0f0f0; font-family:'Segoe UI',sans-serif; font-size:13px; min-height:100vh; display:flex; flex-direction:column; }
        .navbar-pup { background:var(--pup-red); padding:10px 20px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 6px rgba(0,0,0,0.3); }
        .navbar-pup .brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
        .navbar-pup .brand img { height:40px; }
        .navbar-pup .brand-text { color:white; font-size:14px; font-weight:600; line-height:1.3; }
        .navbar-pup .brand-text span { display:block; font-size:11px; font-weight:400; opacity:0.85; }
        .wrapper { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 16px; }
        .card-box { background:white; border:1px solid #ddd; border-radius:8px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08); }
        .card-header-pup { background:var(--pup-red); color:white; padding:16px 24px; display:flex; align-items:center; gap:10px; }
        .card-header-pup img { height:36px; width:36px; object-fit:contain; }
        .card-header-pup .header-text { font-size:16px; font-weight:700; }
        .card-header-pup .header-sub { font-size:11px; opacity:0.85; }
        .card-body-pup { padding:28px 24px; }
        .page-title { font-size:18px; font-weight:700; color:var(--pup-red); margin-bottom:8px; }
        .page-sub { font-size:12px; color:#888; margin-bottom:20px; line-height:1.5; }
        .form-label { font-size:12px; font-weight:600; color:#555; margin-bottom:4px; }
        .form-control { font-size:13px; border-radius:4px; border:1px solid #ccc; padding:8px 10px; }
        .form-control:focus { border-color:var(--pup-red); box-shadow:0 0 0 2px rgba(109,26,26,0.1); }
        .btn-submit { background:var(--pup-red); color:white; border:none; padding:9px 24px; border-radius:4px; font-size:13px; font-weight:600; cursor:pointer; width:100%; transition:background 0.2s; }
        .btn-submit:hover { background:#8B2020; }
        .back-link { display:block; text-align:center; margin-top:16px; font-size:12px; color:#888; text-decoration:none; }
        .back-link:hover { color:var(--pup-red); text-decoration:underline; }
        .footer-pup { background:#f8f8f8; border-top:1px solid #ddd; padding:12px 20px; text-align:center; font-size:12px; color:#666; }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="../index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">PUP e-DocuServe <span>Biñan Campus — Online Document Request</span></div>
    </a>
</nav>

<div class="wrapper">
    <div class="card-box">
        <div class="card-header-pup">
            <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
            <div>
                <div class="header-text">PUP e-DocuServe</div>
                <div class="header-sub">Biñan Campus — Online Document Request</div>
            </div>
        </div>

        <div class="card-body-pup">
            <div class="page-title"><i class="fas fa-lock-open me-2"></i>Forgot Password</div>
            <div class="page-sub">
                Enter the email address associated with your student account and we'll send you a link to reset your password.
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                        placeholder="your.email@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autofocus required>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>

<div class="footer-pup">
    &copy; <?= date('Y') ?> Polytechnic University of the Philippines &ndash; Biñan Campus
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>