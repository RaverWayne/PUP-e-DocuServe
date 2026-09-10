<?php
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];
$success = '';
$error   = '';

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_action'])) {
    $target_id = intval($_POST['student_id']);
    $action    = $_POST['verify_action'];

    // Fetch student info for email
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

// Filters
$search         = trim($_GET['search'] ?? '');
$filter_course  = trim($_GET['course'] ?? '');
$filter_year    = trim($_GET['year'] ?? '');
$filter_vstatus = trim($_GET['vstatus'] ?? '');

$where  = [];
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

$current_page = 'students';
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
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .filter-bar { padding: 12px 18px; background: #fafafa; border-bottom: 1px solid #eee; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .filter-bar select, .filter-bar input { font-size: 12px; padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: var(--sa-color); }
        .filter-bar button { background: var(--sa-color); color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .filter-bar a.clear { font-size: 12px; color: #888; text-decoration: none; padding: 5px 8px; }
        .filter-bar a.clear:hover { color: var(--sa-color); }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #f8f8f8; }
        .data-table th { padding: 9px 12px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .btn-approve { background: #27ae60; color: white; border: none; padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-approve:hover { background: #219a52; }
        .btn-reject  { background: #e74c3c; color: white; border: none; padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-reject:hover  { background: #c0392b; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
        .vs-pending  { background: #f39c12; color: white; }
        .vs-active   { background: #27ae60; color: white; }
        .vs-rejected { background: #e74c3c; color: white; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-users me-2" style="color:var(--sa-color);"></i>Students</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
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
                <i class="fas fa-users me-2" style="color:var(--sa-color);"></i>Registered Students
                <span style="font-weight:400; font-size:12px; color:#888; margin-left:6px;">(<?= count($students) ?> records)</span>
            </div>

            <!-- FILTER BAR -->
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
                                $vscls = match($vs) {
                                    'Active'   => 'vs-active',
                                    'Rejected' => 'vs-rejected',
                                    default    => 'vs-pending',
                                };
                                ?>
                                <span class="badge <?= $vscls ?>"><?= htmlspecialchars($vs) ?></span>
                            </td>
                            <td style="font-size:12px;"><?= date('m/d/Y', strtotime($s['created_at'])) ?></td>
                            <td style="text-align:center; min-width:130px;">
                                <?php if (($s['verification_status'] ?? '') === 'Pending Verification'): ?>
                                    <div style="display:flex; gap:4px; justify-content:center;">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="verify_action" value="approve">
                                            <button type="submit" class="btn-approve"><i class="fas fa-check me-1"></i>Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="verify_action" value="reject">
                                            <button type="submit" class="btn-reject" onclick="return confirm('Reject this student account?')">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif (($s['verification_status'] ?? '') === 'Rejected'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="verify_action" value="approve">
                                        <button type="submit" class="btn-approve"><i class="fas fa-undo me-1"></i>Re-approve</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:11px;">—</span>
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
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
