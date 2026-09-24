<?php
$admin_display_name = htmlspecialchars($admin_name ?? 'Administrator');
$admin_role_label = htmlspecialchars($_SESSION['admin_role'] === 'superadmin'
    ? 'Super Administrator'
    : 'Registrar Admin');
?>
<style>
    .admin-profile-menu {
        position: relative;
        display: flex;
        align-items: center;
    }
    .admin-profile-menu-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 0;
        background: transparent;
        color: #666;
        font-family: 'Segoe UI', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        padding: 6px 8px;
        border-radius: 6px;
    }
    .admin-profile-menu-toggle:hover,
    .admin-profile-menu-toggle:focus {
        background: #f5f5f5;
        outline: none;
    }
    .admin-profile-menu-toggle .fa-user-circle {
        color: var(--sa-color, #1a237e);
        font-size: 18px;
    }
    .admin-profile-menu-toggle .fa-caret-down {
        color: #777;
    }
    .admin-profile-menu .dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        z-index: 1060;
        display: none;
        min-width: 210px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        padding: 5px;
        font-size: 13px;
    }
    .admin-profile-menu-toggle[aria-expanded="true"] + .dropdown-menu {
        display: block;
    }
    .admin-profile-menu .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 4px;
        color: #333;
        text-decoration: none;
    }
    .admin-profile-menu .dropdown-item:hover,
    .admin-profile-menu .dropdown-item:focus {
        background: #f5f5f5;
        outline: none;
    }
    .admin-profile-menu .dropdown-item.text-danger:hover,
    .admin-profile-menu .dropdown-item.text-danger:focus {
        background: #fff1f1;
        color: #b00020;
    }
    .admin-profile-menu .dropdown-divider {
        margin: 5px 0;
    }
    .admin-profile-role {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        background: var(--sa-color, #1a237e);
        color: #fff;
    }
    @media (max-width: 575.98px) {
        .admin-profile-menu-toggle {
            max-width: 175px;
        }
        .admin-profile-menu-toggle .admin-profile-name {
            max-width: 112px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-profile-menu .dropdown-menu {
            left: 0;
            right: auto;
        }
    }
</style>

<div class="admin-profile-menu">
    <button class="admin-profile-menu-toggle" type="button"
        data-bs-toggle="dropdown" aria-expanded="false"
        aria-label="Open admin account menu">
        <i class="fas fa-user-circle" aria-hidden="true"></i>
        <span class="admin-profile-name"><?= $admin_display_name ?></span>
        <i class="fas fa-caret-down" aria-hidden="true"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <span class="admin-profile-role"><?= $admin_role_label ?></span>
        <hr class="dropdown-divider">
        <a class="dropdown-item" href="<?= $account_href ?? 'account_settings.php' ?>">
            <i class="fas fa-cog me-2" aria-hidden="true"></i>Account Settings
        </a>
        <a class="dropdown-item text-danger" href="<?= $logout_href ?? '../auth/logout.php' ?>">
            <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>Logout
        </a>
    </div>
</div>
