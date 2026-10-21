<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once '../includes/mailer.php';
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_action'])) {
    csrf_verify();
    $target_id = intval($_POST['student_id']);
    $action    = $_POST['verify_action']; // 'approve' or 'reject'

    $stuStmt = $pdo->prepare("SELECT first_name, email FROM users WHERE id = ?");
    $stuStmt->execute([$target_id]);
    $stu = $stuStmt->fetch();

    if ($action === 'approve') {
        $pdo->prepare("UPDATE users SET verification_status = 'Active' WHERE id = ?")
            ->execute([$target_id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute(["Approved student account", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
        $success = "Student account approved.";

        if ($stu && !empty($stu['email'])) {
            sendMail($pdo, $stu['email'], $stu['first_name'],
                'Your PUP e-DocuServe Account Has Been Approved',
                emailAccountApproved($stu['first_name']));
        }
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE users SET verification_status = 'Rejected' WHERE id = ?")
            ->execute([$target_id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute(["Rejected student account", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
        $success = "Student account rejected.";

        if ($stu && !empty($stu['email'])) {
            sendMail($pdo, $stu['email'], $stu['first_name'],
                'Update on Your PUP e-DocuServe Account Registration',
                emailAccountRejected($stu['first_name']));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    csrf_verify();
    $target_id  = intval($_POST['student_id']);
    $activeReqs = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = ? AND request_status NOT IN ('Cancelled','Claimed')");
    $activeReqs->execute([$target_id]);
    if ($activeReqs->fetchColumn() > 0) {
        $error = "Cannot deactivate: this student has active requests in progress.";
    } else {
        $pdo->prepare("UPDATE users SET verification_status = 'Rejected' WHERE id = ?")->execute([$target_id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
            ->execute(["Deactivated student account", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
        $success = "Student account deactivated.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reactivate') {
    csrf_verify();
    $target_id = intval($_POST['student_id']);
    $pdo->prepare("UPDATE users SET verification_status = 'Active' WHERE id = ?")->execute([$target_id]);
    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
        ->execute(["Reactivated student account", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
    $success = "Student account reactivated.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_student') {
    csrf_verify();
    $target_id  = intval($_POST['student_id']);
    $activeReqs = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = ? AND request_status NOT IN ('Cancelled','Claimed')");
    $activeReqs->execute([$target_id]);
    if ($activeReqs->fetchColumn() > 0) {
        $error = "Cannot delete: this student has active requests. Cancel or complete them first.";
    } else {
        $pdo->prepare("UPDATE users SET verification_status = 'Deleted' WHERE id = ?")->execute([$target_id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
            ->execute(["Deleted student account", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
        $success = "Student account deleted.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_student_password') {
    csrf_verify();
    $target_id = intval($_POST['student_id']);
    $new_pass  = $_POST['new_password'] ?? '';
    if (strlen($new_pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $new_pass)) {
        $error = "Password must contain at least 1 uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $new_pass)) {
        $error = "Password must contain at least 1 lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $new_pass)) {
        $error = "Password must contain at least 1 number.";
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new_pass, PASSWORD_BCRYPT), $target_id]);
        $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target) VALUES (?, ?, ?, ?, ?)")
            ->execute(["Reset student password", $admin_name, $admin_id, $admin_role, "User ID $target_id"]);
        $success = "Student password reset successfully.";
    }
}

$search       = trim($_GET['search'] ?? '');
$filter_course = trim($_GET['course'] ?? '');
$filter_year   = trim($_GET['year'] ?? '');
$filter_vstatus = trim($_GET['vstatus'] ?? '');

$where  = ["verification_status != 'Deleted'"];
$params = [];

if (!empty($search)) {
    $where[]  = "(last_name LIKE ? OR first_name LIKE ? OR student_number LIKE ? OR email LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if (!empty($filter_course)) {
    $where[]  = "course = ?";
    $params[] = $filter_course;
}
if (!empty($filter_year)) {
    $where[]  = "year_admitted = ?";
    $params[] = $filter_year;
}
if (!empty($filter_vstatus)) {
    $where[]  = "verification_status = ?";
    $params[] = $filter_vstatus;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM users $whereSQL ORDER BY last_name ASC");
$stmt->execute($params);
$students = $stmt->fetchAll();

$courses = $pdo->query("SELECT DISTINCT course FROM users WHERE course IS NOT NULL AND course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$years   = $pdo->query("SELECT DISTINCT year_admitted FROM users WHERE year_admitted IS NOT NULL ORDER BY year_admitted DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Students | PUP e-DocuServe</title>
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
        .sidebar-footer a { color: #f66; text-decoration: none; }
        .main-content { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--pup-red); }
        .page-content { padding: 24px; flex: 1; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .filter-bar { padding: 12px 18px; background: #fafafa; border-bottom: 1px solid #eee; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .filter-bar select, .filter-bar input { font-size: 12px; padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: var(--pup-red); }
        .filter-bar button { background: var(--pup-red); color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .filter-bar a.clear { font-size: 12px; color: #888; text-decoration: none; padding: 5px 8px; }
        .filter-bar a.clear:hover { color: var(--pup-red); }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #f8f8f8; }
        .data-table th { padding: 9px 12px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .btn-approve { background: #27ae60; color: white; border: none; padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; display:inline-flex; align-items:center; gap:3px; }
        .btn-approve:hover { background: #219a52; }
        .btn-reject  { background: #e74c3c; color: white; border: none; padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; display:inline-flex; align-items:center; gap:3px; }
        .btn-reject:hover  { background: #c0392b; }
        .btn-sm-action { padding: 3px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: all 0.2s; }
        .btn-disable { background: #e74c3c; color: white; }
        .btn-disable:hover { background: #c0392b; }
        .btn-enable  { background: #27ae60; color: white; }
        .btn-enable:hover  { background: #219a52; }
        .btn-reset   { background: #f39c12; color: white; }
        .btn-reset:hover   { background: #d68910; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
        .vs-pending  { background: #f39c12; color: white; }
        .vs-active   { background: #27ae60; color: white; }
        .vs-rejected { background: #e74c3c; color: white; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">PUP e-DocuServe <span>Biñan Campus</span></div>
    </div>
    <div class="sidebar-role"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php"><i class="fas fa-file-alt"></i> Manage Requests</a>
        <a href="walkin_requests.php"><i class="fas fa-walking"></i> Walk-in Payments</a>
        <div class="nav-section">Users</div>
        <a href="students.php" class="active"><i class="fas fa-users"></i> Students</a>
        <div class="nav-section">Account</div>
        <a href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="font-size:11px; opacity:0.8;">PUP e-DocuServe v1.0</div>
        <div style="font-size:11px; color:#888;">Biñan Campus</div>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-users me-2" style="color:var(--pup-red);"></i>Students</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-users me-2" style="color:var(--pup-red);"></i>Registered Students
                <span style="font-weight:400; font-size:12px; color:#888; margin-left:6px;">(<?= count($students) ?> records)</span>
            </div>

            <form method="GET" action="">
                <div class="filter-bar">
                    <input type="text" name="search" placeholder="Search name, student no., email..."
                        value="<?= htmlspecialchars($search) ?>" style="width:220px;">

                    <select name="vstatus">
                        <option value="">All Statuses</option>
                        <option value="Pending Verification" <?= $filter_vstatus === 'Pending Verification' ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="Active"               <?= $filter_vstatus === 'Active'               ? 'selected' : '' ?>>Active</option>
                        <option value="Rejected"             <?= $filter_vstatus === 'Rejected'             ? 'selected' : '' ?>>Rejected</option>
                    </select>

                    <select name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="year">
                        <option value="">All Years Admitted</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit"><i class="fas fa-search me-1"></i>Filter</button>
                    <a href="students.php" class="clear"><i class="fas fa-times me-1"></i>Clear</a>
                </div>
            </form>

            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Student Number</th>
                            <th>Course</th>
                            <th>Year Admitted</th>
                            <th>Program Status</th>
                            <th>Verification Status</th>
                            <th>Date Registered</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="8" style="text-align:center; color:#aaa; padding:30px;">No students found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars(strtoupper($s['last_name']) . ', ' . $s['first_name']) ?></strong>
                                <div style="font-size:11px; color:#888;"><?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($s['student_number'] ?? 'N/A') ?></td>
                            <td style="max-width:180px; font-size:12px;"><?= htmlspecialchars($s['course'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['year_admitted'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['program_status'] ?? '—') ?></td>
                            <td>
                                <?php
                                $vs    = $s['verification_status'] ?? 'Pending Verification';
                                $vscls = ($vs === 'Active') ? 'vs-active' : (($vs === 'Rejected') ? 'vs-rejected' : 'vs-pending');
                                ?>
                                <span class="badge <?= $vscls ?>"><?= htmlspecialchars($vs) ?></span>
                            </td>
                            <td style="font-size:12px;"><?= date('m/d/Y', strtotime($s['created_at'])) ?></td>
                            <td style="text-align:center; min-width:200px;">
                                <div style="display:flex; gap:3px; justify-content:center; flex-wrap:wrap;">
                                    <button class="btn-sm-action" style="background:#3498db; color:white;"
                                        onclick="openStudentProfile(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if (($s['verification_status'] ?? '') === 'Pending Verification'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="verify_action" value="approve">
                                            <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
                                        </form>
                                        <button class="btn-sm-action btn-reject"
                                            onclick="openStudentConfirm(<?= $s['id'] ?>, 'reject', <?= htmlspecialchars(json_encode(strtoupper($s['last_name']) . ', ' . $s['first_name'])) ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif (($s['verification_status'] ?? '') === 'Active'): ?>
                                        <button class="btn-sm-action btn-disable"
                                            onclick="openStudentConfirm(<?= $s['id'] ?>, 'deactivate', <?= htmlspecialchars(json_encode(strtoupper($s['last_name']) . ', ' . $s['first_name'])) ?>)">
                                            <i class="fas fa-ban"></i> Deactivate
                                        </button>
                                        <button class="btn-sm-action btn-reset"
                                            onclick="openStudentReset(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode(strtoupper($s['last_name']) . ', ' . $s['first_name'])) ?>)">
                                            <i class="fas fa-key"></i> Reset
                                        </button>
                                    <?php elseif (($s['verification_status'] ?? '') === 'Rejected'): ?>
                                        <button class="btn-sm-action btn-enable"
                                            onclick="openStudentConfirm(<?= $s['id'] ?>, 'reactivate', <?= htmlspecialchars(json_encode(strtoupper($s['last_name']) . ', ' . $s['first_name'])) ?>)">
                                            <i class="fas fa-undo"></i> Reactivate
                                        </button>
                                    <?php endif; ?>
                                    <?php if (($s['verification_status'] ?? '') !== 'Pending Verification'): ?>
                                    <button class="btn-sm-action" style="background:#8B0000; color:white;"
                                        onclick="openStudentConfirm(<?= $s['id'] ?>, 'delete_student', <?= htmlspecialchars(json_encode(strtoupper($s['last_name']) . ', ' . $s['first_name'])) ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
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

<div class="modal fade" id="studentProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--pup-red); color:white;">
                <h6 class="modal-title"><i class="fas fa-user me-2"></i>Student Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            <div class="modal-body" style="font-size:13px; padding:0;">
                <div style="display:flex; gap:20px; padding:20px; border-bottom:1px solid #eee; align-items:flex-start;">
                    <div style="flex-shrink:0; text-align:center; width:90px;">
                        <div id="spPhotoWrap" style="width:90px; height:100px; border:1px solid #ddd; border-radius:4px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f5f5f5; margin-bottom:6px;">
                            <i class="fas fa-user-circle" style="font-size:50px; color:#ccc;"></i>
                        </div>
                        <div id="spStatusBadge"></div>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:15px; font-weight:700; color:#333;" id="spName"></div>
                        <div style="font-size:12px; color:#888; margin-top:2px;" id="spStudentNum"></div>
                        <div style="font-size:13px; color:#555; margin-top:6px;" id="spCourse"></div>
                        <div style="font-size:12px; color:#888; margin-top:2px;" id="spEmail"></div>
                        <div style="font-size:12px; color:#888;" id="spMobile"></div>
                    </div>
                </div>
                <div style="padding:16px 20px;">
                    <table style="width:100%; font-size:13px; border-collapse:collapse;">
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555; width:180px;">Date of Birth</td><td id="spDob"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">Address</td><td id="spAddress"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">Year Admitted</td><td id="spYear"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">Admitted As</td><td id="spAdmitted"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">Program Status</td><td id="spProgram"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">High School</td><td id="spHS"></td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:7px 0; font-weight:600; color:#555;">Elementary</td><td id="spElem"></td></tr>
                        <tr><td style="padding:7px 0; font-weight:600; color:#555;">Date Registered</td><td id="spReg"></td></tr>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="studentConfirmHeader" style="background:#e74c3c; color:white;">
                <h6 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Action</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            <div class="modal-body" style="font-size:13px;">
                <p id="studentConfirmMsg" class="mb-0"></p>
            </div>
            <form method="POST" id="studentConfirmForm">
                <?= csrf_field() ?>
                <input type="hidden" name="student_id"   id="scStudentId">
                <input type="hidden" name="action"        id="scAction">
                <input type="hidden" name="verify_action" id="scVerifyAction">
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="scSubmitBtn" class="btn btn-sm" style="background:#e74c3c; color:white; border:none;">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="studentResetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--pup-red); color:white;">
                <h6 class="modal-title"><i class="fas fa-key me-2"></i>Reset Student Password</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_student_password">
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="student_id" id="srStudentId">
                    <p class="mb-3">Resetting password for: <strong id="srStudentName"></strong></p>
                    <label style="font-size:12px; font-weight:600; color:#555; margin-bottom:3px; display:block;">New Password <span style="color:var(--pup-red);">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="new_password" id="srPass" class="form-control" placeholder="New Password" required autocomplete="new-password">
                        <i class="fas fa-eye" id="srToggle" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#aaa;font-size:13px;"></i>
                    </div>
                    <ul style="list-style:none; padding:8px 0 0; margin:0; font-size:12px;">
                        <li id="sr-len"><i class="fas fa-circle-xmark me-1" style="color:#aaa;"></i><span style="color:#aaa;">At least 8 characters</span></li>
                        <li id="sr-upper"><i class="fas fa-circle-xmark me-1" style="color:#aaa;"></i><span style="color:#aaa;">At least 1 uppercase letter</span></li>
                        <li id="sr-lower"><i class="fas fa-circle-xmark me-1" style="color:#aaa;"></i><span style="color:#aaa;">At least 1 lowercase letter</span></li>
                        <li id="sr-num"><i class="fas fa-circle-xmark me-1" style="color:#aaa;"></i><span style="color:#aaa;">At least 1 number</span></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--pup-red); color:white; border:none;">
                        <i class="fas fa-save me-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openStudentProfile(s) {
    const photoWrap = document.getElementById('spPhotoWrap');
    photoWrap.innerHTML = '<i class="fas fa-user-circle" style="font-size:50px; color:#ccc;"></i>';
    if (s.profile_photo) {
        fetch('../includes/get_profile_url.php?user_id=' + s.id)
            .then(r => r.json())
            .then(data => {
                if (data.url) photoWrap.innerHTML = '<img src="' + data.url + '" style="width:100%;height:100%;object-fit:cover;" alt="Profile">';
            }).catch(() => {});
    }
    const vsMap = { 'Active':'success','Rejected':'danger','Pending Verification':'warning text-dark','Deleted':'secondary' };
    document.getElementById('spStatusBadge').innerHTML = '<span class="badge bg-' + (vsMap[s.verification_status] || 'secondary') + '">' + (s.verification_status || '') + '</span>';
    document.getElementById('spName').textContent       = (s.last_name + ', ' + s.first_name + (s.middle_name && s.middle_name.toUpperCase() !== 'N/A' ? ' ' + s.middle_name : '')).toUpperCase();
    document.getElementById('spStudentNum').textContent = s.student_number || 'N/A';
    document.getElementById('spCourse').textContent     = s.course || '—';
    document.getElementById('spEmail').textContent      = s.email || '—';
    document.getElementById('spMobile').textContent     = s.mobile_number || '—';
    document.getElementById('spDob').textContent        = s.date_of_birth || '—';
    document.getElementById('spAddress').textContent    = s.address || '—';
    document.getElementById('spYear').textContent       = s.year_admitted || '—';
    document.getElementById('spAdmitted').textContent   = s.admitted_as || '—';
    document.getElementById('spProgram').textContent    = s.program_status || '—';
    document.getElementById('spHS').textContent         = s.high_school ? (s.high_school + (s.hs_year_grad ? ' (' + s.hs_year_grad + ')' : '')) : '—';
    document.getElementById('spElem').textContent       = s.elementary ? (s.elementary + (s.elem_year_grad ? ' (' + s.elem_year_grad + ')' : '')) : '—';
    document.getElementById('spReg').textContent        = s.created_at ? s.created_at.substring(0, 10) : '—';
    new bootstrap.Modal(document.getElementById('studentProfileModal')).show();
}

function openStudentConfirm(id, action, name) {
    const msgs = {
        reject:         'Reject the account of <strong>' + name + '</strong>? The student will be notified.',
        deactivate:     'Deactivate the account of <strong>' + name + '</strong>? They will not be able to log in until reactivated.',
        reactivate:     'Reactivate the account of <strong>' + name + '</strong>?',
        delete_student: 'Permanently remove the account of <strong>' + name + '</strong>? Their request history is preserved but they will no longer be able to log in. This cannot be undone.',
    };
    const colors = { reject:'#e74c3c', deactivate:'#e74c3c', reactivate:'#27ae60', delete_student:'#8B0000' };
    document.getElementById('studentConfirmMsg').innerHTML  = msgs[action] || 'Are you sure?';
    document.getElementById('studentConfirmHeader').style.background = colors[action] || '#e74c3c';
    document.getElementById('scStudentId').value            = id;
    document.getElementById('scSubmitBtn').style.background = colors[action] || '#e74c3c';
    if (action === 'reject') {
        document.getElementById('scVerifyAction').value = 'reject';
        document.getElementById('scAction').value       = '';
    } else if (action === 'reactivate') {
        document.getElementById('scVerifyAction').value = 'approve';
        document.getElementById('scAction').value       = '';
    } else {
        document.getElementById('scVerifyAction').value = '';
        document.getElementById('scAction').value       = action;
    }
    new bootstrap.Modal(document.getElementById('studentConfirmModal')).show();
}

function openStudentReset(id, name) {
    document.getElementById('srStudentId').value         = id;
    document.getElementById('srStudentName').textContent = name;
    document.getElementById('srPass').value              = '';
    checkSrComplexity();
    new bootstrap.Modal(document.getElementById('studentResetModal')).show();
}

document.getElementById('srToggle')?.addEventListener('click', function() {
    const inp = document.getElementById('srPass');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');
});

function setSrReq(id, ok) {
    const el = document.getElementById(id); if (!el) return;
    const ic = el.querySelector('i'), sp = el.querySelector('span');
    el.style.color = ok ? '#27ae60' : '#aaa';
    if (ic) { ic.className = (ok ? 'fas fa-circle-check' : 'fas fa-circle-xmark') + ' me-1'; ic.style.color = ok ? '#27ae60' : '#aaa'; }
    if (sp) sp.style.color = ok ? '#27ae60' : '#aaa';
}
function checkSrComplexity() {
    const v = document.getElementById('srPass')?.value || '';
    setSrReq('sr-len',   v.length >= 8);
    setSrReq('sr-upper', /[A-Z]/.test(v));
    setSrReq('sr-lower', /[a-z]/.test(v));
    setSrReq('sr-num',   /[0-9]/.test(v));
}
document.getElementById('srPass')?.addEventListener('input', checkSrComplexity);
</script>
</body>
</html>