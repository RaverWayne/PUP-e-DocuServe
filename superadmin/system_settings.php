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

    $section_keys = [
        'general'     => ['school_year', 'office_hours', 'office_contact', 'office_email', 'processing_notice'],
        'landing'     => ['landing_tagline', 'landing_advisory_title', 'landing_advisory_body', 'landing_howto_title', 'landing_howto_steps', 'landing_instructions_steps', 'landing_instructions_note'],
        'bank'        => ['bank_name', 'bank_account_name', 'bank_account_number'],
        'maintenance' => ['maintenance_mode'],
        'email'       => ['smtp_from_name', 'smtp_from_email'],
        'legal'       => ['terms_of_use', 'privacy_statement'],
    ];

    if (isset($section_keys[$section])) {
        foreach ($section_keys[$section] as $key) {
            if (isset($_POST[$key])) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
                $exists->execute([$key]);
                if ($exists->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")
                        ->execute([$_POST[$key], $key]);
                } else {
                    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")
                        ->execute([$key, $_POST[$key]]);
                }
            }
        }
    }

    // Special case: brevo_api_key only updates if not empty
    if ($section === 'email' && !empty($_POST['brevo_api_key'])) {
        $exists = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'brevo_api_key'")->fetchColumn();
        if ($exists) {
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'brevo_api_key'")
                ->execute([$_POST['brevo_api_key']]);
        } else {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('brevo_api_key', ?)")
                ->execute([$_POST['brevo_api_key']]);
        }
    }

    if (!$error) {
        $sectionLabels = [
            'general' => 'General Information', 'landing' => 'Landing Page Content',
            'bank' => 'Bank Payment Information', 'maintenance' => 'Maintenance Mode',
            'email' => 'Email Notifications', 'legal' => 'Legal / Policy Pages',
        ];
        $sectionLabel = $sectionLabels[$section] ?? $section;
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
            ->execute(["Updated system settings [$sectionLabel]", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
        $success = "Settings saved successfully.";
    }
}

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
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }    </style>
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
            <input type="hidden" name="section" value="general">

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
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save General Information</button>
                        </div>
                    </div>
                </div>
            </div>

        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="landing">
            <div class="section-card">
                <div class="section-header"><i class="fas fa-globe me-2" style="color:#e67e22;"></i>Landing Page Content</div>
                <div class="section-body">
                    <div style="background:#fff8e1; border:1px solid #ffc107; border-left:4px solid #e67e22; border-radius:6px; padding:10px 14px; font-size:12px; color:#856404; margin-bottom:18px;">
                        <i class="fas fa-bolt me-1"></i>
                        <strong>Changes here are immediately visible to all public visitors on the homepage — no developer or code changes needed.</strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Hero Tagline <span style="font-weight:400; color:#888;">(shown below the campus name in the header)</span></label>
                            <input type="text" name="landing_tagline" class="form-control"
                                value="<?= htmlspecialchars($settings['landing_tagline'] ?? '') ?>"
                                placeholder="e.g. Request, Pay, Submit the Requirements, Monitor, and Claim.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Advisory Box — Title</label>
                            <input type="text" name="landing_advisory_title" class="form-control"
                                value="<?= htmlspecialchars($settings['landing_advisory_title'] ?? '') ?>"
                                placeholder="⚠ Advisory to Our Clients">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Advisory Box — Content</label>
                            <textarea name="landing_advisory_body" class="form-control" rows="4"
                                placeholder="Advisory text shown in the red box on the homepage…"><?= htmlspecialchars($settings['landing_advisory_body'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">How to Submit — Section Title</label>
                            <input type="text" name="landing_howto_title" class="form-control"
                                value="<?= htmlspecialchars($settings['landing_howto_title'] ?? '') ?>"
                                placeholder="How to Submit Your Document Request">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">How to Submit — Steps</label>
                            <textarea name="landing_howto_steps" class="form-control" rows="5"
                                placeholder="One step per line…"><?= htmlspecialchars($settings['landing_howto_steps'] ?? '') ?></textarea>
                            <div style="font-size:11px; color:#999; margin-top:3px;">One step per line. Steps are numbered automatically on the homepage.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instructions Modal — Steps <span style="font-weight:400; color:#888;">(the "Read Instructions" popup)</span></label>
                            <textarea name="landing_instructions_steps" class="form-control" rows="8"
                                placeholder="One step per line…"><?= htmlspecialchars($settings['landing_instructions_steps'] ?? '') ?></textarea>
                            <div style="font-size:11px; color:#999; margin-top:3px;">One step per line. Shown as a numbered list in the modal.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instructions Modal — Warning Note <span style="font-weight:400; color:#888;">(yellow box at bottom)</span></label>
                            <textarea name="landing_instructions_note" class="form-control" rows="3"
                                placeholder="e.g. Requests with incomplete requirements will be deleted after 90 days…"><?= htmlspecialchars($settings['landing_instructions_note'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Landing Page Content</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="bank">
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
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Bank Information</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="maintenance">
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
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn-save" style="background:#e74c3c;"><i class="fas fa-save me-2"></i>Save Maintenance Mode</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="email">
            <div class="section-card">
                <div class="section-header"><i class="fas fa-envelope me-2" style="color:#3498db;"></i>Email Notifications (Brevo API)</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#f0f4ff; border-radius:6px; border-left:4px solid #3498db;">
                        <i class="fas fa-info-circle me-1"></i>
                        Sends email through Brevo's HTTPS API (not SMTP), so it works on hosts like Railway that block outbound SMTP ports.
                        Leave the API key blank to disable email sending without breaking other features.
                        <br><strong>Where to get this:</strong> Brevo dashboard → <em>SMTP &amp; API</em> → <em>API keys &amp; MCP</em> tab → Generate API key.
                        This is <strong>not</strong> the same as an SMTP key.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label">
                                Brevo API Key
                                <span style="font-weight:400; color:#888;">(leave blank to keep current)</span>
                            </label>
                            <div style="position:relative;">
                                <input type="password" name="brevo_api_key" id="brevoApiKeyInput" class="form-control"
                                    placeholder="<?= !empty($settings['brevo_api_key']) ? '••••••••••••' : 'xkeysib-...' ?>"
                                    autocomplete="new-password">
                                <i class="fas fa-eye" id="toggleBrevoKey"
                                   style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#aaa;font-size:13px;"></i>
                            </div>
                            <?php if (!empty($settings['brevo_api_key'])): ?>
                                <div style="font-size:11px; color:#27ae60; margin-top:3px;">
                                    <i class="fas fa-check-circle me-1"></i>API key is saved. Leave blank to keep it.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3" style="display:flex; align-items:flex-end;">
                            <button type="button" id="btnTestBrevo" class="btn-save w-100" style="background:#3498db; padding:7px 12px;">
                                <i class="fas fa-plug me-1"></i> Test
                            </button>
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
                            <div style="font-size:11px; color:#888; margin-top:3px;">
                                Must be a verified sender in your Brevo account (Senders &amp; IP → Senders).
                            </div>
                        </div>
                    </div>

                    <div id="brevoTestResult" style="display:none; margin-top:14px;"></div>

                    <div class="mt-3">
                        <button type="submit" class="btn-save" style="background:#3498db;"><i class="fas fa-save me-2"></i>Save Email Settings</button>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="legal">
            <div class="section-card">
                <div class="section-header"><i class="fas fa-gavel me-2" style="color:#6D1A1A;"></i>Legal / Policy Pages</div>
                <div class="section-body">
                    <div style="font-size:12px; color:#888; margin-bottom:16px; padding:10px 14px; background:#fff5f5; border-radius:6px; border-left:4px solid #6D1A1A;">
                        <i class="fas fa-info-circle me-1"></i>
                        These texts appear in the <strong>Terms of Use</strong> and <strong>Privacy Statement</strong> modals accessible from the footer of all student-facing pages.
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Terms of Use</label>
                            <textarea name="terms_of_use" class="form-control" rows="6"
                                placeholder="Enter your Terms of Use policy text here…"><?= htmlspecialchars($settings['terms_of_use'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Privacy Statement</label>
                            <textarea name="privacy_statement" class="form-control" rows="6"
                                placeholder="Enter your Privacy Statement text here…"><?= htmlspecialchars($settings['privacy_statement'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Legal Pages</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleBrevoKey')?.addEventListener('click', function () {
    const inp = document.getElementById('brevoApiKeyInput');
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});

document.getElementById('btnTestBrevo')?.addEventListener('click', function () {
    const apiKey = document.querySelector('[name=brevo_api_key]').value.trim();
    const result = document.getElementById('brevoTestResult');

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing…';
    result.style.display = 'none';

    const params = new URLSearchParams({ action: 'test_brevo', api_key: apiKey });
    fetch('brevo_test.php', { method: 'POST', body: params })
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