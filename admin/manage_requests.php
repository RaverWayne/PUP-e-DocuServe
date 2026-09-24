<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once '../includes/mailer.php';
require_once '../includes/release_date.php';
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error   = '';

// Handle request update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security check failed. Please go back, refresh the page, and try again.');
    }

    $request_id           = intval($_POST['request_id'] ?? 0);
    $request_status       = $_POST['request_status'] ?? '';
    $payment_status       = $_POST['payment_status'] ?? '';
    $tentative_release    = $_POST['tentative_release_date'] ?? '';
    $admin_notes          = trim($_POST['admin_notes'] ?? '');
    $custom_requirements  = trim($_POST['custom_requirements'] ?? '');

    $date_verified = ($payment_status === 'Paid') ? date('Y-m-d') : null;

    // Old status
    $old = $pdo->prepare("SELECT request_status, u.first_name, u.email,
                                  r.control_number, r.tentative_release_date,
                                  GROUP_CONCAT(d.document_name SEPARATOR ', ') AS documents
                           FROM requests r
                           LEFT JOIN users u ON r.user_id = u.id
                           LEFT JOIN request_items ri ON r.id = ri.request_id
                           LEFT JOIN documents d ON ri.document_id = d.id
                           WHERE r.id = ? GROUP BY r.id");
    $old->execute([$request_id]);
    $oldReq = $old->fetch();

    $stmt = $pdo->prepare("
        UPDATE requests
        SET request_status = ?, payment_status = ?,
            tentative_release_date = ?,
            admin_notes = ?, date_verified = ?,
            custom_requirements = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $request_status, $payment_status,
        $tentative_release ?: null,
        $admin_notes, $date_verified,
        $custom_requirements ?: null,
        $request_id
    ]);

    // Log request history for SLA compliance (Wave 3)
    if ($oldReq && $oldReq['request_status'] !== $request_status) {
        $pdo->prepare("INSERT INTO request_history (request_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$request_id, $oldReq['request_status'], $request_status, $admin_name, $admin_notes ?: 'Status updated by admin']);
    }

    // Notify student on status change
    if ($oldReq && $oldReq['request_status'] !== $request_status && !empty($oldReq['email'])) {
        $html = emailRequestStatusUpdate(
            $oldReq['first_name'],
            $oldReq['control_number'],
            $request_status,
            $oldReq['documents'] ?? 'N/A',
            $admin_notes,
            $custom_requirements ?: 'No requirements needed',
            $tentative_release ?: $oldReq['tentative_release_date']
        );
        sendMail($pdo, $oldReq['email'], $oldReq['first_name'],
            'Your Request ' . $oldReq['control_number'] . ' has been updated — PUP e-DocuServe',
            $html);
    }

    $success = "Request updated successfully.";
}

// Filter
$filter = $_GET['filter'] ?? 'All';
$search = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if ($filter !== 'All') {
    $where[]  = "r.request_status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $where[]  = "(r.control_number LIKE ? OR u.last_name LIKE ? OR u.first_name LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$requests = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.course, u.student_number,
           GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN request_items ri ON r.id = ri.request_id
    LEFT JOIN documents d ON ri.document_id = d.id
    $whereSQL
    GROUP BY r.id
    ORDER BY r.date_filed DESC
");
$requests->execute($params);
$requests = $requests->fetchAll();

// Fetch single request for manage modal
$manage_req   = null;
$manage_items = [];
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, u.course, u.student_number, u.email, u.mobile_number FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $manage_req = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT ri.*, d.document_name, d.price FROM request_items ri LEFT JOIN documents d ON ri.document_id = d.id WHERE ri.request_id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $manage_items = $stmt->fetchAll();
}

$filters = ['All', 'Pending', 'Processing', 'Ready for Pickup', 'Claimed', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests | PUP e-DocuServe</title>
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
        .sidebar-footer a { color: #f66; text-decoration: none; font-size: 12px; }
        .walkin-badge { background: #f39c12; color: white; padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; }

        .main-content { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--pup-red); }
        .page-content { padding: 24px; flex: 1; }

        .filter-bar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
        .filter-btn { background: #f0f0f0; border: 1px solid #ccc; color: #555; padding: 6px 14px; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .filter-btn:hover, .filter-btn.active { background: var(--pup-red); color: white; border-color: var(--pup-red); }

        .search-box { margin-left: auto; display: flex; gap: 6px; }
        .search-box input { font-size: 13px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; width: 220px; }
        .search-box input:focus { outline: none; border-color: var(--pup-red); }
        .search-box button { background: var(--pup-red); color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; }

        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #f8f8f8; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .btn-sm-action { padding: 4px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-manage { background: var(--pup-red); color: white; }
        .btn-manage:hover { background: #8B2020; color: white; }

        .modal-header { background: var(--pup-red); color: white; }
        .modal-header .btn-close { filter: invert(1); }

        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(139,0,0,0.1); }

        .detail-row { display: flex; padding: 7px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { width: 150px; min-width: 150px; font-weight: 600; color: #555; }

        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th { background: #f0f0f0; padding: 6px 10px; font-weight: 600; border: 1px solid #ddd; }
        .items-table td { padding: 6px 10px; border: 1px solid #eee; }
        .items-table tfoot td { background: #f8f8f8; font-weight: 700; }

        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">PUP e-DocuServe <span>Biñan Campus</span></div>
    </div>
    <div class="sidebar-role"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php" class="active"><i class="fas fa-file-alt"></i> Manage Requests</a>
        <a href="walkin_requests.php"><i class="fas fa-walking"></i> Walk-in Payments</a>
        <div class="nav-section">Users</div>
        <a href="students.php"><i class="fas fa-users"></i> Students</a>
        <div class="nav-section">Account</div>
        <a href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="font-size:11px; opacity:0.8;">PUP e-DocuServe v1.0</div>
        <div style="font-size:11px; color:#888;">Biñan Campus</div>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-file-alt me-2" style="color:var(--pup-red);"></i>Manage Requests</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- FILTERS -->
        <form method="GET" action="">
            <div class="filter-bar">
                <?php foreach ($filters as $f): ?>
                    <a href="?filter=<?= urlencode($f) ?>&search=<?= urlencode($search) ?>"
                       class="filter-btn <?= $filter === $f ? 'active' : '' ?>"><?= $f ?></a>
                <?php endforeach; ?>
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search name or control no." value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>

        <!-- TABLE -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-list me-2" style="color:var(--pup-red);"></i>
                Requests
                <span style="font-weight:400; font-size:12px; color:#888; margin-left:8px;">(<?= count($requests) ?> records)</span>
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
                            <th>SLA Status</th>
                            <th>Date Filed</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="9" style="text-align:center; color:#aaa; padding:30px;">No requests found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['control_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?>
                                <?php if ($r['student_number']): ?>
                                    <div style="font-size:11px; color:#888;"><?= htmlspecialchars($r['student_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($r['documents'] ?? 'N/A') ?>
                            </td>
                            <td><?= htmlspecialchars($r['purpose']) ?></td>
                            <td>
                                <?php
                                $pmap = ['Unpaid' => ['danger','✗ Unpaid'], 'Pending Verification' => ['warning text-dark','⏳ Pending'], 'Paid' => ['success','✔ Paid']];
                                $pb = $pmap[$r['payment_status']] ?? ['secondary', $r['payment_status']];
                                ?>
                                <span class="badge bg-<?= $pb[0] ?>"><?= $pb[1] ?></span>
                                <?php if ($r['bank_slip_path'] === 'walkin'): ?>
                                    <span class="walkin-badge">Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $smap = ['Pending'=>'secondary','Processing'=>'primary','Ready for Pickup'=>'success','Claimed'=>'dark','Cancelled'=>'danger'];
                                $sc = $smap[$r['request_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sc ?>"><?= $r['request_status'] ?></span>
                            </td>
                            <td>
                                <?php
                                $working_days = calculateWorkingDays($r['date_filed']);
                                if ($r['request_status'] === 'Claimed') {
                                    echo '<span class="badge bg-secondary" title="Completed"><i class="fas fa-check-circle me-1"></i> Completed (' . $working_days . 'd)</span>';
                                } elseif ($r['request_status'] === 'Cancelled') {
                                    echo '<span class="badge bg-secondary">Cancelled</span>';
                                } elseif ($working_days <= 5) {
                                    echo '<span class="badge bg-success" title="' . $working_days . ' working days elapsed (SLA: 5 days)"><i class="fas fa-clock me-1"></i> On Track (' . $working_days . 'd)</span>';
                                } else {
                                    echo '<span class="badge bg-danger" title="' . $working_days . ' working days elapsed (SLA: 5 days)"><i class="fas fa-exclamation-triangle me-1"></i> Delayed (' . $working_days . 'd)</span>';
                                }
                                ?>
                            </td>
                            <td><?= date('m/d/Y', strtotime($r['date_filed'])) ?></td>
                            <td style="text-align:center;">
                                <button class="btn-sm-action btn-manage"
                                    onclick="openManage(<?= htmlspecialchars(json_encode(array_merge($r, ['working_days' => $working_days]))) ?>, <?= htmlspecialchars(json_encode([])) ?>)">
                                    <i class="fas fa-edit"></i> Manage
                                </button>
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

<!-- MANAGE MODAL -->
<div class="modal fade" id="manageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-edit me-2"></i>Manage Request</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="request_id" id="mgmt_id">

                    <!-- Student Info -->
                    <div class="mb-3 p-3" style="background:#fafafa; border-radius:6px; border:1px solid #eee;">
                        <div style="font-weight:700; margin-bottom:8px; color:var(--pup-red);">Student Information</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="detail-row"><div class="detail-label">Name:</div><div id="mgmt_name"></div></div>
                                <div class="detail-row"><div class="detail-label">Student No.:</div><div id="mgmt_stnum"></div></div>
                                <div class="detail-row"><div class="detail-label">Email:</div><div id="mgmt_email"></div></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row"><div class="detail-label">Mobile:</div><div id="mgmt_mobile"></div></div>
                                <div class="detail-row"><div class="detail-label">Course:</div><div id="mgmt_course"></div></div>
                                <div class="detail-row"><div class="detail-label">Purpose:</div><div id="mgmt_purpose"></div></div>
                                <div class="detail-row"><div class="detail-label">SLA Turnaround:</div><div id="mgmt_sla"></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents -->
                    <div class="mb-3">
                        <div style="font-weight:700; margin-bottom:6px;">Requested Documents</div>
                        <div id="mgmt_docs" style="font-size:13px; color:#555;"></div>
                    </div>

                    <!-- Bank Slip -->
                    <div class="mb-3" id="slipRow">
                        <div style="font-weight:700; margin-bottom:6px;">Bank Slip</div>
                        <div id="mgmt_slip"></div>
                    </div>

                    <!-- Admin Controls -->
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" id="mgmt_payment" class="form-select">
                                <option value="Unpaid">Unpaid</option>
                                <option value="Pending Verification">Pending Verification</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Request Status</label>
                            <select name="request_status" id="mgmt_status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Processing">Processing</option>
                                <option value="Ready for Pickup">Ready for Pickup</option>
                                <option value="Claimed">Claimed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Tentative Release Date
                                <span style="font-weight:400; color:#888;">(override)</span>
                            </label>
                            <input type="date" name="tentative_release_date" id="mgmt_release" class="form-control">
                            <div style="font-size:11px; color:#888; margin-top:3px;">
                                Auto-calculated: <strong id="mgmt_release_hint" style="color:#555;">—</strong>
                                <span style="color:#aaa;">(edit to override)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="mgmt_total" class="form-control" readonly style="background:#f5f5f5;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admin Notes <span style="font-weight:400; color:#888;">(visible to student)</span></label>
                            <textarea name="admin_notes" id="mgmt_notes" class="form-control" rows="3" placeholder="Add notes for the student..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                Requirements from Student
                                <span style="font-weight:400; color:#888;">(visible to student)</span>
                            </label>
                            <textarea name="custom_requirements" id="mgmt_requirements" class="form-control" rows="2"
                                placeholder="e.g. Present original receipt, bring valid ID... (leave blank for default)"></textarea>
                            <div style="font-size:11px; color:#888; margin-top:3px;">
                                If blank, student will see: <em>"No requirements needed."</em>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--pup-red); color:white;">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openManage(req) {
    document.getElementById('mgmt_id').value      = req.id;
    document.getElementById('mgmt_name').textContent   = req.last_name + ', ' + req.first_name;
    document.getElementById('mgmt_stnum').textContent  = req.student_number || 'N/A';
    document.getElementById('mgmt_email').textContent  = req.email || 'N/A';
    document.getElementById('mgmt_mobile').textContent = req.mobile_number || 'N/A';
    document.getElementById('mgmt_course').textContent = req.course || 'N/A';
    document.getElementById('mgmt_purpose').textContent = req.purpose || 'N/A';

    // SLA turnaround display
    const days = req.working_days || 0;
    let slaHtml = '';
    if (req.request_status === 'Claimed') {
        slaHtml = `<span class="badge bg-secondary"><i class="fas fa-check-circle me-1"></i> Completed (${days} working days)</span>`;
    } else if (req.request_status === 'Cancelled') {
        slaHtml = `<span class="badge bg-secondary">Cancelled</span>`;
    } else if (days <= 5) {
        slaHtml = `<span class="badge bg-success"><i class="fas fa-clock me-1"></i> On Track (${days} / 5 working days)</span>`;
    } else {
        slaHtml = `<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> Delayed (${days} working days — exceeds 5d SLA)</span>`;
    }
    document.getElementById('mgmt_sla').innerHTML = slaHtml;

    document.getElementById('mgmt_docs').textContent   = req.documents || 'N/A';
    document.getElementById('mgmt_total').value        = '₱ ' + parseFloat(req.total_amount).toFixed(2);
    document.getElementById('mgmt_notes').value        = req.admin_notes || '';
    document.getElementById('mgmt_requirements').value = req.custom_requirements || '';
    document.getElementById('mgmt_release').value = req.tentative_release_date || '';

    // Release date hint
    const hint = req.tentative_release_date
        ? new Date(req.tentative_release_date).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
        : '—';
    document.getElementById('mgmt_release_hint').textContent = hint;

    document.getElementById('mgmt_payment').value = req.payment_status;
    document.getElementById('mgmt_status').value  = req.request_status;

    // Bank slip
    let slipHTML = '';
    if (req.bank_slip_path === 'walkin') {
        slipHTML = '<span style="color:#e67e22;"><i class="fas fa-walking me-1"></i>Student chose to walk in with physical slip</span>';
    } else if (req.bank_slip_path) {
        slipHTML = `<a href="../assets/uploads/${req.bank_slip_path}" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:12px;"><i class="fas fa-eye me-1"></i>View Uploaded Slip</a>`;
    } else {
        slipHTML = '<span style="color:#aaa;">No slip uploaded yet.</span>';
    }
    document.getElementById('mgmt_slip').innerHTML = slipHTML;

    new bootstrap.Modal(document.getElementById('manageModal')).show();
}
</script>
</body>
</html>