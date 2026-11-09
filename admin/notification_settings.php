<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $keys = ['notification_recipients', 'notify_on_new_request'];
    foreach ($keys as $key) {
        $val = $_POST[$key] ?? '';
        $exists = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $exists->execute([$key]);
        if ($exists->fetchColumn() > 0) $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
        else $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
    }
    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
        ->execute(["Updated system settings [Notification Recipients]", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
    $success = "Notification recipients saved successfully.";
}

$stmt = $pdo->query("SELECT * FROM system_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];

$current_page = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings | PUP e-DocuServe</title>
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
        .sidebar-footer { padding: 12px 16px; border-top: 1px solid #333; }
        .main-content { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .page-content { padding: 24px; flex: 1; max-width: 780px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(139,0,0,0.1); }
        .btn-save { background: var(--pup-red); color: white; border: none; padding: 10px 32px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #8B2020; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-bell me-2" style="color:var(--pup-red);"></i>Notification Settings</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="section-card">
                <div class="section-header"><i class="fas fa-users me-2" style="color:var(--pup-red);"></i>Admin Notification Recipients</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#f0f4ff; border-radius:6px; border-left:4px solid var(--pup-red);">
                        <i class="fas fa-info-circle me-1"></i> These email addresses receive a notification whenever a student submits a new document request.
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Recipient Email Addresses</label>
                            <textarea name="notification_recipients" class="form-control" rows="3" placeholder="e.g. registrar@pup.edu.ph, admin@pup.edu.ph"><?= htmlspecialchars($settings['notification_recipients'] ?? '') ?></textarea>
                            <div style="font-size:11px; color:#888; margin-top:3px;">One email per line or separated by commas.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notify on New Request</label>
                            <select name="notify_on_new_request" class="form-select">
                                <option value="1" <?= ($settings['notify_on_new_request'] ?? '1') == '1' ? 'selected' : '' ?>>ON - Send notifications</option>
                                <option value="0" <?= ($settings['notify_on_new_request'] ?? '1') == '0' ? 'selected' : '' ?>>OFF - Do not send</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Recipients</button></div>
                </div>
            </div>
        </form>
    </div>
    <div class="footer-admin">© <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Admin</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
