<?php
// Shared sidebar for superadmin pages
// Usage: include 'sidebar.php'; (pass $current_page before including)
$current_page = $current_page ?? '';
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">PUP e-DocuServe <span>Biñan Campus</span></div>
    </div>
    <div class="sidebar-role"><i class="fas fa-crown me-2"></i>Super Admin Panel</div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="index.php" class="<?= $current_page==='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php" class="<?= $current_page==='requests'?'active':'' ?>"><i class="fas fa-file-alt"></i> Manage Requests</a>
        <a href="walkin_requests.php" class="<?= $current_page==='walkin'?'active':'' ?>"><i class="fas fa-walking"></i> Walk-in Payments</a>
        <div class="nav-section">Management</div>
        <a href="students.php" class="<?= $current_page==='students'?'active':'' ?>"><i class="fas fa-users"></i> Students</a>
        <a href="manage_admins.php" class="<?= $current_page==='admins'?'active':'' ?>"><i class="fas fa-user-shield"></i> Manage Admins</a>
        <a href="manage_documents.php" class="<?= $current_page==='documents'?'active':'' ?>"><i class="fas fa-file-invoice"></i> Manage Documents</a>
        <div class="nav-section">Reports & Tools</div>
        <a href="reports.php" class="<?= $current_page==='reports'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="announcements.php" class="<?= $current_page==='announcements'?'active':'' ?>"><i class="fas fa-bullhorn"></i> Announcements</a>
        <a href="export.php" class="<?= $current_page==='export'?'active':'' ?>"><i class="fas fa-file-export"></i> Export Data</a>
        <a href="system_logs.php" class="<?= $current_page==='logs'?'active':'' ?>"><i class="fas fa-history"></i> System Logs</a>
        <a href="system_settings.php" class="<?= $current_page==='settings'?'active':'' ?>"><i class="fas fa-sliders-h"></i> System Settings</a>
        <div class="nav-section">Account</div>
        <a href="account_settings.php" class="<?= $current_page==='account'?'active':'' ?>"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="margin-bottom:6px;">Logged in as <strong style="color:#ccc;"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
    </div>
</div>
