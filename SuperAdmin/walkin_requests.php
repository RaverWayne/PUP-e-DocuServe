<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';

// Confirm walk-in payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE requests SET payment_status = 'Paid', date_verified = ? WHERE id = ? AND bank_slip_path = 'walkin'");
    $stmt->execute([date('Y-m-d'), $request_id]);
    $success = "Walk-in payment confirmed successfully.";
}

// Fetch walk-in requests
$walkins = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, u.student_number, u.course, u.mobile_number,
           GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN request_items ri ON r.id = ri.request_id
    LEFT JOIN documents d ON ri.document_id = d.id
    WHERE r.bank_slip_path = 'walkin'
    GROUP BY r.id
    ORDER BY r.date_filed DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Walk-in Payments | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #8B0000; }
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
        .sidebar-footer a { color: #f66; text-decoration: none; }
        .main-content { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--pup-red); }
        .page-content { padding: 24px; flex: 1; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #f8f8f8; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .btn-confirm { background: #27ae60; color: white; border: none; padding: 5px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: background 0.2s; }
        .btn-confirm:hover { background: #219a52; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
        .modal-header { background: var(--pup-red); color: white; }
        .modal-header .btn-close { filter: invert(1); }
    </style>
</head>
<body>

<?php $current_page = 'walkin'; include 'sidebar.php'; ?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-walking me-2" style="color:var(--pup-red);"></i>Walk-in Payments</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-walking me-2" style="color:var(--pup-red);"></i>
                Students who will present bank slip in person
                <span style="font-weight:400; font-size:12px; color:#888; margin-left:8px;">(<?= count($walkins) ?> pending)</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Control Number</th>
                            <th>Student</th>
                            <th>Documents</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Date Filed</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($walkins)): ?>
                            <tr><td colspan="7" style="text-align:center; color:#aaa; padding:30px;">No walk-in payment requests.</td></tr>
                        <?php else: ?>
                        <?php foreach ($walkins as $w): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($w['control_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($w['last_name'] . ', ' . $w['first_name']) ?>
                                <?php if ($w['student_number']): ?>
                                    <div style="font-size:11px; color:#888;"><?= htmlspecialchars($w['student_number']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:11px; color:#888;"><?= htmlspecialchars($w['mobile_number']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($w['documents'] ?? 'N/A') ?></td>
                            <td>₱ <?= number_format($w['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($w['payment_status'] === 'Paid'): ?>
                                    <span class="badge bg-success">✔ Paid</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">⏳ Pending Verification</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('m/d/Y', strtotime($w['date_filed'])) ?></td>
                            <td style="text-align:center;">
                                <?php if ($w['payment_status'] !== 'Paid'): ?>
                                    <button class="btn-confirm"
                                        onclick="confirmWalkin(<?= $w['id'] ?>, '<?= htmlspecialchars($w['control_number']) ?>', '<?= htmlspecialchars($w['last_name'] . ', ' . $w['first_name']) ?>')">
                                        <i class="fas fa-check me-1"></i> Confirm Payment
                                    </button>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:12px;">Confirmed</span>
                                <?php endif; ?>
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

<!-- CONFIRM MODAL -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-check-circle me-2"></i>Confirm Walk-in Payment</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="request_id" id="confirmReqId">
                    <p>Control Number: <strong id="confirmCtrl"></strong></p>
                    <p>Student: <strong id="confirmName"></strong></p>
                    <div class="alert alert-info py-2" style="font-size:12px;">
                        <i class="fas fa-info-circle me-1"></i>
                        Confirm that this student has physically presented their bank payment slip at the Registrar's Office.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#27ae60; color:white;">
                        <i class="fas fa-check me-1"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmWalkin(id, ctrl, name) {
    document.getElementById('confirmReqId').value       = id;
    document.getElementById('confirmCtrl').textContent  = ctrl;
    document.getElementById('confirmName').textContent  = name;
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}
</script>
</body>
</html>
