<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Handle regular settings
    if (!$error) {
        $keys = [
            'school_year', 'office_hours', 'office_contact', 'office_email',
            'processing_notice', 'bank_name', 'bank_account_name',
            'bank_account_number', 'maintenance_mode',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure',
            'smtp_from_name', 'smtp_from_email'
        ];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")
                    ->execute([$_POST[$key], $key]);
            }
        }
        // SMTP password — only update if submitted
        if (!empty($_POST['smtp_pass'])) {
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'smtp_pass'")
                ->execute([$_POST['smtp_pass']]);
        }
        if (!$success) {
            $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
                ->execute(["Updated system settings", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
            $success = "System settings saved successfully.";
        }
    }
}

// Fetch all settings
$stmt     = $pdo->query("SELECT * FROM system_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$current_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | PUP e-DocuServe</title>
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
        .page-content { padding: 24px; flex: 1; max-width: 780px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .btn-save { background: var(--sa-color); color: white; border: none; padding: 10px 32px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #283593; }
        .maintenance-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; font-size: 13px; color: #856404; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-sliders-h me-2" style="color:var(--sa-color);"></i>System Settings</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- General Settings -->
            <div class="section-card">
                <div class="section-header"><i class="fas fa-university me-2" style="color:var(--sa-color);"></i>General Information</div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="<?= htmlspecialchars($settings['school_year'] ?? '') ?>" placeholder="e.g. 2025-2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office Hours</label>
                            <input type="text" name="office_hours" class="form-control" value="<?= htmlspecialchars($settings['office_hours'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office Contact Number</label>
                            <input type="text" name="office_contact" class="form-control" value="<?= htmlspecialchars($settings['office_contact'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office Email</label>
                            <input type="email" name="office_email" class="form-control" value="<?= htmlspecialchars($settings['office_email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Processing Notice <span style="font-weight:400; color:#888;">(shown on New Request page)</span></label>
                            <textarea name="processing_notice" class="form-control" rows="2"><?= htmlspecialchars($settings['processing_notice'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Settings -->
            <div class="section-card">
                <div class="section-header"><i class="fas fa-university me-2" style="color:#27ae60;"></i>Bank Payment Information</div>
                <div class="section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Name</label>
                            <input type="text" name="bank_account_name" class="form-control" value="<?= htmlspecialchars($settings['bank_account_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control" value="<?= htmlspecialchars($settings['bank_account_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="section-card">
                <div class="section-header"><i class="fas fa-tools me-2" style="color:#e74c3c;"></i>Maintenance Mode</div>
                <div class="section-body">
                    <?php if ($settings['maintenance_mode'] == '1'): ?>
                        <div class="maintenance-box">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Maintenance mode is currently ON.</strong> Students cannot log in or access the system.
                        </div>
                    <?php endif; ?>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Maintenance Mode</label>
                            <select name="maintenance_mode" class="form-select">
                                <option value="0" <?= ($settings['maintenance_mode'] ?? '0') == '0' ? 'selected' : '' ?>>OFF — System is accessible</option>
                                <option value="1" <?= ($settings['maintenance_mode'] ?? '0') == '1' ? 'selected' : '' ?>>ON — Restrict student access</option>
                            </select>
                        </div>
                        <div class="col-md-8" style="font-size:12px; color:#888; margin-top:20px;">
                            When turned ON, students will be shown a maintenance message when they try to log in.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email / SMTP Settings -->
            <div class="section-card">
                <div class="section-header"><i class="fas fa-envelope me-2" style="color:#3498db;"></i>Email Notifications (SMTP)</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#f0f4ff; border-radius:6px; border-left:4px solid #3498db;">
                        <i class="fas fa-info-circle me-1"></i>
                        Configure SMTP to enable email notifications (account approvals, request status updates) and the Forgot Password flow.
                        Leave blank to disable email sending without breaking other features.
                        <br><strong>Gmail tip:</strong> Use <code>smtp.gmail.com</code>, port <code>587</code>, security <code>TLS</code>, and an
                        <a href="https://support.google.com/accounts/answer/185833" target="_blank" style="color:#3498db;">App Password</a> (not your regular Gmail password).
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control"
                                value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                                placeholder="e.g. smtp.gmail.com">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Port</label>
                            <input type="number" name="smtp_port" class="form-control"
                                value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>"
                                placeholder="587">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Encryption</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= ($settings['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (port 587 — recommended)</option>
                                <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
                                <option value=""    <?= ($settings['smtp_secure'] ?? '') === ''    ? 'selected' : '' ?>>None (port 25)</option>
                            </select>
                        </div>
                        <div class="col-md-2" style="display:flex; align-items:flex-end;">
                            <button type="button" id="btnTestSmtp" class="btn-save w-100" style="background:#3498db; padding:7px 12px;">
                                <i class="fas fa-plug me-1"></i> Test
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_user" class="form-control"
                                value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>"
                                placeholder="your@gmail.com or provider token">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                SMTP Password / App Password
                                <span style="font-weight:400; color:#888;">(leave blank to keep current)</span>
                            </label>
                            <div style="position:relative;">
                                <input type="password" name="smtp_pass" id="smtpPassInput" class="form-control"
                                    placeholder="<?= !empty($settings['smtp_pass']) ? '••••••••••••' : 'Enter password' ?>"
                                    autocomplete="new-password">
                                <i class="fas fa-eye" id="toggleSmtpPass"
                                   style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#aaa;font-size:13px;"></i>
                            </div>
                            <?php if (!empty($settings['smtp_pass'])): ?>
                                <div style="font-size:11px; color:#27ae60; margin-top:3px;">
                                    <i class="fas fa-check-circle me-1"></i>Password is saved. Leave blank to keep it.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Name</label>
                            <input type="text" name="smtp_from_name" class="form-control"
                                value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'PUP e-DocuServe') ?>"
                                placeholder="PUP e-DocuServe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Email Address</label>
                            <input type="email" name="smtp_from_email" class="form-control"
                                value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                                placeholder="noreply@pup.edu.ph">
                        </div>
                    </div>

                    <!-- Test result area -->
                    <div id="smtpTestResult" style="display:none; margin-top:14px;"></div>
                </div>
            </div>

            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save All Settings</button>

        </form>
    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// SMTP password toggle
document.getElementById('toggleSmtpPass')?.addEventListener('click', function () {
    const inp = document.getElementById('smtpPassInput');
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});

// SMTP connection test
document.getElementById('btnTestSmtp')?.addEventListener('click', function () {
    const host   = document.querySelector('[name=smtp_host]').value.trim();
    const port   = document.querySelector('[name=smtp_port]').value.trim();
    const user   = document.querySelector('[name=smtp_user]').value.trim();
    const pass   = document.querySelector('[name=smtp_pass]').value.trim();
    const secure = document.querySelector('[name=smtp_secure]').value;
    const result = document.getElementById('smtpTestResult');

    if (!host || !user) {
        result.style.display = 'block';
        result.innerHTML = '<div class="alert alert-warning py-2" style="font-size:12px;"><i class="fas fa-exclamation-triangle me-1"></i>Please fill in SMTP Host and Username before testing.</div>';
        return;
    }

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing…';
    result.style.display = 'none';

    const params = new URLSearchParams({ action:'test_smtp', host, port, user, pass, secure });
    fetch('smtp_test.php', { method:'POST', body: params })
        .then(r => r.json())
        .then(data => {
            result.style.display = 'block';
            if (data.ok) {
                result.innerHTML = '<div class="alert alert-success py-2" style="font-size:12px;"><i class="fas fa-check-circle me-1"></i>' + data.message + '</div>';
            } else {
                result.innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>' + data.message + '</div>';
            }
        })
        .catch(() => {
            result.style.display = 'block';
            result.innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Test request failed. Check server logs.</div>';
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-plug me-1"></i> Test';
        });
});
</script>