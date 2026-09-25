<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

// Date filter
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

// Summary for date range
$total_range    = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE DATE(date_filed) BETWEEN ? AND ?");
$total_range->execute([$date_from, $date_to]);
$total_range = $total_range->fetchColumn();

$paid_range = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE payment_status = 'Paid' AND DATE(date_filed) BETWEEN ? AND ?");
$paid_range->execute([$date_from, $date_to]);
$paid_range = $paid_range->fetchColumn();

$claimed_range = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE request_status = 'Claimed' AND DATE(date_filed) BETWEEN ? AND ?");
$claimed_range->execute([$date_from, $date_to]);
$claimed_range = $claimed_range->fetchColumn();

// Turnaround time & SLA Compliance for date range
$tat_stmt = $pdo->prepare("
    SELECT ROUND(AVG(DATEDIFF(COALESCE(date_released, updated_at), date_filed)), 1)
    FROM requests
    WHERE request_status IN ('Claimed', 'Ready for Pickup')
      AND DATE(date_filed) BETWEEN ? AND ?
");
$tat_stmt->execute([$date_from, $date_to]);
$avg_tat_range = $tat_stmt->fetchColumn();
$avg_tat_range = ($avg_tat_range !== null && $avg_tat_range !== false) ? $avg_tat_range : 0;

$sla_stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_with_target,
        SUM(CASE WHEN DATE(COALESCE(date_released, updated_at)) <= tentative_release_date THEN 1 ELSE 0 END) as on_time
    FROM requests
    WHERE request_status IN ('Claimed', 'Ready for Pickup')
      AND tentative_release_date IS NOT NULL
      AND DATE(date_filed) BETWEEN ? AND ?
");
$sla_stmt->execute([$date_from, $date_to]);
$sla_range_data = $sla_stmt->fetch();
$sla_rate_range = ($sla_range_data && $sla_range_data['total_with_target'] > 0)
    ? round(($sla_range_data['on_time'] / $sla_range_data['total_with_target']) * 100)
    : 100;

// Requests by status
$by_status = $pdo->prepare("
    SELECT request_status, COUNT(*) as count
    FROM requests
    WHERE DATE(date_filed) BETWEEN ? AND ?
    GROUP BY request_status
");
$by_status->execute([$date_from, $date_to]);
$by_status = $by_status->fetchAll();

// Requests by course
$by_course = $pdo->prepare("
    SELECT u.course, COUNT(r.id) as count
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE DATE(r.date_filed) BETWEEN ? AND ?
    GROUP BY u.course
    ORDER BY count DESC
");
$by_course->execute([$date_from, $date_to]);
$by_course = $by_course->fetchAll();

// Most requested documents
$top_docs = $pdo->prepare("
    SELECT d.document_name, COUNT(ri.id) as count
    FROM request_items ri
    LEFT JOIN documents d ON ri.document_id = d.id
    LEFT JOIN requests r ON ri.request_id = r.id
    WHERE DATE(r.date_filed) BETWEEN ? AND ?
    GROUP BY d.document_name
    ORDER BY count DESC
    LIMIT 10
");
$top_docs->execute([$date_from, $date_to]);
$top_docs = $top_docs->fetchAll();

// Requests by purpose
$by_purpose = $pdo->prepare("
    SELECT purpose, COUNT(*) as count
    FROM requests
    WHERE DATE(date_filed) BETWEEN ? AND ?
    GROUP BY purpose
    ORDER BY count DESC
");
$by_purpose->execute([$date_from, $date_to]);
$by_purpose = $by_purpose->fetchAll();

// Daily requests trend
$daily = $pdo->prepare("
    SELECT DATE(date_filed) as day, COUNT(*) as count
    FROM requests
    WHERE DATE(date_filed) BETWEEN ? AND ?
    GROUP BY DATE(date_filed)
    ORDER BY day ASC
");
$daily->execute([$date_from, $date_to]);
$daily = $daily->fetchAll();

// Requests Today filters
$today_date_from = $_GET['today_date_from'] ?? date('Y-m-d');
$today_date_to   = $_GET['today_date_to']   ?? date('Y-m-d');
$today_time      = $_GET['today_time']      ?? '';
$today_course    = $_GET['today_course']    ?? '';
$today_year      = $_GET['today_year']      ?? '';

$today_query = "
    SELECT r.id, r.control_number, CONCAT(u.last_name, ', ', u.first_name) as student_name,
           u.course, u.year_admitted, r.purpose, r.request_status, r.payment_status,
           TIME(r.date_filed) as time_filed, DATE(r.date_filed) as date_only
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE DATE(r.date_filed) BETWEEN ? AND ?
";
$today_params = [$today_date_from, $today_date_to];

if ($today_time) {
    if ($today_time === 'morning') {
        $today_query .= " AND TIME(r.date_filed) BETWEEN '08:00:00' AND '11:59:59'";
    } elseif ($today_time === 'afternoon') {
        $today_query .= " AND TIME(r.date_filed) BETWEEN '12:00:00' AND '16:59:59'";
    } elseif ($today_time === 'evening') {
        $today_query .= " AND TIME(r.date_filed) BETWEEN '17:00:00' AND '20:00:00'";
    }
}

if ($today_course) {
    $today_query .= " AND u.course = ?";
    $today_params[] = $today_course;
}

if ($today_year) {
    $today_query .= " AND u.year_admitted = ?";
    $today_params[] = $today_year;
}

$today_query .= " ORDER BY r.date_filed DESC";
$today_requests = $pdo->prepare($today_query);
$today_requests->execute($today_params);
$today_requests = $today_requests->fetchAll();

// Get unique courses for filter
$courses_list = $pdo->query("SELECT DISTINCT course FROM users WHERE course IS NOT NULL ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

$current_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .section-body { padding: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .summary-card { background: white; border-radius: 8px; padding: 16px 18px; border-left: 4px solid #ddd; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .summary-card .card-num { font-size: 22px; font-weight: 700; color: #333; }
        .summary-card .card-label { font-size: 11px; color: #888; margin-top: 3px; }
        .card-red { border-left-color: var(--pup-red); }
        .card-green { border-left-color: #27ae60; }
        .card-blue { border-left-color: #3498db; }
        .card-gold { border-left-color: #f39c12; }
        .card-dark { border-left-color: #2c3e50; }
        .card-purple { border-left-color: #8e44ad; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { padding: 9px 14px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; font-size: 12px; background: #f8f8f8; }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid #f0f0f0; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; }
        .btn-filter { background: var(--sa-color); color: white; border: none; padding: 7px 18px; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .btn-filter:hover { background: #283593; }
        .progress-bar-custom { height: 16px; border-radius: 4px; background: var(--sa-color); }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-chart-bar me-2" style="color:var(--sa-color);"></i>Reports</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <!-- DATE FILTER -->
        <div class="section-card">
            <div class="section-header">Filter by Date Range</div>
            <div class="section-body">
                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label style="font-size:12px; font-weight:600; color:#555;">From</label>
                            <input type="date" name="date_from" class="form-control mt-1" value="<?= $date_from ?>">
                        </div>
                        <div class="col-md-3">
                            <label style="font-size:12px; font-weight:600; color:#555;">To</label>
                            <input type="date" name="date_to" class="form-control mt-1" value="<?= $date_to ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn-filter w-100">
                                <i class="fas fa-filter me-1"></i> Apply Filter
                            </button>
                        </div>
                        <div class="col-md-4 text-muted" style="font-size:12px;">
                            Showing data from <strong><?= date('M d, Y', strtotime($date_from)) ?></strong> to <strong><?= date('M d, Y', strtotime($date_to)) ?></strong>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card card-red">
                <div class="card-num"><?= $total_range ?></div>
                <div class="card-label">Total Requests</div>
            </div>
            <div class="summary-card card-green">
                <div class="card-num"><?= $paid_range ?></div>
                <div class="card-label">Paid Requests</div>
            </div>
            <div class="summary-card card-blue">
                <div class="card-num"><?= $claimed_range ?></div>
                <div class="card-label">Claimed Documents</div>
            </div>
            <div class="summary-card card-dark">
                <div class="card-num"><?= $avg_tat_range ?> <span style="font-size:12px; font-weight:normal; color:#888;">days</span></div>
                <div class="card-label">Avg. Turnaround Time</div>
            </div>
            <div class="summary-card card-purple">
                <div class="card-num"><?= $sla_rate_range ?>%</div>
                <div class="card-label">SLA Compliance Rate</div>
            </div>
        </div>

        <!-- REQUESTS TODAY SECTION -->
        <div class="section-card">
            <div class="section-header">Requests Today</div>
            <div class="section-body">
                <form method="GET" action="">
                    <!-- Keep original date range in hidden inputs -->
                    <input type="hidden" name="date_from" value="<?= $date_from ?>">
                    <input type="hidden" name="date_to" value="<?= $date_to ?>">
                    
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-3">
                            <label style="font-size:12px; font-weight:600; color:#555;">From Date</label>
                            <input type="date" name="today_date_from" class="form-control mt-1" value="<?= $today_date_from ?>">
                        </div>
                        <div class="col-md-3">
                            <label style="font-size:12px; font-weight:600; color:#555;">To Date</label>
                            <input type="date" name="today_date_to" class="form-control mt-1" value="<?= $today_date_to ?>">
                        </div>
                        <div class="col-md-2">
                            <label style="font-size:12px; font-weight:600; color:#555;">Time of Day</label>
                            <select name="today_time" class="form-select mt-1">
                                <option value="">All</option>
                                <option value="morning" <?= $today_time === 'morning' ? 'selected' : '' ?>>Morning (8-12)</option>
                                <option value="afternoon" <?= $today_time === 'afternoon' ? 'selected' : '' ?>>Afternoon (12-5)</option>
                                <option value="evening" <?= $today_time === 'evening' ? 'selected' : '' ?>>Evening (5-8)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size:12px; font-weight:600; color:#555;">Course</label>
                            <select name="today_course" class="form-select mt-1">
                                <option value="">All Courses</option>
                                <?php foreach ($courses_list as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $today_course === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label style="font-size:12px; font-weight:600; color:#555;">Year</label>
                            <input type="number" name="today_year" class="form-control mt-1" placeholder="1-4" min="1" max="4" value="<?= htmlspecialchars($today_year) ?>">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn-filter w-100"><i class="fas fa-filter"></i></button>
                        </div>
                    </div>
                </form>

                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Control #</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($today_requests)): ?>
                                <tr><td colspan="9" style="text-align:center; color:#aaa; padding:20px;">No requests found for the selected filters.</td></tr>
                            <?php else: ?>
                            <?php foreach ($today_requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['control_number']) ?></td>
                                <td><?= htmlspecialchars($req['student_name']) ?></td>
                                <td><?= htmlspecialchars($req['course'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($req['year_admitted'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($req['purpose']) ?></td>
                                <td><?= date('M d, Y', strtotime($req['date_only'])) ?></td>
                                <td><?= date('g:i A', strtotime($req['time_filed'])) ?></td>
                                <td>
                                    <?php
                                    $pay_color = $req['payment_status'] === 'Paid' ? '#27ae60' : ($req['payment_status'] === 'Pending' ? '#f39c12' : '#95a5a6');
                                    ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; color:white; background:<?= $pay_color ?>;">
                                        <?= htmlspecialchars($req['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_color = [
                                        'Pending' => '#95a5a6',
                                        'Processing' => '#3498db',
                                        'Ready for Pickup' => '#27ae60',
                                        'Claimed' => '#2c3e50',
                                        'Rejected' => '#e74c3c'
                                    ][$req['request_status']] ?? '#95a5a6';
                                    ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; color:white; background:<?= $status_color ?>;">
                                        <?= htmlspecialchars($req['request_status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:14px; padding:10px; background:#f8f8f8; border-radius:6px; font-size:12px; color:#666;">
                    <i class="fas fa-info-circle me-1"></i>
                    Showing <strong><?= count($today_requests) ?></strong> request(s) matching the selected filters.
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="two-col">
            <!-- Daily Trend -->
            <div class="section-card">
                <div class="section-header">Daily Requests Trend</div>
                <div class="section-body">
                    <canvas id="trendChart" height="180"></canvas>
                </div>
            </div>

            <!-- By Status -->
            <div class="section-card">
                <div class="section-header">Requests by Status</div>
                <div class="section-body">
                    <canvas id="statusChart" height="180"></canvas>
                </div>
            </div>
        </div>

        <div class="two-col">
            <!-- Top Documents -->
            <div class="section-card">
                <div class="section-header">Most Requested Documents</div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr><th>#</th><th>Document</th><th>Count</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_docs)): ?>
                                <tr><td colspan="3" style="text-align:center; color:#aaa; padding:20px;">No data.</td></tr>
                            <?php else: ?>
                            <?php foreach ($top_docs as $i => $d): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($d['document_name']) ?></td>
                                <td><strong><?= $d['count'] ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- By Purpose -->
            <div class="section-card">
                <div class="section-header">Requests by Purpose</div>
                <div class="section-body">
                    <?php if (empty($by_purpose)): ?>
                        <div style="text-align:center; color:#aaa; padding:20px;">No data.</div>
                    <?php else: ?>
                    <?php
                    $max_purpose = max(array_column($by_purpose, 'count'));
                    foreach ($by_purpose as $p):
                        $pct = $max_purpose > 0 ? round(($p['count'] / $max_purpose) * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span><?= htmlspecialchars($p['purpose']) ?></span>
                            <strong><?= $p['count'] ?></strong>
                        </div>
                        <div style="background:#f0f0f0; border-radius:4px; overflow:hidden;">
                            <div class="progress-bar-custom" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- By Course -->
        <div class="section-card">
            <div class="section-header">Requests by Course/Program</div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Course</th><th>Total Requests</th><th>Percentage</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($by_course)): ?>
                            <tr><td colspan="4" style="text-align:center; color:#aaa; padding:20px;">No data.</td></tr>
                        <?php else: ?>
                        <?php
                        $total_course = array_sum(array_column($by_course, 'count'));
                        foreach ($by_course as $i => $c):
                            $pct = $total_course > 0 ? round(($c['count'] / $total_course) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($c['course'] ?? 'N/A') ?></td>
                            <td><strong><?= $c['count'] ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="flex:1; background:#f0f0f0; border-radius:4px; overflow:hidden; height:12px;">
                                        <div style="width:<?= $pct ?>%; height:100%; background:var(--sa-color); border-radius:4px;"></div>
                                    </div>
                                    <span style="font-size:11px; color:#888; width:36px;"><?= $pct ?>%</span>
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
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Daily Trend Chart
const trendLabels = <?= json_encode(array_column($daily, 'day')) ?>;
const trendData   = <?= json_encode(array_column($daily, 'count')) ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Requests',
            data: trendData,
            borderColor: '#1a237e',
            backgroundColor: 'rgba(26,35,126,0.1)',
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#1a237e',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Status Chart
const statusLabels = <?= json_encode(array_column($by_status, 'request_status')) ?>;
const statusData   = <?= json_encode(array_column($by_status, 'count')) ?>;
const statusColors = ['#95a5a6','#3498db','#27ae60','#2c3e50','#e74c3c'];

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});
</script>
</body>
</html>
