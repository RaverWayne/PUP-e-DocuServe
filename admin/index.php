<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Summary counts
$total     = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$pending   = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Pending'")->fetchColumn();
$processing = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Processing'")->fetchColumn();
$ready     = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Ready for Pickup'")->fetchColumn();
$claimed   = $pdo->query("SELECT COUNT(*) FROM requests WHERE request_status = 'Claimed'")->fetchColumn();
$walkin    = $pdo->query("SELECT COUNT(*) FROM requests WHERE bank_slip_path = 'walkin' AND payment_status = 'Pending Verification'")->fetchColumn();
$unpaid    = $pdo->query("SELECT COUNT(*) FROM requests WHERE payment_status = 'Unpaid'")->fetchColumn();
$pending_payment = $pdo->query("SELECT COUNT(*) FROM requests WHERE payment_status = 'Pending Verification'")->fetchColumn();

// Recent requests
$recent = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, u.course,
           GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN request_items ri ON r.id = ri.request_id
    LEFT JOIN documents d ON ri.document_id = d.id
    GROUP BY r.id
    ORDER BY r.date_filed DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }

        /* SIDEBAR */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 220px; height: 100vh;
            background: #1a1a1a; color: white;
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            padding: 16px 16px 12px;
            border-bottom: 1px solid #333;
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-brand img { height: 36px; }
        .sidebar-brand-text { font-size: 12px; font-weight: 700; line-height: 1.3; color: white; }
        .sidebar-brand-text span { display: block; font-size: 10px; font-weight: 400; opacity: 0.7; }

        .sidebar-role {
            padding: 10px 16px;
            background: var(--pup-red);
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .sidebar-nav { flex: 1; padding: 10px 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; color: #ccc;
            text-decoration: none; font-size: 13px;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover { background: #2a2a2a; color: white; }
        .sidebar-nav a.active { background: var(--pup-red); color: white; }
        .sidebar-nav a i { width: 16px; text-align: center; }

        .sidebar-nav .nav-section {
            padding: 8px 16px 4px;
            font-size: 10px; color: #666;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid #333;
            font-size: 12px; color: #888;
        }
        .sidebar-footer a { color: #f66; text-decoration: none; font-size: 12px; }
        .sidebar-footer a:hover { color: #ff8888; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 220px;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }

        .topbar {
            background: white; border-bottom: 1px solid #ddd;
            padding: 10px 24px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 99;
        }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--pup-red); }

        .page-content { padding: 24px; flex: 1; }

        /* CARDS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: white; border-radius: 8px;
            padding: 18px 20px;
            border-left: 4px solid #ddd;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 16px;
        }
        .summary-card .card-icon {
            width: 44px; height: 44px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .summary-card .card-info { flex: 1; }
        .summary-card .card-num { font-size: 24px; font-weight: 700; color: #333; line-height: 1; }
        .summary-card .card-label { font-size: 11px; color: #888; margin-top: 3px; }

        .card-red { border-left-color: var(--pup-red); }
        .card-red .card-icon { background: var(--pup-red); }
        .card-orange { border-left-color: #e67e22; }
        .card-orange .card-icon { background: #e67e22; }
        .card-blue { border-left-color: #3498db; }
        .card-blue .card-icon { background: #3498db; }
        .card-green { border-left-color: #27ae60; }
        .card-green .card-icon { background: #27ae60; }
        .card-purple { border-left-color: #8e44ad; }
        .card-purple .card-icon { background: #8e44ad; }
        .card-dark { border-left-color: #555; }
        .card-dark .card-icon { background: #555; }
        .card-yellow { border-left-color: #f39c12; }
        .card-yellow .card-icon { background: #f39c12; }
        .card-teal { border-left-color: #16a085; }
        .card-teal .card-icon { background: #16a085; }

        /* TABLES */
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #f8f8f8; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; text-align: left; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }

        .btn-sm-action {
            padding: 4px 10px; border-radius: 4px; font-size: 11px;
            border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
            transition: all 0.2s;
        }
        .btn-manage { background: var(--pup-red); color: white; }
        .btn-manage:hover { background: #8B2020; color: white; }
        .btn-view { background: #5a9fd4; color: white; }
        .btn-view:hover { background: #4a8fc4; color: white; }

        .footer-admin {
            background: white; border-top: 1px solid #eee;
            padding: 12px 24px; text-align: center;
            font-size: 12px; color: #aaa;
        }

        .badge { font-size: 10px; }
        .walkin-badge { background: #f39c12; color: white; padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">
            PUP e-DocuServe
            <span>Biñan Campus</span>
        </div>
    </div>
    <div class="sidebar-role"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php"><i class="fas fa-file-alt"></i> Manage Requests
            <?php if ($pending > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $pending ?></span>
            <?php endif; ?>
        </a>
        <a href="walkin_requests.php"><i class="fas fa-walking"></i> Walk-in Payments
            <?php if ($walkin > 0): ?>
                <span class="walkin-badge ms-auto"><?= $walkin ?></span>
            <?php endif; ?>
        </a>
        <div class="nav-section">Users</div>
        <a href="students.php"><i class="fas fa-users"></i> Students</a>
        <div class="nav-section">Account</div>
        <a href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="margin-bottom:6px;">Logged in as <strong style="color:#ccc;"><?= htmlspecialchars($admin_name) ?></strong></div>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-tachometer-alt me-2" style="color:var(--pup-red);"></i>Dashboard</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">

        <!-- SUMMARY CARDS ROW 1 -->
        <div class="summary-grid">
            <div class="summary-card card-red">
                <div class="card-icon"><i class="fas fa-file-alt"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $total ?></div>
                    <div class="card-label">Total Requests</div>
                </div>
            </div>
            <div class="summary-card card-orange">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $pending ?></div>
                    <div class="card-label">Pending Requests</div>
                </div>
            </div>
            <div class="summary-card card-blue">
                <div class="card-icon"><i class="fas fa-spinner"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $processing ?></div>
                    <div class="card-label">Processing</div>
                </div>
            </div>
            <div class="summary-card card-green">
                <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $ready ?></div>
                    <div class="card-label">Ready for Pickup</div>
                </div>
            </div>
        </div>

        <!-- SUMMARY CARDS ROW 2 -->
        <div class="summary-grid" style="grid-template-columns: repeat(4,1fr); margin-top:-8px;">
            <div class="summary-card card-dark">
                <div class="card-icon"><i class="fas fa-box"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $claimed ?></div>
                    <div class="card-label">Claimed</div>
                </div>
            </div>
            <div class="summary-card card-yellow">
                <div class="card-icon"><i class="fas fa-walking"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $walkin ?></div>
                    <div class="card-label">Walk-in Pending</div>
                </div>
            </div>
            <div class="summary-card card-purple">
                <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $pending_payment ?></div>
                    <div class="card-label">Pending Verification</div>
                </div>
            </div>
            <div class="summary-card card-teal">
                <div class="card-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="card-info">
                    <div class="card-num"><?= $unpaid ?></div>
                    <div class="card-label">Unpaid</div>
                </div>
            </div>
        </div>

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
                            <th>Control Number</th>
                            <th>Student</th>
                            <th>Documents</th>
                            <th>Purpose</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="8" style="text-align:center; color:#aaa; padding:30px;">No requests yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['control_number']) ?></strong></td>
                            <td><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                            <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($r['documents'] ?? 'N/A') ?>
                            </td>
                            <td><?= htmlspecialchars($r['purpose']) ?></td>
                            <td>
                                <?php
                                $pmap = [
                                    'Unpaid'               => ['danger', '✗ Unpaid'],
                                    'Pending Verification' => ['warning text-dark', '⏳ Pending'],
                                    'Paid'                 => ['success', '✔ Paid'],
                                ];
                                $pb = $pmap[$r['payment_status']] ?? ['secondary', $r['payment_status']];
                                ?>
                                <span class="badge bg-<?= $pb[0] ?>"><?= $pb[1] ?></span>
                                <?php if ($r['bank_slip_path'] === 'walkin'): ?>
                                    <span class="walkin-badge">Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $smap = [
                                    'Pending'          => 'secondary',
                                    'Processing'       => 'primary',
                                    'Ready for Pickup' => 'success',
                                    'Claimed'          => 'dark',
                                    'Cancelled'        => 'danger',
                                ];
                                $sc = $smap[$r['request_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sc ?>"><?= $r['request_status'] ?></span>
                            </td>
                            <td><?= date('m/d/Y', strtotime($r['date_filed'])) ?></td>
                            <td>
                                <a href="manage_requests.php?id=<?= $r['id'] ?>" class="btn-sm-action btn-manage">
                                    <i class="fas fa-edit"></i> Manage
                                </a>
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
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
