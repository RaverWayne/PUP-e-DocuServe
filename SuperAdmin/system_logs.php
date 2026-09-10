<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$search     = trim($_GET['search'] ?? '');
$params     = [];
$where      = '';

if (!empty($search)) {
    $where    = "WHERE action LIKE ? OR performed_by LIKE ? OR target LIKE ?";
    $s        = "%$search%";
    $params   = [$s, $s, $s];
}

$stmt = $pdo->prepare("SELECT * FROM system_logs $where ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$current_page = 'logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs | PUP e-DocuServe</title>
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
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; background: #f8f8f8; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .search-box { display: flex; gap: 6px; }
        .search-box input { font-size: 13px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; width: 260px; }
        .search-box input:focus { outline: none; border-color: var(--sa-color); }
        .search-box button { background: var(--sa-color); color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-history me-2" style="color:var(--sa-color);"></i>System Logs</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">
        <div class="section-card">
            <div class="section-header">
                <span>Activity Logs <span style="font-weight:400; font-size:12px; color:#888;">(last 200 entries)</span></span>
                <form method="GET">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search action, user, target..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Action</th>
                            <th>Performed By</th>
                            <th>Role</th>
                            <th>Target</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" style="text-align:center; color:#aaa; padding:30px;">No logs found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td style="color:#aaa;"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($log['performed_by']) ?></strong>
                                <?php if (!empty($log['performed_by_id'])): ?>
                                    <div style="font-size:11px; color:#aaa;">ID: <?= intval($log['performed_by_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['performed_by_role'])): ?>
                                    <?php if ($log['performed_by_role'] === 'superadmin'): ?>
                                        <span class="badge" style="background:var(--sa-color); font-size:10px;">Super Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" style="font-size:10px;">Admin</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#888;"><?= htmlspecialchars($log['target'] ?? '-') ?></td>
                            <td style="color:#888; font-size:12px; white-space:nowrap;"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
