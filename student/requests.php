<?php
session_start();
require_once '../config/db.php';
require_once 'csrf.php';

// Session timeout — 10 minutes
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$upload_success = false;
$upload_error   = '';
$cancel_success = false;
$cancel_error   = '';

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request_id'])) {
    csrf_verify();
    $cancel_id     = intval($_POST['cancel_request_id']);
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');

    if (empty($cancel_reason)) {
        $cancel_error = "Please provide a reason for cancellation.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ? AND user_id = ? AND payment_status = 'Unpaid'");
        $stmt->execute([$cancel_id, $user_id]);
        $to_cancel = $stmt->fetch();

        if (!$to_cancel) {
            $cancel_error = "This request cannot be cancelled.";
        } else {
            $pdo->prepare("UPDATE requests SET request_status = 'Cancelled', admin_notes = ? WHERE id = ? AND user_id = ?")
                ->execute(["Cancelled by student: " . $cancel_reason, $cancel_id, $user_id]);
            $student_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $pdo->prepare("INSERT INTO request_history (request_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$cancel_id, $to_cancel['request_status'], 'Cancelled', $student_name ?: 'Student', 'Cancelled by student: ' . $cancel_reason]);
            $cancel_success = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bank_slip'])) {
    csrf_verify();
    $request_id = intval($_POST['request_id'] ?? 0);
    $file       = $_FILES['bank_slip'];
    $allowed    = ['image/jpeg', 'image/jpg'];
    $max_size   = 10 * 1024 * 1024;

    // Double-check actual file extension too
    $ext_check  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user_id]);
    $req = $stmt->fetch();

    if (!$req) {
        $upload_error = "Invalid request.";
    } elseif (!in_array($file['type'], $allowed) || !in_array($ext_check, ['jpg', 'jpeg'])) {
        $upload_error = "Only JPG/JPEG files are allowed for bank slip uploads.";
    } elseif ((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'image/jpeg') {
        // Verify actual file content
        $upload_error = "The uploaded file's content doesn't match a valid JPG image.";
    } elseif ($file['size'] > $max_size) {
        $upload_error = "File size must not exceed 10MB.";
    } else {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'slip_' . $request_id . '_' . time() . '.' . $ext;
        $dest     = '../assets/uploads/' . $filename;

        if (!is_dir('../assets/uploads/')) {
            mkdir('../assets/uploads/', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("UPDATE requests SET bank_slip_path = ?, payment_status = 'Pending Verification' WHERE id = ? AND user_id = ?");
            $stmt->execute([$filename, $request_id, $user_id]);
            $student_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $pdo->prepare("INSERT INTO request_history (request_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$request_id, $req['request_status'], $req['request_status'], $student_name ?: 'Student', 'Bank slip uploaded by student']);
            $upload_success = true;
        } else {
            $upload_error = "Failed to upload. Please try again.";
        }
    }
}

$stmt = $pdo->prepare("
    SELECT r.*, GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents
    FROM requests r
    LEFT JOIN request_items ri ON r.id = ri.request_id
    LEFT JOIN documents d ON ri.document_id = d.id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.date_filed DESC
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();

// Fetch request history for all user requests (Wave 3)
$histStmt = $pdo->prepare("
    SELECT rh.*
    FROM request_history rh
    JOIN requests r ON rh.request_id = r.id
    WHERE r.user_id = ?
    ORDER BY rh.changed_at ASC
");
$histStmt->execute([$user_id]);
$all_histories = $histStmt->fetchAll(PDO::FETCH_ASSOC);

$request_histories = [];
foreach ($all_histories as $h) {
    $request_histories[$h['request_id']][] = $h;
}

function statusBadge($status) {
    $map = ['Pending' => 'secondary', 'Processing' => 'primary', 'Ready for Pickup' => 'success', 'Claimed' => 'dark', 'Cancelled' => 'danger'];
    $color = $map[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>{$status}</span>";
}

function paymentBadge($status) {
    $map = ['Unpaid' => ['danger','✗ Unpaid'], 'Pending Verification' => ['warning text-dark','⏳ Pending Verification'], 'Paid' => ['success','✔ YES']];
    $data = $map[$status] ?? ['secondary', $status];
    return "<span class='badge bg-{$data[0]}'>{$data[1]}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; }
        .navbar-pup { background-color: var(--pup-red); padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 999; }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.85; }
        .dropdown-toggle { background: none; border: none; color: white; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-toggle:focus { box-shadow: none; }
        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border-bottom: none; }
        .tabs-bar .nav-link { color: #555; font-size: 13px; padding: 12px 16px; border: none; border-bottom: 3px solid transparent; border-radius: 0; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .tabs-bar .nav-link:hover { color: var(--pup-red); }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; }
        .content-wrapper { max-width: 1100px; margin: 24px auto; padding: 0 16px 40px; }
        .section-card { background: white; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
        .section-header { background: #e8e8e8; padding: 10px 16px; font-weight: 600; font-size: 13px; color: #333; border-bottom: 1px solid #ddd; }
        .advisory-box { background: #ffe8e8; border: 1px solid #f5c0c0; border-radius: 6px; padding: 16px 20px; margin-bottom: 16px; text-align: center; }
        .advisory-box h6 { color: var(--pup-red); font-weight: 700; font-style: italic; text-transform: uppercase; margin-bottom: 8px; }
        .advisory-box p { color: #6D1A1A; font-size: 12px; line-height: 1.6; margin: 0; }
        .requests-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .requests-table thead tr { background: #f0f0f0; }
        .requests-table th { padding: 9px 12px; font-weight: 600; color: #333; border: 1px solid #ddd; font-size: 12px; }
        .requests-table td { padding: 8px 12px; border: 1px solid #eee; vertical-align: top; }
        .requests-table tbody tr:hover { background: #fafafa; }
        .control-number { font-weight: 700; color: #333; font-size: 12px; }
        .date-info { font-size: 11px; color: #888; margin-top: 2px; }
        .btn-view { background: #5a9fd4; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; text-decoration: none; transition: background 0.2s; display: inline-block; }
        .btn-view:hover { background: #4a8fc4; color: white; }
        .btn-action { color: white; border: none; padding: 5px 0; border-radius: 4px; font-size: 11px; cursor: pointer; transition: background 0.2s; width: 115px; display: block; text-align: center; }
        .btn-action.upload { background: var(--pup-red); }
        .btn-action.upload:hover { background: #8B2020; }
        .btn-action.walkin { background: #555; }
        .btn-action.walkin:hover { background: #333; }
        .btn-action.cancel { background: #c0392b; }
        .btn-action.cancel:hover { background: #a93226; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
        .footer-pup { background: #f8f8f8; border-top: 1px solid #ddd; padding: 12px 20px; text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }
        .modal-header { background: var(--pup-red); color: white; }
        .modal-header .btn-close { filter: invert(1); }

        /* Timeline Styles */
        .timeline-track { position: relative; padding: 20px 10px; margin-bottom: 20px; background: #fafafa; border-radius: 6px; border: 1px solid #eee; }
        .timeline-steps { display: flex; justify-content: space-between; position: relative; }
        .timeline-steps::before { content: ''; position: absolute; top: 16px; left: 30px; right: 30px; height: 3px; background: #e0e0e0; z-index: 1; }
        .timeline-step { position: relative; z-index: 2; text-align: center; flex: 1; }
        .timeline-icon { width: 34px; height: 34px; border-radius: 50%; background: #e0e0e0; color: #777; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 13px; transition: all 0.3s; }
        .timeline-step.completed .timeline-icon { background: #27ae60; color: white; }
        .timeline-step.active .timeline-icon { background: var(--pup-red); color: white; box-shadow: 0 0 0 4px rgba(109,26,26,0.2); }
        .timeline-step.cancelled .timeline-icon { background: #c0392b; color: white; }
        .timeline-title { font-size: 11px; font-weight: 600; color: #777; }
        .timeline-step.active .timeline-title { color: var(--pup-red); font-weight: 700; }
        .timeline-step.completed .timeline-title { color: #27ae60; font-weight: 600; }
        .timeline-step.cancelled .timeline-title { color: #c0392b; font-weight: 700; }

        .history-list { list-style: none; padding-left: 20px; position: relative; border-left: 2px solid #ddd; margin-left: 10px; margin-bottom: 0; }
        .history-item { position: relative; margin-bottom: 16px; }
        .history-item:last-child { margin-bottom: 0; }
        .history-item::before { content: ''; position: absolute; left: -26px; top: 3px; width: 10px; height: 10px; border-radius: 50%; background: var(--pup-red); }
        .history-time { font-size: 11px; color: #888; margin-bottom: 2px; }
        .history-title { font-size: 13px; font-weight: 600; color: #333; }
        .history-notes { font-size: 12px; color: #555; background: #f9f9f9; padding: 6px 10px; border-radius: 4px; margin-top: 4px; border: 1px solid #eee; }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">PUP e-DocuServe<span>Biñan Campus — Online Document Request</span></div>
    </a>
    <div class="dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle" style="font-size:18px;"></i>
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            <i class="fas fa-caret-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="font-size:13px;">
            <li><a class="dropdown-item" href="account_settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<div class="tabs-bar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="new_request.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
        <li class="nav-item"><a class="nav-link active" href="requests.php"><i class="fas fa-list"></i> Requests</a></li>
        <li class="nav-item"><a class="nav-link" href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
    </ul>
</div>

<div class="content-wrapper">

    <?php if ($upload_success): ?>
        <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> Bank slip uploaded! The registrar will verify your payment shortly.</div>
    <?php endif; ?>
    <?php if (!empty($upload_error)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($upload_error) ?></div>
    <?php endif; ?>
    <?php if ($cancel_success): ?>
        <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> Your request has been cancelled successfully.</div>
    <?php endif; ?>
    <?php if (!empty($cancel_error)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($cancel_error) ?></div>
    <?php endif; ?>

    <div class="advisory-box">
        <h6>⚠ Advisory to Our Clients</h6>
        <p>All requests with incomplete and invalid requirements shall be automatically deleted after <strong>90 days</strong> of noncompliance. <br><strong>Always "View Details" for more specific updates.</strong></p>
    </div>

    <div class="section-card">
        <div class="section-header">Requests</div>
        <div style="overflow-x: auto;">
            <?php if (empty($requests)): ?>
                <div class="empty-state"><i class="fas fa-folder-open"></i>No requests found. <a href="new_request.php" style="color:var(--pup-red);">Submit a new request.</a></div>
            <?php else: ?>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Control Number</th>
                        <th>Details</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Tentative Release Date</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td>
                            <div class="control-number"><?= htmlspecialchars($req['control_number']) ?></div>
                            <div class="date-info">Date Filed:<br><?= date('d/m/Y', strtotime($req['date_filed'])) ?></div>
                            <?php if ($req['date_verified']): ?>
                            <div class="date-info">Date Verified:<br><?= date('d/m/Y', strtotime($req['date_verified'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><strong>Documents:</strong> <?= htmlspecialchars($req['documents'] ?? 'N/A') ?></div>
                            <div><strong>Course:</strong> <?= htmlspecialchars($user['course']) ?></div>
                            <div><strong>Purpose:</strong> <?= htmlspecialchars($req['purpose']) ?></div>
                        </td>
                        <td style="text-align:center; min-width:160px;">
                            <?= statusBadge($req['request_status']) ?>
                            <div class="mt-1 d-flex justify-content-center gap-1">
                                <button class="btn-view" onclick="openDetails(<?= htmlspecialchars(json_encode($req)) ?>)">Details</button>
                                <button class="btn-view" style="background:var(--pup-red);" onclick="openTimeline(<?= $req['id'] ?>, '<?= htmlspecialchars($req['control_number']) ?>', '<?= htmlspecialchars($req['request_status']) ?>')">
                                    <i class="fas fa-history me-1"></i>Timeline
                                </button>
                            </div>
                            <div style="font-size:11px; color:#888; margin-top:4px;">Always "View Details" for more specific updates</div>
                        </td>
                        <td style="text-align:center; min-width:140px;">
                            <div><?= $req['tentative_release_date'] ? date('d/m/Y', strtotime($req['tentative_release_date'])) : '<span style="color:#aaa;">-</span>' ?></div>
                            <div class="mt-1" style="font-size:12px;">Paid? <?= paymentBadge($req['payment_status']) ?></div>
                        </td>
                        <td style="text-align:center; min-width:130px;">
                            <?php if ($req['payment_status'] === 'Unpaid' && $req['request_status'] !== 'Cancelled'): ?>
                                <div style="display:flex; flex-direction:column; gap:5px; align-items:center;">
                                    <?php if (($req['payment_method'] ?? '') === 'Walk-in (Bank Slip)'): ?>
                                        <button class="btn-action upload" onclick="openUpload(<?= $req['id'] ?>, '<?= htmlspecialchars($req['control_number']) ?>')">
                                            <i class="fas fa-upload me-1"></i> Upload Slip
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#888; text-align:center;"><i class="fas fa-cash-register me-1"></i>Pay at Cashier</span>
                                    <?php endif; ?>
                                    <button class="btn-action cancel" onclick="openCancel(<?= $req['id'] ?>, '<?= htmlspecialchars($req['control_number']) ?>')">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            <?php elseif ($req['payment_status'] === 'Pending Verification'): ?>
                                <?php if ($req['bank_slip_path'] === 'walkin'): ?>
                                    <span style="font-size:11px; color:#888;"><i class="fas fa-store me-1"></i>Walk-in Pending</span>
                                <?php else: ?>
                                    <span style="font-size:11px; color:#888;"><i class="fas fa-clock me-1"></i>Awaiting Verification</span>
                                <?php endif; ?>
                            <?php elseif (!empty($req['bank_slip_path']) && $req['bank_slip_path'] !== 'walkin'): ?>
                                <a href="../assets/uploads/<?= htmlspecialchars($req['bank_slip_path']) ?>" target="_blank" class="btn-view">
                                    <i class="fas fa-eye me-1"></i> View Slip
                                </a>
                            <?php else: ?>
                                <span style="color:#aaa; font-size:11px;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> | <a href="#">Privacy Statement</a>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-info-circle me-2"></i>Request Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent" style="font-size:13px; padding:0;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- TIMELINE MODAL (Wave 3) -->
<div class="modal fade" id="timelineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-history me-2"></i>Status Tracking & Timeline — <span id="timelineControlNum"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:13px; padding:20px;">
                <div id="timelineStepper" class="timeline-track"></div>
                <h6 class="fw-bold mb-3" style="color:var(--pup-red); font-size:13px;"><i class="fas fa-list-ul me-2"></i>Status History Log</h6>
                <div id="timelineHistoryList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- UPLOAD SLIP MODAL -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Bank Slip</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="request_id" id="uploadRequestId">
                    <p class="mb-1">Control Number: <strong id="uploadControlNum"></strong></p>
                    <p class="text-muted mb-3" style="font-size:12px;">Upload a clear photo or scanned copy of your bank slip as proof of payment.</p>
                    <input type="file" name="bank_slip" class="form-control" accept=".jpg,.jpeg" required style="font-size:13px;">
                    <div style="font-size:11px; color:#888; margin-top:4px;">Accepted: JPG/JPEG only. Max file size: 10MB.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnUploadSubmit" class="btn btn-sm" style="background:var(--pup-red); color:white;">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-times-circle me-2"></i>Cancel Request</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="cancel_request_id" id="cancelRequestId">
                    <p class="mb-3">Control Number: <strong id="cancelControlNum"></strong></p>
                    <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action cannot be undone. Only unpaid requests can be cancelled.
                    </div>
                    <label style="font-size:12px; font-weight:600; color:#555; margin-bottom:4px; display:block;">
                        Reason for Cancellation <span style="color:var(--pup-red);">*</span>
                    </label>
                    <textarea name="cancel_reason" class="form-control" rows="3" placeholder="Please state your reason for cancelling this request..." required style="font-size:13px;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times me-1"></i> Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const allRequestHistories = <?= json_encode($request_histories) ?>;

function openTimeline(reqId, controlNum, currentStatus) {
    document.getElementById('timelineControlNum').textContent = controlNum;

    // Build Stepper
    const steps = [
        { key: 'Pending', label: 'Submitted', icon: 'fa-file-alt' },
        { key: 'Processing', label: 'Processing', icon: 'fa-cogs' },
        { key: 'Ready for Pickup', label: 'Ready for Pickup', icon: 'fa-envelope-open-text' },
        { key: 'Claimed', label: 'Claimed', icon: 'fa-check-circle' }
    ];

    const isCancelled = currentStatus === 'Cancelled';
    const statusOrder = ['Pending', 'Processing', 'Ready for Pickup', 'Claimed'];
    const currentIndex = statusOrder.indexOf(currentStatus);

    let stepperHtml = '<div class="timeline-steps">';
    if (isCancelled) {
        stepperHtml += `
            <div class="timeline-step completed">
                <div class="timeline-icon"><i class="fas fa-file-alt"></i></div>
                <div class="timeline-title">Submitted</div>
            </div>
            <div class="timeline-step cancelled">
                <div class="timeline-icon"><i class="fas fa-times-circle"></i></div>
                <div class="timeline-title">Cancelled</div>
            </div>
        `;
    } else {
        steps.forEach((step, idx) => {
            let cls = '';
            if (idx < currentIndex) cls = 'completed';
            else if (idx === currentIndex) cls = 'active';
            stepperHtml += `
                <div class="timeline-step ${cls}">
                    <div class="timeline-icon"><i class="fas ${step.icon}"></i></div>
                    <div class="timeline-title">${step.label}</div>
                </div>
            `;
        });
    }
    stepperHtml += '</div>';
    document.getElementById('timelineStepper').innerHTML = stepperHtml;

    // Build History Logs
    const history = allRequestHistories[reqId] || [];
    if (history.length === 0) {
        document.getElementById('timelineHistoryList').innerHTML = '<p class="text-muted" style="font-size:12px;">No activity logs recorded yet for this request.</p>';
    } else {
        let histHtml = '<ul class="history-list">';
        history.forEach(item => {
            const dateStr = item.changed_at ? new Date(item.changed_at).toLocaleString() : '-';
            histHtml += `
                <li class="history-item">
                    <div class="history-time"><i class="far fa-clock me-1"></i>${dateStr}</div>
                    <div class="history-title">${item.new_status ? item.new_status : 'Update'} <span class="text-muted fw-normal" style="font-size:11px;">by ${item.changed_by || 'System'}</span></div>
                    ${item.notes ? `<div class="history-notes">${item.notes}</div>` : ''}
                </li>
            `;
        });
        histHtml += '</ul>';
        document.getElementById('timelineHistoryList').innerHTML = histHtml;
    }

    new bootstrap.Modal(document.getElementById('timelineModal')).show();
}

function openDetails(req) {
    const statusMap = { 'Pending':'<span class="badge bg-secondary">Pending</span>', 'Processing':'<span class="badge bg-primary">Processing</span>', 'Ready for Pickup':'<span class="badge bg-success">Ready for Pickup</span>', 'Claimed':'<span class="badge bg-dark">Claimed</span>', 'Cancelled':'<span class="badge bg-danger">Cancelled</span>' };
    const payMap = { 'Unpaid':'<span class="badge bg-danger">✗ Unpaid</span>', 'Pending Verification':'<span class="badge bg-warning text-dark">⏳ Pending Verification</span>', 'Paid':'<span class="badge bg-success">✔ Paid</span>' };
    let slipInfo = '-';
    if (req.bank_slip_path === 'walkin') slipInfo = '<span class="badge bg-secondary"><i class="fas fa-walking me-1"></i>Walk-in</span>';
    else if (req.bank_slip_path) slipInfo = '<a href="../assets/uploads/' + req.bank_slip_path + '" target="_blank" class="btn-view"><i class="fas fa-eye me-1"></i> View Slip</a>';
    let html = '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
    html += row('Control Number', '<strong>' + req.control_number + '</strong>');
    html += row('Request Status', statusMap[req.request_status] || req.request_status);
    html += row('Payment Status', payMap[req.payment_status] || req.payment_status);
    html += row('Payment Method', req.bank_slip_path === 'walkin' ? 'Walk-in' : (req.bank_slip_path ? 'Bank Slip Upload' : '-'));
    html += row('Purpose', req.purpose);
    html += row('Total Amount', '₱ ' + parseFloat(req.total_amount).toFixed(2));
    html += row('Payment Method', req.payment_method || '-');
    html += row('Date Filed', req.date_filed ? req.date_filed.substring(0,10) : '-');
    html += row('Date Verified', req.date_verified ? req.date_verified.substring(0,10) : '-');
    html += row('Tentative Release', req.tentative_release_date || '-');
    html += row('Bank Slip', slipInfo);
    html += row('Admin Notes', req.admin_notes || '<span style="color:#aaa;">None</span>');
    html += '</table>';
    document.getElementById('detailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
function row(label, value) {
    return `<tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:9px 16px;font-weight:600;color:#555;width:160px;background:#fafafa;">${label}:</td><td style="padding:9px 16px;color:#333;">${value}</td></tr>`;
}
function openUpload(reqId, controlNum) {
    document.getElementById('uploadRequestId').value = reqId;
    document.getElementById('uploadControlNum').textContent = controlNum;
    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}
function openCancel(reqId, controlNum) {
    document.getElementById('cancelRequestId').value = reqId;
    document.getElementById('cancelControlNum').textContent = controlNum;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
document.getElementById('uploadModal').addEventListener('show.bs.modal', function () {
    document.getElementById('btnUploadSubmit').onclick = function (e) {
        const fileInput = document.querySelector('#uploadModal input[type=file]');
        if (!fileInput.value) return; // let HTML5 validation handle empty

        e.preventDefault();
        
        alert('Reminder: Still present the bank slip receipt at the Registrar\'s Office in person.');
       
        e.target.closest('form').submit();
    };
});
</script>
</body>
</html>