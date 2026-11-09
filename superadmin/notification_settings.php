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
    $section = $_POST['section'] ?? '';
    if ($section === 'email') {
        $keys = ['smtp_from_name', 'smtp_from_email'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
                $exists->execute([$key]);
                if ($exists->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$_POST[$key], $key]);
                } else {
                    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $_POST[$key]]);
                }
            }
        }
        if (!empty($_POST['brevo_api_key'])) {
            $exists = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'brevo_api_key'")->fetchColumn();
            if ($exists) $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'brevo_api_key'")->execute([$_POST['brevo_api_key']]);
            else $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('brevo_api_key', ?)")->execute([$_POST['brevo_api_key']]);
        }
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
            ->execute(["Updated system settings [Email Notifications]", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
        $success = "Email settings saved successfully.";
    } elseif ($section === 'recipients') {
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
        .main-content { margin-left: 230px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .page-content { padding: 24px; flex: 1; max-width: 780px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .btn-save { background: var(--sa-color); color: white; border: none; padding: 10px 32px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #283593; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-bell me-2" style="color:var(--sa-color);"></i>Notification Settings</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="email">
            <div class="section-card">
                <div class="section-header"><i class="fas fa-envelope me-2" style="color:#3498db;"></i>Email Provider (Brevo API)</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#f0f4ff; border-radius:6px; border-left:4px solid #3498db;">
                        <i class="fas fa-info-circle me-1"></i> Sends email through Brevo HTTPS API. Leave the API key blank to keep the current one. Get a key at Brevo dashboard → SMTP &amp; API → API keys → Generate API key.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label">Brevo API Key <span style="font-weight:400; color:#888;">(leave blank to keep current)</span></label>
                            <div style="position:relative;">
                                <input type="password" name="brevo_api_key" id="brevoApiKeyInput" class="form-control" placeholder="<?= !empty($settings['brevo_api_key']) ? '••••••••••••' : 'xkeysib-...' ?>" autocomplete="new-password">
                                <i class="fas fa-eye" id="toggleBrevoKey" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#aaa;font-size:13px;"></i>
                            </div>
                            <?php if (!empty($settings['brevo_api_key'])): ?><div style="font-size:11px; color:#27ae60; margin-top:3px;"><i class="fas fa-check-circle me-1"></i>API key is saved.</div><?php endif; ?>
                        </div>
                        <div class="col-md-3" style="display:flex; align-items:flex-end;">
                            <button type="button" id="btnTestBrevo" class="btn-save w-100" style="background:#3498db; padding:7px 12px;"><i class="fas fa-plug me-1"></i> Test</button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Name</label>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'PUP e-DocuServe') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Email Address</label>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>">
                            <div style="font-size:11px; color:#888; margin-top:3px;">Must be a verified sender in your Brevo account.</div>
                        </div>
                    </div>
                    <div id="brevoTestResult" style="display:none; margin-top:14px;"></div>
                    <div class="mt-3"><button type="submit" class="btn-save" style="background:#3498db;"><i class="fas fa-save me-2"></i>Save Email Settings</button></div>
                </div>
            </div>
        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="recipients">
            <div class="section-card">
                <div class="section-header"><i class="fas fa-users me-2" style="color:var(--sa-color);"></i>Admin Notification Recipients</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#f0f4ff; border-radius:6px; border-left:4px solid var(--sa-color);">
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
    <div class="footer-admin">© <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleBrevoKey')?.addEventListener('click', function () {
    const inp = document.getElementById('brevoApiKeyInput');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');
});
document.getElementById('btnTestBrevo')?.addEventListener('click', function () {
    const apiKey = document.querySelector('[name=brevo_api_key]').value.trim();
    const result = document.getElementById('brevoTestResult');
    this.disabled = true; this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing…'; result.style.display = 'none';
    fetch('brevo_test.php', { method: 'POST', body: new URLSearchParams({ action: 'test_brevo', api_key: apiKey }) })
        .then(r => r.json()).then(data => {
            result.style.display = 'block';
            result.innerHTML = data.ok ? '<div class="alert alert-success py-2" style="font-size:12px;"><i class="fas fa-check-circle me-1"></i>' + data.message + '</div>' : '<div class="alert alert-danger py-2" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>' + data.message + '</div>';
        }).catch(() => { result.style.display = 'block'; result.innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Test request failed.</div>'; })
        .finally(() => { this.disabled = false; this.innerHTML = '<i class="fas fa-plug me-1"></i> Test'; });
});
</script>
</body>
</html>
