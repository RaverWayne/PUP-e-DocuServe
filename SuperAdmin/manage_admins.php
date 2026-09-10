<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name  = $_SESSION['admin_name'];
$success = '';
$error   = '';

// Add admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $name       = trim("$first_name $last_name");

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!str_ends_with(strtolower($email), '@pup.edu.ph')) {
        $error = "Admin email must end with @pup.edu.ph.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "An admin with this email already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT)]);

            $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
                           VALUES (?, ?, ?, ?, ?)")
                ->execute(["Added new admin account", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], $email]);

            $success = "Admin account created successfully.";
        }
    }
}

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle') {
    $id         = intval($_POST['admin_id']);
    $new_status = $_POST['new_status'];
    $pdo->prepare("UPDATE admins SET status = ? WHERE id = ? AND role = 'admin'")->execute([$new_status, $id]);
    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
        ->execute(["Set admin status to $new_status", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], "Admin ID $id"]);
    $success = "Admin status updated.";
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'reset_password') {
    $id       = intval($_POST['admin_id']);
    $new_pass = $_POST['new_password'] ?? '';
    if (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $error = "Password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ? AND role = 'admin'")->execute([password_hash($new_pass, PASSWORD_BCRYPT), $id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
            ->execute(["Reset admin password", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], "Admin ID $id"]);
        $success = "Admin password reset successfully.";
    }
}

$admins = $pdo->query("SELECT * FROM admins WHERE role = 'admin' ORDER BY created_at DESC")->fetchAll();
$current_page = 'admins';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins | PUP e-DocuServe</title>
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
        .page-content { padding: 24px; flex: 1; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .section-body { padding: 20px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; background: #f8f8f8; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .btn-add { background: var(--sa-color); color: white; border: none; padding: 8px 20px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-add:hover { background: #283593; }
        .btn-sm-action { padding: 4px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-disable { background: #e74c3c; color: white; }
        .btn-disable:hover { background: #c0392b; }
        .btn-enable { background: #27ae60; color: white; }
        .btn-enable:hover { background: #219a52; }
        .btn-reset { background: #f39c12; color: white; }
        .btn-reset:hover { background: #d68910; }
        .modal-header { background: var(--sa-color); color: white; }
        .modal-header .btn-close { filter: invert(1); }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
        .password-wrapper { position: relative; }
        .password-wrapper .toggle-pass { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; }
        .password-wrapper .toggle-pass:hover { color: var(--sa-color); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-user-shield me-2" style="color:var(--sa-color);"></i>Manage Admins</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ADD ADMIN FORM -->
        <div class="section-card">
            <div class="section-header">Add New Admin Account</div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">First Name <span style="color:var(--pup-red);">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Last Name <span style="color:var(--pup-red);">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email <span style="color:var(--pup-red);">*</span></label>
                            <input type="email" name="email" class="form-control"
                                placeholder="username@pup.edu.ph" required>
                            <div style="font-size:11px; color:#888; margin-top:2px;">Must end in @pup.edu.ph</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Password <span style="color:var(--pup-red);">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="addPass" class="form-control"
                                    placeholder="Min 6 chars, upper + lower + number" required>
                                <i class="fas fa-eye toggle-pass" onclick="togglePass('addPass',this)"></i>
                            </div>
                            <div style="font-size:11px; color:#888; margin-top:2px;">1 uppercase, 1 lowercase, 1 number required.</div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn-add w-100">
                                <i class="fas fa-plus me-2"></i>Add Admin
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ADMIN LIST -->
        <div class="section-card">
            <div class="section-header">
                Admin Accounts (<?= count($admins) ?>)
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="6" style="text-align:center; color:#aaa; padding:30px;">No admin accounts yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($admins as $i => $a): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $a['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                    <?= $a['status'] ?>
                                </span>
                            </td>
                            <td><?= date('m/d/Y', strtotime($a['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:5px; justify-content:center;">
                                    <!-- Toggle Status -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $a['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                        <button type="submit" class="btn-sm-action <?= $a['status'] === 'Active' ? 'btn-disable' : 'btn-enable' ?>">
                                            <i class="fas fa-<?= $a['status'] === 'Active' ? 'ban' : 'check' ?>"></i>
                                            <?= $a['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <!-- Reset Password -->
                                    <button class="btn-sm-action btn-reset"
                                        onclick="openReset(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-key me-2"></i>Reset Admin Password</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="admin_id" id="resetAdminId">
                    <p class="mb-3">Resetting password for: <strong id="resetAdminName"></strong></p>
                    <label class="form-label">New Password <span style="color:var(--pup-red);">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="resetPass" class="form-control" placeholder="New password (min 6 chars)" required>
                        <i class="fas fa-eye toggle-pass" onclick="togglePass('resetPass',this)"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--sa-color); color:white;">
                        <i class="fas fa-save me-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openReset(id, name) {
    document.getElementById('resetAdminId').value       = id;
    document.getElementById('resetAdminName').textContent = name;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
function togglePass(id, icon) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>
