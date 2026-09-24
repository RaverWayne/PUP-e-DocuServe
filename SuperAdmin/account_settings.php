<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$success = '';
$error   = '';

$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['profile_photo'];
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        $ext_ok  = in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
        if (!in_array($file['type'], $allowed) || !$ext_ok) {
            $error = "Only JPG/JPEG/PNG images are allowed.";
        } elseif (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']), $allowed)) {
            // Extension/browser-type checks above can both be spoofed by
            // renaming a file — this checks the file's actual bytes.
            $error = "The uploaded file's content doesn't match a valid image.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "Photo must not exceed 5MB.";
        } else {
            if (!is_dir('../assets/uploads/')) mkdir('../assets/uploads/', 0755, true);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], '../assets/uploads/' . $filename)) {
                $pdo->prepare("UPDATE admins SET profile_photo = ? WHERE id = ?")->execute([$filename, $admin_id]);
                $admin['profile_photo'] = $filename;
                $success = "Profile photo updated.";
            }
        }
    }

    // Password change
    if (isset($_POST['old_password'])) {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (empty($old) || empty($new) || empty($conf)) {
            $error = "All password fields are required.";
        } elseif (!password_verify($old, $admin['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Z]/', $new)) {
            $error = "Password must include at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $new)) {
            $error = "Password must include at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $new)) {
            $error = "Password must include at least one number.";
        } elseif ($new !== $conf) {
            $error = "New passwords do not match.";
        } elseif ($old === $new) {
            $error = "New password must be different from the current one.";
        } else {
            $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_BCRYPT), $admin_id]);
            $success = "Password changed successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --sa-color: #1a237e; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 230px; height: 100vh; background: #0d0d0d; color: white; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 16px 16px 12px; border-bottom: 1px solid #222; display: flex; align-items: center; gap: 10px; }
        .sidebar-brand img { height: 36px; }
        .sidebar-brand-text { font-size: 12px; font-weight: 700; line-height: 1.3; color: white; }
        .sidebar-brand-text span { display: block; font-size: 10px; font-weight: 400; opacity: 0.7; }
        .sidebar-role { padding: 10px 16px; background: var(--sa-color); font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .sidebar-nav { flex: 1; padding: 10px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: #bbb; text-decoration: none; font-size: 12px; transition: all 0.2s; }
        .sidebar-nav a:hover { background: #1a1a1a; color: white; }
        .sidebar-nav a.active { background: var(--sa-color); color: white; }
        .sidebar-nav a i { width: 16px; text-align: center; font-size: 12px; }
        .sidebar-nav .nav-section { padding: 10px 16px 4px; font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-footer { padding: 12px 16px; border-top: 1px solid #222; font-size: 12px; color: #777; }
        .sidebar-footer a { color: #f66; text-decoration: none; }
        .main-content { margin-left: 230px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--sa-color); }
        .page-content { padding: 24px; flex: 1; max-width: 540px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .password-wrapper { position: relative; }
        .password-wrapper .toggle-pass { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; font-size: 13px; background: none; border: none; padding: 0; z-index: 5; }
        .password-wrapper .toggle-pass:hover { color: var(--sa-color); }
        .password-wrapper .form-control { padding-right: 36px; }

        .pwd-checklist { list-style: none; padding: 8px 12px; margin-top: 8px; margin-bottom: 0; font-size: 11px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; }
        .pwd-req { margin-bottom: 4px; display: flex; align-items: center; gap: 6px; color: #777; transition: all 0.2s; }
        .pwd-req:last-child { margin-bottom: 0; }
        .pwd-req.valid { color: #27ae60; font-weight: 600; }
        .pwd-req.valid i { color: #27ae60; }
        .pwd-req.invalid { color: #888; }
        .pwd-req.invalid i { color: #bbb; }
        .btn-submit { background: var(--sa-color); color: white; border: none; padding: 8px 28px; border-radius: 5px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #283593; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php $current_page = 'account'; include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-cog me-2" style="color:var(--sa-color);"></i>Account Settings</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

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

        <!-- PROFILE INFO -->
        <div class="section-card" style="margin-bottom:16px;">
            <div class="section-header">Account Information</div>
            <div class="section-body">
                <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                    <!-- Profile Photo -->
                    <div style="text-align:center;">
                        <?php $photo = $admin['profile_photo'] ?? null; ?>
                        <?php if ($photo && file_exists("../assets/uploads/$photo")): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($photo) ?>"
                                style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #ddd;">
                        <?php else: ?>
                            <div style="width:80px; height:80px; border-radius:50%; background:#e0e0e0; display:flex; align-items:center; justify-content:center; font-size:28px; color:#aaa;">
                                <i class="fas fa-crown" style="color:var(--sa-color);"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Name & Email -->
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#333;"><?= htmlspecialchars($admin['name']) ?></div>
                        <div style="font-size:13px; color:#888; margin-top:2px;"><?= htmlspecialchars($admin['email']) ?></div>
                        <span class="badge mt-1" style="background:var(--sa-color); font-size:11px;">Super Admin</span>
                    </div>
                    <!-- Upload Photo -->
                    <div style="margin-left:auto;">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <label style="font-size:12px; font-weight:600; color:#555; display:block; margin-bottom:4px;">
                                Update Profile Photo
                            </label>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png"
                                    class="form-control" style="font-size:12px; padding:4px 8px; width:220px;">
                                <button type="submit" class="btn-submit" style="padding:5px 14px; font-size:12px;">
                                    <i class="fas fa-upload me-1"></i>Upload
                                </button>
                            </div>
                            <div style="font-size:11px; color:#aaa; margin-top:2px;">JPG/PNG only, max 5MB.</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="section-card">
            <div class="section-header">Change Password</div>
            <div class="section-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="oldPass" class="form-label">Current Password <span style="color:var(--pup-red);" aria-hidden="true">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="old_password" id="oldPass" class="form-control" placeholder="Current Password" required aria-required="true">
                            <button type="button" class="toggle-pass" id="toggleOldPassBtn" aria-label="Toggle current password visibility">
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
                        <label for="confPass" class="form-label">Confirm New Password <span style="color:var(--pup-red);" aria-hidden="true">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confPass" class="form-control" placeholder="Confirm New Password" required aria-required="true" autocomplete="new-password">
                            <button type="button" class="toggle-pass" id="toggleConfPassBtn" aria-label="Toggle confirm password visibility">
                                <i class="fas fa-eye" id="toggleConfPassIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save me-2"></i>Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
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
makeToggle('toggleConfPassBtn', 'toggleConfPassIcon', 'confPass');

// Live Password Checklist validation
const pwdInput = document.getElementById('newPass');
const confirmInput = document.getElementById('confPass');

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