<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($title) || empty($content)) {
            $error = "Title and content are required.";
        } else {
            $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)")
                ->execute([$title, $content, $admin_name]);
            $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
                ->execute(["Posted announcement: $title", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], $title]);
            $success = "Announcement posted successfully.";
        }
    }

    if ($action === 'toggle') {
        $id     = intval($_POST['ann_id']);
        $status = intval($_POST['new_status']);
        $pdo->prepare("UPDATE announcements SET is_active = ? WHERE id = ?")->execute([$status, $id]);
        $success = "Announcement status updated.";
    }

    if ($action === 'delete') {
        $id = intval($_POST['ann_id']);
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
            ->execute(["Deleted announcement ID $id", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], "Announcement ID $id"]);
        $success = "Announcement deleted.";
    }
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$current_page  = 'announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements | PUP e-DocuServe</title>
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
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .btn-post { background: var(--sa-color); color: white; border: none; padding: 8px 24px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-post:hover { background: #283593; }
        .ann-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 16px 20px; margin-bottom: 14px; border-left: 4px solid var(--sa-color); }
        .ann-card.inactive { border-left-color: #ccc; opacity: 0.7; }
        .ann-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 6px; }
        .ann-content { color: #555; font-size: 13px; line-height: 1.6; margin-bottom: 10px; }
        .ann-meta { font-size: 11px; color: #aaa; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .btn-sm-action { padding: 4px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
        .btn-disable { background: #e74c3c; color: white; }
        .btn-enable { background: #27ae60; color: white; }
        .btn-delete { background: #777; color: white; }
        .btn-delete:hover { background: #555; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-bullhorn me-2" style="color:var(--sa-color);"></i>Announcements</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- POST FORM -->
        <div class="section-card">
            <div class="section-header">Post New Announcement</div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Title <span style="color:var(--pup-red);">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Announcement title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span style="color:var(--pup-red);">*</span></label>
                        <textarea name="content" class="form-control" rows="4" placeholder="Write the announcement content here..." required></textarea>
                    </div>
                    <div style="font-size:11px; color:#888; margin-bottom:12px;">
                        <i class="fas fa-info-circle me-1"></i> Active announcements will be visible to students on their dashboard.
                    </div>
                    <button type="submit" class="btn-post"><i class="fas fa-bullhorn me-2"></i>Post Announcement</button>
                </form>
            </div>
        </div>

        <!-- ANNOUNCEMENTS LIST -->
        <div class="section-card">
            <div class="section-header">All Announcements (<?= count($announcements) ?>)</div>
            <div class="section-body">
                <?php if (empty($announcements)): ?>
                    <div style="text-align:center; color:#aaa; padding:30px;">No announcements yet.</div>
                <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="ann-card <?= !$ann['is_active'] ? 'inactive' : '' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div style="flex:1;">
                            <div class="ann-title">
                                <?= htmlspecialchars($ann['title']) ?>
                                <?php if ($ann['is_active']): ?>
                                    <span class="badge bg-success ms-2" style="font-size:10px;">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2" style="font-size:10px;">Hidden</span>
                                <?php endif; ?>
                            </div>
                            <div class="ann-content"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                            <div class="ann-meta">
                                <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($ann['created_by']) ?></span>
                                <span><i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?></span>
                            </div>
                        </div>
                        <div style="display:flex; gap:5px; flex-shrink:0; margin-top:2px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $ann['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn-sm-action <?= $ann['is_active'] ? 'btn-disable' : 'btn-enable' ?>">
                                    <i class="fas fa-<?= $ann['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    <?= $ann['is_active'] ? 'Hide' : 'Show' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                                <button type="submit" class="btn-sm-action btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
