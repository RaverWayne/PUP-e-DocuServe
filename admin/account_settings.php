<?php
session_start();
require_once '../config/db.php';
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
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
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
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
        :root { --pup-red: #6D1A1A; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1a1a1a; color: white; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 16px 16px 12px; border-bottom: 1px solid #333; display: flex; align-items: center; gap: 10px; }
        .sidebar-brand img { height: 36px; }
        .sidebar-brand-text { font-size: 12px; font-weight: 700; line-height: 1.3; color: white; }
        .sidebar-brand-text span { display: block; font-size: 10px; font-weight: 400; opacity: 0.7; }
        .sidebar-role { padding: 10px 16px; background: var(--pup-red); font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .sidebar-nav { flex: 1; padding: 10px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: #ccc; text-decoration: none; font-size: 13px; transition: all 0.2s; }
        .sidebar-nav a:hover { background: #2a2a2a; color: white; }
        .sidebar-nav a.active { background: var(--pup-red); color: white; }
        .sidebar-nav a i { width: 16px; text-align: center; }
        .sidebar-nav .nav-section { padding: 8px 16px 4px; font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-footer { padding: 12px 16px; border-top: 1px solid #333; font-size: 12px; color: #888; }
        .sidebar-footer a { color: #f66; text-decoration: none; }
        .main-content { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--pup-red); }
        .page-content { padding: 24px; flex: 1; max-width: 500px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(139,0,0,0.1); }
        .password-wrapper { position: relative; }
        .password-wrapper .toggle-pass { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; font-size: 13px; }
        .password-wrapper .toggle-pass:hover { color: var(--pup-red); }
        .btn-submit { background: var(--pup-red); color: white; border: none; padding: 8px 28px; border-radius: 5px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #8B2020; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">PUP e-DocuServe <span>Biñan Campus</span></div>
    </div>
    <div class="sidebar-role"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php"><i class="fas fa-file-alt"></i> Manage Requests</a>
        <a href="walkin_requests.php"><i class="fas fa-walking"></i> Walk-in Payments</a>
        <div class="nav-section">Users</div>
        <a href="students.php"><i class="fas fa-users"></i> Students</a>
        <div class="nav-section">Account</div>
        <a href="account_settings.php" class="active"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="margin-bottom:6px;">Logged in as <strong style="color:#ccc;"><?= htmlspecialchars($admin_name) ?></strong></div>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-cog me-2" style="color:var(--pup-red);"></i>Account Settings</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
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
                                <i class="fas fa-user-shield"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Name & Email -->
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#333;"><?= htmlspecialchars($admin['name']) ?></div>
                        <div style="font-size:13px; color:#888; margin-top:2px;"><?= htmlspecialchars($admin['email']) ?></div>
                        <span class="badge bg-danger mt-1" style="font-size:11px;">Admin</span>
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
                        <label class="form-label">Current Password <span style="color:var(--pup-red);">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="old_password" id="oldPass" class="form-control" placeholder="Current Password">
                            <i class="fas fa-eye toggle-pass" onclick="togglePass('oldPass', this)"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span style="color:var(--pup-red);">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="newPass" class="form-control" placeholder="New Password">
                            <i class="fas fa-eye toggle-pass" onclick="togglePass('newPass', this)"></i>
                        </div>
                        <div style="font-size:11px; color:#888; margin-top:3px;">Minimum 6 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password <span style="color:var(--pup-red);">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confPass" class="form-control" placeholder="Confirm New Password">
                            <i class="fas fa-eye toggle-pass" onclick="togglePass('confPass', this)"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save me-2"></i>Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(id, icon) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>