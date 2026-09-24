<?php
session_start();
require_once '../config/db.php';
require_once 'csrf.php';

// Session timeout — 10 minutes
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old_password     = $_POST['old_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!password_verify($old_password, $user['password'])) {
        $error = "Your current password is incorrect.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password must include at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = "Password must include at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = "Password must include at least one number.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif ($old_password === $new_password) {
        $error = "New password must be different from your current password.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt   = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        $success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }

        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; }

        .navbar-pup {
            background-color: var(--pup-red); padding: 8px 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 999;
        }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.85; }
        .dropdown-toggle { background: none; border: none; color: white; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-toggle:focus { box-shadow: none; }

        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border-bottom: none; }
        .tabs-bar .nav-link {
            color: #555; font-size: 13px; padding: 12px 16px;
            border: none; border-bottom: 3px solid transparent;
            border-radius: 0; text-decoration: none;
            display: flex; align-items: center; gap: 6px;
        }
        .tabs-bar .nav-link:hover { color: var(--pup-red); }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; }

        .content-wrapper { max-width: 960px; margin: 24px auto; padding: 0 16px 40px; }

        .section-card { background: white; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
        .section-header { background: #e8e8e8; padding: 10px 16px; font-weight: 600; font-size: 13px; color: #333; border-bottom: 1px solid #ddd; }
        .section-body { padding: 20px; }

        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-control { font-size: 13px; border-radius: 4px; border: 1px solid #ccc; padding: 7px 10px; }
        .form-control:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(139,0,0,0.1); }

        .password-wrapper { position: relative; }
        .password-wrapper .toggle-pass {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%); cursor: pointer;
            color: #aaa; font-size: 13px; background: none; border: none; padding: 0;
            z-index: 5;
        }
        .password-wrapper .toggle-pass:hover { color: var(--pup-red); }
        .password-wrapper .form-control { padding-right: 36px; }

        .pwd-checklist { list-style: none; padding: 8px 12px; margin-top: 8px; margin-bottom: 0; font-size: 11px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; }
        .pwd-req { margin-bottom: 4px; display: flex; align-items: center; gap: 6px; color: #777; transition: all 0.2s; }
        .pwd-req:last-child { margin-bottom: 0; }
        .pwd-req.valid { color: #27ae60; font-weight: 600; }
        .pwd-req.valid i { color: #27ae60; }
        .pwd-req.invalid { color: #888; }
        .pwd-req.invalid i { color: #bbb; }

        .btn-submit {
            background: var(--pup-red); color: white; border: none;
            padding: 8px 28px; border-radius: 5px; font-size: 13px;
            font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #8B2020; }

        .footer-pup {
            background: #f8f8f8; border-top: 1px solid #ddd;
            padding: 12px 20px; text-align: center;
            font-size: 12px; color: #666; margin-top: 20px;
        }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
    </a>
    <div class="dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle" style="font-size:18px;"></i>
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            <i class="fas fa-caret-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="font-size:13px;">
            <li><a class="dropdown-item" href="account_settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<!-- TABS -->
<div class="tabs-bar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="new_request.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
        <li class="nav-item"><a class="nav-link" href="requests.php"><i class="fas fa-list"></i> Requests</a></li>
        <li class="nav-item"><a class="nav-link active" href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
    </ul>
</div>

<!-- CONTENT -->
<div class="content-wrapper">

    <div class="section-card">
        <div class="section-header">Account Settings</div>
        <div class="section-body">

            <?php if (!empty($success)): ?>
                <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="section-card" style="max-width: 460px;">
                <div class="section-header">Change Your Password</div>
                <div class="section-body">
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="oldPass" class="form-label">Old Password <span style="color:var(--pup-red);" aria-hidden="true">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="old_password" id="oldPass" class="form-control" placeholder="Old Password" required aria-required="true">
                                <button type="button" class="toggle-pass" id="toggleOldPassBtn" aria-label="Toggle old password visibility">
                                    <i class="fas fa-eye" id="toggleOldPassIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newPass" class="form-label">New Password <span style="color:var(--pup-red);" aria-hidden="true">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="newPass" class="form-control" placeholder="New Password" required aria-required="true" aria-describedby="pwdChecklist" autocomplete="new-password">
                                <button type="button" class="toggle-pass" id="toggleNewPassBtn" aria-label="Toggle new password visibility">
                                    <i class="fas fa-eye" id="toggleNewPassIcon"></i>
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
                            <label for="confirmPass" class="form-label">Confirm New Password <span style="color:var(--pup-red);" aria-hidden="true">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirmPass" class="form-control" placeholder="Confirm New Password" required aria-required="true" autocomplete="new-password">
                                <button type="button" class="toggle-pass" id="toggleConfPassBtn" aria-label="Toggle confirm password visibility">
                                    <i class="fas fa-eye" id="toggleConfPassIcon"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save me-2"></i>Submit
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- FOOTER -->
<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> | <a href="#">Privacy Statement</a>
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
makeToggle('toggleOldPassBtn', 'toggleOldPassIcon', 'oldPass');
makeToggle('toggleNewPassBtn', 'toggleNewPassIcon', 'newPass');
makeToggle('toggleConfPassBtn', 'toggleConfPassIcon', 'confirmPass');

// Live Password Checklist validation
const pwdInput = document.getElementById('newPass');
const confirmInput = document.getElementById('confirmPass');

function updatePwdRequirement(elemId, isValid) {
    const elem = document.getElementById(elemId);
    if (!elem) return;
    const icon = elem.querySelector('i');
    if (isValid) {
        elem.classList.remove('invalid');
        elem.classList.add('valid');
        if (icon) icon.className = 'fas fa-circle-check';
    } else {
        elem.classList.remove('valid');
        elem.classList.add('invalid');
        if (icon) icon.className = 'fas fa-circle-xmark';
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

if (pwdInput) pwdInput.addEventListener('input', checkPasswordComplexity);
if (confirmInput) confirmInput.addEventListener('input', checkPasswordComplexity);
</script>
</body>
</html>
