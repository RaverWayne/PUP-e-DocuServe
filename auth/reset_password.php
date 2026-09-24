<?php
session_start();
require_once '../config/db.php';

// Already logged in? redirect away
if (isset($_SESSION['user_id']))  { header("Location: ../student/index.php"); exit(); }
if (isset($_SESSION['admin_id'])) { header("Location: ../admin/index.php");   exit(); }

$token   = trim($_GET['token'] ?? '');
$success = '';
$error   = '';
$valid   = false;
$user    = null;

// Validate token
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email
        FROM users
        WHERE reset_token = ?
          AND reset_token_expires > UTC_TIMESTAMP()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $valid = true;
    } else {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
}

if (empty($token)) {
    $error = "No reset token provided.";
}

// Handle new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must include at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must include at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must include at least one number.";
    } elseif ($password !== $password2) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_token_expires = NULL
            WHERE id = ?
        ")->execute([$hash, $user['id']]);

        $success = "Your password has been reset successfully. You can now log in with your new password.";
        $valid   = false; // hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }
        body { background:#f0f0f0; font-family:'Segoe UI',sans-serif; font-size:13px; min-height:100vh; display:flex; flex-direction:column; }
        .navbar-pup { background:var(--pup-red); padding:10px 20px; display:flex; align-items:center; box-shadow:0 2px 6px rgba(0,0,0,0.3); }
        .navbar-pup .brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
        .navbar-pup .brand img { height:40px; }
        .navbar-pup .brand-text { color:white; font-size:14px; font-weight:600; line-height:1.3; }
        .navbar-pup .brand-text span { display:block; font-size:11px; font-weight:400; opacity:0.85; }
        .wrapper { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 16px; }
        .card-box { background:white; border:1px solid #ddd; border-radius:8px; width:100%; max-width:440px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08); }
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
        .password-wrapper { position:relative; }
        .password-wrapper .toggle-pass { position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#aaa; font-size:13px; z-index:5; background:none; border:none; padding:0; }
        .password-wrapper .toggle-pass:hover { color:var(--pup-red); }
        .password-wrapper .form-control { padding-right:36px; }
        .pwd-checklist { list-style:none; padding:8px 12px; margin-top:8px; margin-bottom:0; font-size:11px; background:#fafafa; border:1px solid #eee; border-radius:4px; }
        .pwd-req { margin-bottom:4px; display:flex; align-items:center; gap:6px; color:#777; transition:all 0.2s; }
        .pwd-req:last-child { margin-bottom:0; }
        .pwd-req.valid { color:#27ae60; font-weight:600; }
        .pwd-req.valid i { color:#27ae60; }
        .pwd-req.invalid { color:#888; }
        .pwd-req.invalid i { color:#bbb; }
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
            <div class="page-title"><i class="fas fa-key me-2"></i>Set New Password</div>

            <?php if ($valid && $user): ?>
                <div class="page-sub">
                    Setting a new password for <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    (<?= htmlspecialchars($user['email']) ?>)
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
                </div>
                <div style="text-align:center; margin-top:16px;">
                    <a href="login.php" class="btn-submit" style="display:inline-block; text-decoration:none; width:auto; padding:9px 32px;">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                </div>
            <?php elseif ($error && !$valid): ?>
                <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
                <a href="forgot_password.php" class="back-link">
                    <i class="fas fa-redo me-1"></i>Request a new reset link
                </a>
            <?php elseif ($valid): ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label for="passInput" class="form-label">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="passInput" class="form-control"
                                placeholder="Enter new password" autocomplete="new-password" required
                                aria-required="true" aria-describedby="pwdChecklist">
                            <button type="button" class="toggle-pass" id="togglePass1" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="togglePass1Icon"></i>
                            </button>
                        </div>
                        <ul class="pwd-checklist" id="pwdChecklist" aria-live="polite">
                            <li id="req-len" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 8 characters</li>
                            <li id="req-upper" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 uppercase letter (A-Z)</li>
                            <li id="req-lower" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 lowercase letter (a-z)</li>
                            <li id="req-num" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 number (0-9)</li>
                            <li id="req-match" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> Passwords match</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <label for="passInput2" class="form-label">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password2" id="passInput2" class="form-control"
                                placeholder="Re-enter new password" autocomplete="new-password" required
                                aria-required="true">
                            <button type="button" class="toggle-pass" id="togglePass2" aria-label="Toggle confirm password visibility">
                                <i class="fas fa-eye" id="togglePass2Icon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save me-2"></i>Save New Password
                    </button>
                </form>

            <?php endif; ?>

            <?php if ($valid): ?>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="footer-pup">
    &copy; <?= date('Y') ?> Polytechnic University of the Philippines &ndash; Biñan Campus
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function makeToggle(btnId, iconId, inputId) {
    document.getElementById(btnId)?.addEventListener('click', function () {
        const inp = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (inp && icon) {
            const isPassword = inp.type === 'password';
            inp.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isPassword);
            icon.classList.toggle('fa-eye-slash', isPassword);
        }
    });
}
makeToggle('togglePass1', 'togglePass1Icon', 'passInput');
makeToggle('togglePass2', 'togglePass2Icon', 'passInput2');

// Live Password Checklist validation
const pwdInput = document.getElementById('passInput');
const confirmInput = document.getElementById('passInput2');

function updatePwdRequirement(elemId, isValid) {
    const elem = document.getElementById(elemId);
    if (!elem) return;
    const icon = elem.querySelector('i');
    if (isValid) {
        elem.classList.remove('invalid');
        elem.classList.add('valid');
        if (icon) {
            icon.className = 'fas fa-circle-check';
        }
    } else {
        elem.classList.remove('valid');
        elem.classList.add('invalid');
        if (icon) {
            icon.className = 'fas fa-circle-xmark';
        }
    }
}

function checkPasswordComplexity() {
    if (!pwdInput) return;
    const val = pwdInput.value;
    const confVal = confirmInput ? confirmInput.value : '';

    updatePwdRequirement('req-len', val.length >= 8);
    updatePwdRequirement('req-upper', /[A-Z]/.test(val));
    updatePwdRequirement('req-lower', /[a-z]/.test(val));
    updatePwdRequirement('req-num', /[0-9]/.test(val));
    updatePwdRequirement('req-match', val.length > 0 && val === confVal);
}

if (pwdInput) {
    pwdInput.addEventListener('input', checkPasswordComplexity);
}
if (confirmInput) {
    confirmInput.addEventListener('input', checkPasswordComplexity);
}
</script>
</body>
</html>