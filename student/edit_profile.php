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

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Collect inputs
    $last_name      = trim($_POST['last_name'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $suffix_select  = $_POST['suffix_select'] ?? '';
    $suffix_custom  = trim($_POST['suffix_custom'] ?? '');
    $date_of_birth  = trim($_POST['date_of_birth'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $mobile_number  = trim($_POST['mobile_number'] ?? '');

    // Educational
    $course         = trim($_POST['course'] ?? '');
    $year_admitted  = trim($_POST['year_admitted'] ?? '');
    $admitted_as    = trim($_POST['admitted_as'] ?? '');
    $program_status = trim($_POST['program_status'] ?? '');
    $high_school    = trim($_POST['high_school'] ?? '');
    $hs_year_grad   = trim($_POST['hs_year_grad'] ?? '');
    $elementary     = trim($_POST['elementary'] ?? '');
    $elem_year_grad = trim($_POST['elem_year_grad'] ?? '');

    // Resolve suffix
    $suffix_final = '';
    if (!empty($suffix_custom)) {
        $suffix_final = $suffix_custom;
    } elseif (!empty($suffix_select) && $suffix_select !== 'none') {
        $suffix_final = $suffix_select;
    }

    // Validations — mirror registration rules

    if (empty($last_name))  $errors[] = "Last name is required.";
    if (empty($first_name)) $errors[] = "First name is required.";

    // Middle name
    if (empty($middle_name)) {
        $errors[] = "Middle name is required. Enter \"N/A\" if you have no middle name.";
    }

    // Date of birth
    if (empty($date_of_birth)) {
        $errors[] = "Date of birth is required.";
    }

    // Mobile number
    if (empty($mobile_number)) {
        $errors[] = "Mobile number is required.";
    } elseif (strtolower($mobile_number) === 'n/a') {
        $errors[] = "Mobile number cannot be 'N/A'.";
    } elseif (!preg_match('/^\d{11}$/', $mobile_number)) {
        $errors[] = "Mobile number must be exactly 11 numeric digits (e.g. 09171234567).";
    }

    if (empty($address))     $errors[] = "Permanent address is required.";
    if (empty($course))      $errors[] = "Course/Program is required.";
    if (empty($high_school)) $errors[] = "High school name is required.";
    if (empty($elementary))  $errors[] = "Elementary school name is required.";

    // Update if valid
    if (empty($errors)) {
        $pdo->prepare("
            UPDATE users SET
                last_name = ?, first_name = ?, middle_name = ?, suffix = ?,
                date_of_birth = ?, address = ?, mobile_number = ?,
                course = ?, year_admitted = ?,
                admitted_as = ?, program_status = ?,
                high_school = ?, hs_year_grad = ?,
                elementary = ?, elem_year_grad = ?
            WHERE id = ?
        ")->execute([
            $last_name, $first_name, $middle_name,
            $suffix_final ?: null,
            $date_of_birth, $address, $mobile_number,
            $course, $year_admitted ?: null,
            $admitted_as, $program_status,
            $high_school, $hs_year_grad ?: null,
            $elementary, $elem_year_grad ?: null,
            $user_id
        ]);

        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $success = "Profile updated successfully!";
    }
}

$current_year = date('Y');

$courses = [
    'BACHELOR OF SCIENCE IN PSYCHOLOGY',
    'BACHELOR OF SECONDARY EDUCATION MAJOR IN ENGLISH',
    'BACHELOR OF SECONDARY EDUCATION MAJOR IN SOCIAL STUDIES',
    'BACHELOR OF ELEMENTARY EDUCATION',
    'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
    'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING',
    'BACHELOR OF SCIENCE IN INDUSTRIAL ENGINEERING',
    'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION MAJOR IN HUMAN RESOURCE MANAGEMENT',
    'DIPLOMA IN COMPUTER ENGINEERING TECHNOLOGY',
    'DIPLOMA IN INFORMATION TECHNOLOGY',
];

$suffix_options = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

// Determine current suffix state for the form
$db_suffix        = $user['suffix'] ?? '';
$suffix_in_list   = in_array($db_suffix, $suffix_options);
$suffix_sel_val   = '';
$suffix_cust_val  = '';
if (empty($db_suffix)) {
    $suffix_sel_val = 'none';
} elseif ($suffix_in_list) {
    $suffix_sel_val = $db_suffix;
} else {
    $suffix_sel_val  = 'other';
    $suffix_cust_val = $db_suffix;
}
// On POST re-render override with posted values
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
    $suffix_sel_val  = $_POST['suffix_select'] ?? 'none';
    $suffix_cust_val = $_POST['suffix_custom'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }

        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }

        /* NAVBAR */
        .navbar-pup { background: var(--pup-red); padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; box-shadow: 0 2px 6px rgba(0,0,0,0.3); }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; width: 38px; object-fit: contain; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.8; }
        .navbar-user { display: flex; align-items: center; gap: 10px; font-size: 13px; color: white; position: relative; }
        .navbar-user .user-name { font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-menu-custom { position: absolute; top: 110%; right: 0; background: white; border: 1px solid #ddd; border-radius: 6px; min-width: 160px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 999; }
        .dropdown-menu-custom a { display: block; padding: 9px 16px; font-size: 13px; color: #333; text-decoration: none; }
        .dropdown-menu-custom a:hover { background: #f5f5f5; }
        .dropdown-menu-custom a.text-danger { color: #dc3545; }
        .dropdown-menu-custom .divider { border-top: 1px solid #eee; margin: 4px 0; }

        /* TABS */
        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border: none; }
        .tabs-bar .nav-link { color: #666; font-size: 13px; padding: 10px 16px; border: none; border-bottom: 3px solid transparent; border-radius: 0; }
        .tabs-bar .nav-link:hover { color: var(--pup-red); background: none; }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; background: none; }
        .tabs-bar .nav-link i { margin-right: 5px; }

        /* CONTENT */
        .content-wrapper { max-width: 900px; margin: 24px auto; padding: 0 16px; }

        .section-card { background: white; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; overflow: hidden; }
        .section-header { background: #f5f5f5; padding: 11px 16px; font-weight: 700; font-size: 13px; color: #444; border-bottom: 1px solid #ddd; }
        .section-body { padding: 20px; }

        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-label .req { color: var(--pup-red); }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(109,26,26,0.1); outline: none; }
        .form-text { font-size: 11px; color: #999; margin-top: 2px; }
        .field-hint { font-size: 11px; color: #888; margin-top: 3px; }

        .suffix-wrapper { display: flex; gap: 8px; }
        .suffix-wrapper select { flex: 0 0 130px; }
        .suffix-wrapper input  { flex: 1; }

        .btn-save { background: var(--pup-red); color: white; border: none; padding: 9px 28px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #8B2020; }
        .btn-cancel { background: #f0f0f0; color: #555; border: 1px solid #ccc; padding: 9px 22px; border-radius: 4px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-cancel:hover { background: #e0e0e0; color: #333; }

        .divider-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">PUP e-DocuServe <span>Biñan Campus — Online Document Request</span></div>
    </a>
    <div class="navbar-user">
        <div class="user-name" onclick="toggleDropdown()">
            <i class="fas fa-user-circle" style="font-size:18px;"></i>
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            <i class="fas fa-caret-down" style="font-size:11px;"></i>
        </div>
        <div class="dropdown-menu-custom" id="navDropdown" style="display:none;">
            <a href="index.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <a href="account_settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a>
            <div class="divider"></div>
            <a href="../auth/logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
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

    <?php if ($success): ?>
        <div class="alert alert-success py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
            <a href="index.php" class="ms-3" style="font-size:12px;">← Back to Profile</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
            <strong><i class="fas fa-exclamation-triangle me-1"></i> Please fix the following errors:</strong>
            <ul class="mb-0 ps-3 mt-1">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>

        <!-- PERSONAL INFORMATION -->
        <div class="section-card">
            <div class="section-header"><i class="fas fa-user me-2" style="color:var(--pup-red);"></i>Personal Information</div>
            <div class="section-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" class="form-control"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? $user['last_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" class="form-control"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name']) ?>" required>
                    </div>

                    <!-- Middle Name — required; N/A accepted -->
                    <div class="col-md-4">
                        <label class="form-label">Middle Name <span class="req">*</span></label>
                        <input type="text" name="middle_name" class="form-control"
                            placeholder='Middle Name (enter "N/A" if none)'
                            value="<?= htmlspecialchars($_POST['middle_name'] ?? $user['middle_name'] ?? '') ?>">
                        <div class="field-hint">If you have no middle name, type <strong>N/A</strong>.</div>
                    </div>

                    <!-- Suffix — dropdown + custom fallback -->
                    <div class="col-md-4">
                        <label class="form-label">Suffix <span style="color:#aaa; font-weight:400;">(optional)</span></label>
                        <div class="suffix-wrapper">
                            <select name="suffix_select" id="suffixSelect" class="form-select">
                                <option value="none"  <?= $suffix_sel_val === 'none'  ? 'selected' : '' ?>>None</option>
                                <?php foreach ($suffix_options as $s): ?>
                                    <option value="<?= $s ?>" <?= $suffix_sel_val === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                                <option value="other" <?= $suffix_sel_val === 'other' ? 'selected' : '' ?>>Other...</option>
                            </select>
                            <input type="text" name="suffix_custom" id="suffixCustom"
                                class="form-control"
                                placeholder="Type suffix"
                                style="display:<?= $suffix_sel_val === 'other' ? 'block' : 'none' ?>;"
                                value="<?= htmlspecialchars($suffix_cust_val) ?>">
                        </div>
                    </div>

                    <!-- Date of Birth — required -->
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth <span class="req">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control"
                            value="<?= htmlspecialchars($_POST['date_of_birth'] ?? $user['date_of_birth'] ?? '') ?>">
                    </div>

                    <!-- Mobile Number — required, 11 digits -->
                    <div class="col-md-4">
                        <label class="form-label">Mobile Number <span class="req">*</span></label>
                        <input type="text" name="mobile_number" class="form-control"
                            id="mobileInput"
                            placeholder="e.g. 09171234567"
                            maxlength="11"
                            value="<?= htmlspecialchars($_POST['mobile_number'] ?? $user['mobile_number'] ?? '') ?>">
                        <div class="field-hint">11 digits, numbers only.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Permanent Address <span class="req">*</span></label>
                        <textarea name="address" class="form-control" rows="2"
                            placeholder="House No., Street, Barangay, City, Province"><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control"
                            value="<?= htmlspecialchars($user['email']) ?>"
                            disabled style="background:#f5f5f5; color:#888;">
                        <div class="form-text">Email cannot be changed here. Contact admin if needed.</div>
                    </div>

                </div>
            </div>
        </div>

        <!-- EDUCATIONAL INFORMATION -->
        <div class="section-card">
            <div class="section-header"><i class="fas fa-graduation-cap me-2" style="color:var(--pup-red);"></i>Educational Information</div>
            <div class="section-body">

                <div class="divider-label">PUP Information</div>
                <div class="row g-3 mb-4">

                    <div class="col-md-4">
                        <label class="form-label">Student Number</label>
                        <input type="text" class="form-control"
                            value="<?= htmlspecialchars($user['student_number'] ?? '') ?>"
                            disabled style="background:#f5f5f5; color:#888;">
                        <div class="form-text">Contact admin to update student number.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Course / Program <span class="req">*</span></label>
                        <select name="course" class="form-select">
                            <option value="">-- Select Course --</option>
                            <?php
                            $selected_course = $_POST['course'] ?? $user['course'] ?? '';
                            foreach ($courses as $c):
                            ?>
                                <option value="<?= $c ?>" <?= $selected_course === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Program Status</label>
                        <select name="program_status" class="form-select">
                            <?php $ps = $_POST['program_status'] ?? $user['program_status'] ?? 'Undergraduate'; ?>
                            <option value="Undergraduate" <?= $ps === 'Undergraduate' ? 'selected' : '' ?>>Undergraduate</option>
                            <option value="Graduate"      <?= $ps === 'Graduate'      ? 'selected' : '' ?>>Graduate</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Year Admitted</label>
                        <input type="number" name="year_admitted" class="form-control"
                            value="<?= htmlspecialchars($_POST['year_admitted'] ?? $user['year_admitted'] ?? '') ?>"
                            placeholder="e.g. 2023" min="2000" max="<?= $current_year ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Admitted As</label>
                        <select name="admitted_as" class="form-select">
                            <?php $aa = $_POST['admitted_as'] ?? $user['admitted_as'] ?? 'New Freshman'; ?>
                            <option value="New Freshman"  <?= $aa === 'New Freshman'  ? 'selected' : '' ?>>New Freshman</option>
                            <option value="Transferee"    <?= $aa === 'Transferee'    ? 'selected' : '' ?>>Transferee</option>
                            <option value="Cross Enrollee" <?= $aa === 'Cross Enrollee' ? 'selected' : '' ?>>Cross Enrollee</option>
                        </select>
                    </div>

                </div>

                <div class="divider-label">Previous Schools</div>
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">High School Graduated From <span class="req">*</span></label>
                        <input type="text" name="high_school" class="form-control"
                            placeholder="Senior High School Name"
                            value="<?= htmlspecialchars($_POST['high_school'] ?? $user['high_school'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year Graduated</label>
                        <input type="number" name="hs_year_grad" class="form-control"
                            value="<?= htmlspecialchars($_POST['hs_year_grad'] ?? $user['hs_year_grad'] ?? '') ?>"
                            placeholder="e.g. 2023" min="1990" max="<?= $current_year ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Elementary School Graduated From <span class="req">*</span></label>
                        <input type="text" name="elementary" class="form-control"
                            placeholder="Elementary School Name"
                            value="<?= htmlspecialchars($_POST['elementary'] ?? $user['elementary'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year Graduated</label>
                        <input type="number" name="elem_year_grad" class="form-control"
                            value="<?= htmlspecialchars($_POST['elem_year_grad'] ?? $user['elem_year_grad'] ?? '') ?>"
                            placeholder="e.g. 2017" min="1990" max="<?= $current_year ?>">
                    </div>

                </div>

            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div style="display:flex; gap:10px; margin-bottom:40px;">
            <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
            <a href="index.php" class="btn-cancel"><i class="fas fa-times me-2"></i>Cancel</a>
        </div>

    </form>
</div>

<!-- FOOTER -->
<div style="background:#f8f8f8; border-top:1px solid #ddd; padding:12px 20px; text-align:center; font-size:12px; color:#666;">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#" style="color:var(--pup-red); text-decoration:none;">Terms of Use</a> |
    <a href="#" style="color:var(--pup-red); text-decoration:none;">Privacy Statement</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Suffix custom input toggle
    const suffixSelect = document.getElementById('suffixSelect');
    const suffixCustom = document.getElementById('suffixCustom');

    suffixSelect.addEventListener('change', function () {
        if (this.value === 'other') {
            suffixCustom.style.display = 'block';
            suffixCustom.focus();
        } else {
            suffixCustom.style.display = 'none';
            suffixCustom.value = '';
        }
    });

    // Mobile number: digits only
    document.getElementById('mobileInput').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    // Navbar dropdown
    function toggleDropdown() {
        const d = document.getElementById('navDropdown');
        d.style.display = d.style.display === 'none' ? 'block' : 'none';
    }
    document.addEventListener('click', function (e) {
        const d = document.getElementById('navDropdown');
        if (!e.target.closest('.navbar-user')) d.style.display = 'none';
    });
</script>
</body>
</html>