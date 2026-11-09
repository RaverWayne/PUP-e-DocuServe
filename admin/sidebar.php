<?php
// Usage: include 'sidebar.php'; (pass $current_page before including)
$current_page = $current_page ?? '';
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="sidebar-brand-text">PUP e-DocuServe <span>Biñan Campus</span></div>
    </div>
    <div class="sidebar-role"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <nav class="sidebar-nav" id="adminSidebarNav">
        <div class="nav-section">Main</div>
        <a href="index.php" class="<?= $current_page==='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_requests.php" class="<?= $current_page==='requests'?'active':'' ?>"><i class="fas fa-file-alt"></i> Manage Requests</a>

        <div class="nav-section">Users</div>
        <a href="students.php" class="<?= $current_page==='students'?'active':'' ?>"><i class="fas fa-users"></i> Students</a>

        <div class="nav-section">Settings</div>
        <a href="notification_settings.php" class="<?= $current_page==='notifications'?'active':'' ?>"><i class="fas fa-bell"></i> Notification Settings</a>

        <div class="nav-section">Account</div>
        <a href="account_settings.php" class="<?= $current_page==='account'?'active':'' ?>"><i class="fas fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div style="font-size:11px; opacity:0.8;">PUP e-DocuServe v1.0</div>
        <div style="font-size:11px; color:#888;">Biñan Campus</div>
    </div>
</div>
<script>
    // Simple accordion/collapse logic (placeholder for full Task 7 requirement)
    document.addEventListener('DOMContentLoaded', () => {
        // Task 7 will implement full JS collapse/persist logic here
    });
</script>
