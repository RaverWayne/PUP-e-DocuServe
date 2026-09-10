<?php
session_start();
require_once '../config/db.php';

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

// Fetch active announcements
$announcements = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

$upload_success = false;
$upload_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file     = $_FILES['profile_photo'];
    $allowed  = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024;

    if (!in_array($file['type'], $allowed)) {
        $upload_error = "Only JPG and PNG files are allowed.";
    } elseif ($file['size'] > $max_size) {
        $upload_error = "File size must not exceed 2MB.";
    } else {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $dest     = '../assets/uploads/' . $filename;

        if (!is_dir('../assets/uploads/')) {
            mkdir('../assets/uploads/', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            $user['profile_photo'] = $filename;
            $upload_success        = true;
        } else {
            $upload_error = "Failed to upload photo. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }

        body {
            background: #f0f0f0;
            font-family: 'Segoe UI', sans-serif;
            font-size: 13px;
        }

        /* NAVBAR */
        .navbar-pup {
            background-color: var(--pup-red);
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            position: sticky; top: 0; z-index: 999;
        }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.85; }
        .dropdown-toggle { background: none; border: none; color: white; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-toggle:focus { box-shadow: none; }

        /* TABS */
        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border-bottom: none; }
        .tabs-bar .nav-link {
            color: #555; font-size: 13px; padding: 12px 16px;
            border: none; border-bottom: 3px solid transparent;
            border-radius: 0; text-decoration: none;
            display: flex; align-items: center; gap: 6px;
        }
        .tabs-bar .nav-link:hover { color: var(--pup-red); border-bottom-color: #ddd; }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; }

        /* CONTENT */
        .content-wrapper { max-width: 960px; margin: 24px auto; padding: 0 16px; }

        .section-card { background: white; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
        .section-header {
            background: #e8e8e8; padding: 10px 16px;
            font-weight: 600; font-size: 13px; color: #333;
            border-bottom: 1px solid #ddd;
            display: flex; justify-content: space-between; align-items: center;
        }
        .section-body { padding: 0; }

        /* INFO TABLE ROWS */
        .info-row {
            display: grid;
            grid-template-columns: 220px 1fr;
            border-bottom: 1px solid #f0f0f0;
            min-height: 36px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            padding: 9px 16px;
            font-weight: 700;
            color: #333;
            font-size: 13px;
            background: #fafafa;
            border-right: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        .info-value {
            padding: 9px 16px;
            color: #333;
            font-size: 13px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .info-value .sub-pair {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-value .sub-label {
            font-weight: 700;
            color: #333;
        }

        /* EDIT BUTTON */
        .btn-edit {
            background: #f0f0f0; border: 1px solid #ccc; color: #555;
            padding: 3px 12px; border-radius: 4px; font-size: 12px;
            cursor: pointer; text-decoration: none; transition: all 0.2s;
        }
        .btn-edit:hover { background: var(--pup-red); color: white; border-color: var(--pup-red); }

        /* PHOTO */
        .profile-relative { position: relative; padding-right: 130px; }
        .photo-wrapper { position: absolute; right: 0; top: 0; text-align: center; width: 115px; }
        .photo-box {
            width: 105px; height: 115px;
            border: 1px solid #ccc; border-radius: 4px;
            overflow: hidden; background: #f5f5f5;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 6px;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
        .photo-box .no-photo { color: #aaa; font-size: 40px; }
        .btn-upload {
            background: #555; color: white; border: none;
            padding: 4px 0; border-radius: 4px; font-size: 11px;
            cursor: pointer; width: 100%; transition: background 0.2s;
        }
        .btn-upload:hover { background: var(--pup-red); }

        /* FOOTER */
        .footer-pup {
            background: #f8f8f8; border-top: 1px solid #ddd;
            padding: 12px 20px; text-align: center;
            font-size: 12px; color: #666; margin-top: 40px;
        }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
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

<!-- TABS -->
<div class="tabs-bar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="new_request.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
        <li class="nav-item"><a class="nav-link" href="requests.php"><i class="fas fa-list"></i> Requests</a></li>
        <li class="nav-item"><a class="nav-link" href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
    </ul>
</div>

<!-- CONTENT -->
<div class="content-wrapper">

    <?php if (!empty($announcements)): ?>
    <div style="margin-bottom: 16px;">
        <?php foreach ($announcements as $ann): ?>
        <div style="background: #fff8e1; border: 1px solid #f0d080; border-left: 4px solid #FFD700; border-radius: 6px; padding: 13px 16px; margin-bottom: 10px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                <i class="fas fa-bullhorn" style="color:#6D1A1A; font-size:13px;"></i>
                <strong style="font-size:13px; color:#333;"><?= htmlspecialchars($ann['title']) ?></strong>
            </div>
            <div style="font-size:13px; color:#555; line-height:1.6;"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
            <div style="font-size:11px; color:#aaa; margin-top:6px;"><i class="fas fa-clock me-1"></i><?= date('F d, Y', strtotime($ann['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($upload_success): ?>
        <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-check-circle me-1"></i> Profile photo updated successfully.
        </div>
    <?php endif; ?>
    <?php if (!empty($upload_error)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($upload_error) ?>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <div class="section-header">Profile</div>
        <div class="section-body" style="padding: 16px 20px;">
            <div class="profile-relative">

                <!-- Personal Information -->
                <div class="section-card">
                    <div class="section-header">
                        Personal Information
                        <a href="edit_profile.php" class="btn-edit"><i class="fas fa-pencil-alt me-1"></i>Edit</a>
                    </div>
                    <div class="section-body">
                        <div class="info-row">
                            <div class="info-label">Last Name:</div>
                            <div class="info-value"><?= htmlspecialchars(strtoupper($user['last_name'])) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">First Name:</div>
                            <div class="info-value"><?= htmlspecialchars(strtoupper($user['first_name'])) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Middle Name:</div>
                            <div class="info-value">
                                <?= $user['middle_name'] ? htmlspecialchars(strtoupper($user['middle_name'])) : '<span style="color:#aaa;">N/A</span>' ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth:</div>
                            <div class="info-value"><?= htmlspecialchars($user['date_of_birth']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Permanent Address:</div>
                            <div class="info-value"><?= htmlspecialchars($user['address']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email Address:</div>
                            <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Mobile Number:</div>
                            <div class="info-value"><?= htmlspecialchars($user['mobile_number']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Educational Information -->
                <div class="section-card mb-0">
                    <div class="section-header">
                        Educational Information
                        <a href="edit_profile.php" class="btn-edit"><i class="fas fa-pencil-alt me-1"></i>Edit</a>
                    </div>
                    <div class="section-body">
                        <div class="info-row">
                            <div class="info-label">Student Number:</div>
                            <div class="info-value">
                                <?= $user['student_number'] ? htmlspecialchars($user['student_number']) : '<span style="color:#aaa;">N/A</span>' ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value"><?= htmlspecialchars($user['program_status']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">School Year Admitted:</div>
                            <div class="info-value">
                                <?= htmlspecialchars($user['year_admitted']) ?>
                                <div class="sub-pair">
                                    <span class="sub-label">Admitted as:</span>
                                    <span><?= htmlspecialchars($user['admitted_as']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Course:</div>
                            <div class="info-value"><?= htmlspecialchars($user['course']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Name of High School graduated from:</div>
                            <div class="info-value">
                                <?= htmlspecialchars($user['high_school']) ?>
                                <?php if ($user['hs_year_grad']): ?>
                                    <div class="sub-pair">
                                        <span class="sub-label">Year Graduated:</span>
                                        <span><?= htmlspecialchars($user['hs_year_grad']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Name of Elementary graduated from:</div>
                            <div class="info-value">
                                <?= htmlspecialchars($user['elementary']) ?>
                                <?php if ($user['elem_year_grad']): ?>
                                    <div class="sub-pair">
                                        <span class="sub-label">Year Graduated:</span>
                                        <span><?= htmlspecialchars($user['elem_year_grad']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Photo -->
                <div class="photo-wrapper">
                    <div class="photo-box">
                        <?php if (!empty($user['profile_photo']) && file_exists('../assets/uploads/' . $user['profile_photo'])): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user no-photo"></i>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="profile_photo" id="photoInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        <button type="button" class="btn-upload" onclick="document.getElementById('photoInput').click()">
                            <i class="fas fa-camera me-1"></i> Upload
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- FOOTER -->
<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> | <a href="#">Privacy Statement</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>