<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

// Summary counts
$total           = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$pending         = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Pending'")->fetchColumn();
$processing      = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Processing'")->fetchColumn();
$ready           = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Ready for Pickup'")->fetchColumn();
$claimed         = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Claimed'")->fetchColumn();
$walkin          = $pdo->query("SELECT COUNT(*) FROM requests WHERE bank_slip_path = 'walkin' AND payment_status = 'Pending Verification'")->fetchColumn();
$unpaid          = $pdo->query("SELECT COUNT(*) FROM requests WHERE payment_status = 'Unpaid'")->fetchColumn();
$pending_payment = $pdo->query("SELECT COUNT(*) FROM requests WHERE payment_status = 'Pending Verification'")->fetchColumn();
$total_students  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admins    = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn();
$total_docs      = $pdo->query("SELECT COUNT(*) FROM documents WHERE is_active = 1")->fetchColumn();
$requests_today  = $pdo->query("SELECT COUNT(*) FROM requests WHERE DATE(date_filed) = CURDATE()")->fetchColumn();

// Turnaround time and SLA metrics
$avg_tat = $pdo->query("
    SELECT ROUND(AVG(DATEDIFF(COALESCE(date_released, updated_at), date_filed)), 1)
    FROM requests
    WHERE request_status IN ('Claimed', 'Ready for Pickup')
")->fetchColumn();
$avg_tat = ($avg_tat !== null && $avg_tat !== false) ? $avg_tat : 0;

$sla_stats = $pdo->query("
    SELECT
        COUNT(*) as total_with_target,
        SUM(CASE WHEN DATE(COALESCE(date_released, updated_at)) <= tentative_release_date THEN 1 ELSE 0 END) as on_time
    FROM requests
    WHERE request_status IN ('Claimed', 'Ready for Pickup') AND tentative_release_date IS NOT NULL
")->fetch();
$sla_rate = ($sla_stats && $sla_stats['total_with_target'] > 0)
    ? round(($sla_stats['on_time'] / $sla_stats['total_with_target']) * 100)
    : 100;

$overdue_count = $pdo->query("
    SELECT COUNT(*) FROM requests
    WHERE request_status IN ('Pending', 'Processing')
      AND tentative_release_date IS NOT NULL
      AND tentative_release_date < CURDATE()
")->fetchColumn();

$due_today_count = $pdo->query("
    SELECT COUNT(*) FROM requests
    WHERE request_status IN ('Pending', 'Processing')
      AND tentative_release_date = CURDATE()
")->fetchColumn();

// Recent requests
$recent = $pdo->query("
    SELECT r.*, u.first_name, u.last_name,
           GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN request_items ri ON r.id = ri.request_id
    LEFT JOIN documents d ON ri.document_id = d.id
    GROUP BY r.id
    ORDER BY r.date_filed DESC
    LIMIT 5
")->fetchAll();

// Recent logs
$logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | PUP e-DocuServe</title>
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
        .sidebar-role { padding: 10px 16px; background: var(--sa-color); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .sidebar-nav { flex: 1; padding: 10px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: #bbb; text-decoration: none; font-size: 12px; transition: all 0.2s; }
        .sidebar-nav a:hover { background: #1a1a1a; color: white; }
        .sidebar-nav a.active { background: var(--sa-color); color: white; }
        .sidebar-nav a i { width: 16px; text-align: center; font-size: 12px; }
        .sidebar-nav .nav-section { padding: 10px 16px 4px; font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-footer { padding: 12px 16px; border-top: 1px solid #222; font-size: 12px; color: #777; }
        .sidebar-footer a { color: #f66; text-decoration: none; font-size: 12px; }

        .main-content { margin-left: 230px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--sa-color); }
        .page-content { padding: 24px; flex: 1; }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px; }
        .summary-card { background: white; border-radius: 8px; padding: 16px 18px; border-left: 4px solid #ddd; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 14px; }
        .summary-card .card-icon { width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 17px; color: white; flex-shrink: 0; }
        .summary-card .card-num { font-size: 22px; font-weight: 700; color: #333; line-height: 1; }
        .summary-card .card-label { font-size: 11px; color: #888; margin-top: 3px; }

        .card-red { border-left-color: var(--pup-red); } .card-red .card-icon { background: var(--pup-red); }
        .card-orange { border-left-color: #e67e22; } .card-orange .card-icon { background: #e67e22; }
        .card-blue { border-left-color: #3498db; } .card-blue .card-icon { background: #3498db; }
        .card-green { border-left-color: #27ae60; } .card-green .card-icon { background: #27ae60; }
        .card-dark { border-left-color: #555; } .card-dark .card-icon { background: #555; }
        .card-yellow { border-left-color: #f39c12; } .card-yellow .card-icon { background: #f39c12; }
        .card-purple { border-left-color: #8e44ad; } .card-purple .card-icon { background: #8e44ad; }
        .card-teal { border-left-color: #16a085; } .card-teal .card-icon { background: #16a085; }
        .card-navy { border-left-color: var(--sa-color); } .card-navy .card-icon { background: var(--sa-color); }
        .card-gray { border-left-color: #95a5a6; } .card-gray .card-icon { background: #95a5a6; }
        .card-pink { border-left-color: #e91e63; } .card-pink .card-icon { background: #e91e63; }

        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; background: #f8f8f8; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: #fafafa; }

        .btn-sm-action { padding: 4px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-manage { background: var(--pup-red); color: white; }
        .btn-manage:hover { background: #8B2020; color: white; }
        .walkin-badge { background: #f39c12; color: white; padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php $current_page = 'dashboard'; include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-tachometer-alt me-2" style="color:var(--sa-color);"></i>Super Admin Dashboard</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <!-- ROW 1 -->
        <div class="summary-grid">
            <div class="summary-card card-red">
                <div class="card-icon"><i class="fas fa-file-alt"></i></div>
                <div><div class="card-num"><?= $total ?></div><div class="card-label">Total Requests</div></div>
            </div>
            <div class="summary-card card-orange">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <div><div class="card-num"><?= $pending ?></div><div class="card-label">Pending</div></div>
            </div>
            <div class="summary-card card-blue">
                <div class="card-icon"><i class="fas fa-spinner"></i></div>
                <div><div class="card-num"><?= $processing ?></div><div class="card-label">Processing</div></div>
            </div>
            <div class="summary-card card-green">
                <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                <div><div class="card-num"><?= $ready ?></div><div class="card-label">Ready for Pickup</div></div>
            </div>
        </div>

        <!-- ROW 2 -->
        <div class="summary-grid">
            <div class="summary-card card-dark">
                <div class="card-icon"><i class="fas fa-box"></i></div>
                <div><div class="card-num"><?= $claimed ?></div><div class="card-label">Claimed</div></div>
            </div>
            <div class="summary-card card-yellow">
                <div class="card-icon"><i class="fas fa-walking"></i></div>
                <div><div class="card-num"><?= $walkin ?></div><div class="card-label">Walk-in Pending</div></div>
            </div>
            <div class="summary-card card-purple">
                <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
                <div><div class="card-num"><?= $pending_payment ?></div><div class="card-label">Pending Verification</div></div>
            </div>
            <div class="summary-card card-teal">
                <div class="card-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div><div class="card-num"><?= $unpaid ?></div><div class="card-label">Unpaid</div></div>
            </div>
        </div>

        <!-- ROW 3 -->
        <div class="summary-grid">
            <div class="summary-card card-navy">
                <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                <div><div class="card-num"><?= $total_students ?></div><div class="card-label">Registered Students</div></div>
            </div>
            <div class="summary-card card-pink">
                <div class="card-icon"><i class="fas fa-user-shield"></i></div>
                <div><div class="card-num"><?= $total_admins ?></div><div class="card-label">Admin Accounts</div></div>
            </div>
            <div class="summary-card card-gray">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <div><div class="card-num"><?= $total_docs ?></div><div class="card-label">Active Documents</div></div>
            </div>
            <div class="summary-card card-orange">
                <div class="card-icon"><i class="fas fa-calendar-day"></i></div>
                <div><div class="card-num"><?= $requests_today ?></div><div class="card-label">Requests Today</div></div>
            </div>
        </div>

        <!-- SLA & PERFORMANCE METRICS -->
        <div class="summary-grid">
            <div class="summary-card" style="border-left-color: #2c3e50;">
                <div class="card-icon" style="background: #2c3e50;"><i class="fas fa-stopwatch"></i></div>
                <div><div class="card-num"><?= $avg_tat ?> <span style="font-size:12px; font-weight:normal; color:#888;">days</span></div><div class="card-label">Avg. Turnaround Time</div></div>
            </div>
            <div class="summary-card" style="border-left-color: #27ae60;">
                <div class="card-icon" style="background: #27ae60;"><i class="fas fa-award"></i></div>
                <div><div class="card-num"><?= $sla_rate ?>%</div><div class="card-label">SLA Compliance Rate</div></div>
            </div>
            <div class="summary-card" style="border-left-color: <?= $due_today_count > 0 ? '#e67e22' : '#95a5a6' ?>;">
                <div class="card-icon" style="background: <?= $due_today_count > 0 ? '#e67e22' : '#95a5a6' ?>;"><i class="fas fa-calendar-check"></i></div>
                <div><div class="card-num"><?= $due_today_count ?></div><div class="card-label">Requests Due Today</div></div>
            </div>
            <div class="summary-card" style="border-left-color: <?= $overdue_count > 0 ? '#c0392b' : '#27ae60' ?>;">
                <div class="card-icon" style="background: <?= $overdue_count > 0 ? '#c0392b' : '#27ae60' ?>;"><i class="fas <?= $overdue_count > 0 ? 'fa-exclamation-triangle' : 'fa-check' ?>"></i></div>
                <div><div class="card-num"><?= $overdue_count ?></div><div class="card-label">Overdue Target Date</div></div>
            </div>
        </div>

        <?php if ($overdue_count > 0): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3" role="alert" style="font-size:12px;">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div class="flex-grow-1">
                    <strong>SLA Alert:</strong> There are <strong><?= $overdue_count ?></strong> active requests exceeding their target release date.
                </div>
                <a href="manage_requests.php?status=Pending" class="btn btn-sm btn-outline-danger ms-2" style="font-size:11px;">Review Requests</a>
            </div>
        <?php endif; ?>

        <div class="two-col">
            <!-- RECENT REQUESTS -->
            <div class="section-card">
                <div class="section-header">
                    <span><i class="fas fa-history me-2" style="color:var(--pup-red);"></i>Recent Requests</span>
                    <a href="manage_requests.php" class="btn-sm-action btn-manage">View All</a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Student</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)): ?>
                                <tr><td colspan="4" style="text-align:center; color:#aaa; padding:20px;">No requests yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($recent as $r):
                                $pmap = ['Unpaid'=>['danger','✗'],'Pending Verification'=>['warning text-dark','⏳'],'Paid'=>['success','✔']];
                                $pb = $pmap[$r['payment_status']] ?? ['secondary','?'];
                                $smap = ['Pending'=>'secondary','Processing'=>'primary','Ready for Pickup'=>'success','Claimed'=>'dark','Cancelled'=>'danger'];
                                $sc = $smap[$r['request_status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td><strong style="font-size:12px;"><?= htmlspecialchars($r['control_number']) ?></strong></td>
                                <td><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                                <td><span class="badge bg-<?= $pb[0] ?>"><?= $pb[1] ?></span><?php if ($r['bank_slip_path']==='walkin'): ?> <span class="walkin-badge">WI</span><?php endif; ?></td>
                                <td><span class="badge bg-<?= $sc ?>"><?= $r['request_status'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RECENT LOGS -->
            <div class="section-card">
                <div class="section-header">
                    <span><i class="fas fa-history me-2" style="color:var(--sa-color);"></i>Recent System Logs</span>
                    <a href="system_logs.php" class="btn-sm-action" style="background:var(--sa-color); color:white;">View All</a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr><th>Action</th><th>By</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="3" style="text-align:center; color:#aaa; padding:20px;">No logs yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['performed_by']) ?></td>
                                <td style="font-size:11px; color:#888;"><?= date('m/d/Y H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
