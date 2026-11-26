<?php
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

$errors = [];

$current_year = date('Y');
$years        = range($current_year, 2010);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number  = trim($_POST['student_number'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $confirm_email   = trim($_POST['confirm_email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm_pass    = $_POST['confirm_password'] ?? '';
    $mobile          = trim($_POST['mobile_number'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $first_name      = trim($_POST['first_name'] ?? '');
    $middle_name     = trim($_POST['middle_name'] ?? '');
    $suffix_select   = $_POST['suffix_select'] ?? '';
    $suffix_custom   = trim($_POST['suffix_custom'] ?? '');
    $dob             = trim($_POST['date_of_birth'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $program_status  = $_POST['program_status'] ?? 'Undergraduate';
    $course          = trim($_POST['course'] ?? '');
    $year_admitted   = $_POST['year_admitted'] ?? '';
    $admitted_as     = $_POST['admitted_as'] ?? 'New Freshman';
    $high_school     = trim($_POST['high_school'] ?? '');
    $hs_year_grad    = $_POST['hs_year_grad'] ?? '';
    $elementary      = trim($_POST['elementary'] ?? '');
    $elem_year_grad  = $_POST['elem_year_grad'] ?? '';

    $suffix_final = '';
    if (!empty($suffix_custom)) {
        $suffix_final = $suffix_custom;
    } elseif (!empty($suffix_select) && $suffix_select !== 'none') {
        $suffix_final = $suffix_select;
    }

    if (empty($student_number)) {
        $errors[] = "Student Number is required.";
    } elseif (!preg_match('/^\d{4}-\d{5}-BN-0$/', $student_number)) {
        $errors[] = "Student Number format is invalid. Expected format: YYYY-XXXXX-BN-0 (e.g. 2023-00001-BN-0).";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE student_number = ?");
        $stmt->execute([$student_number]);
        if ($stmt->fetch()) {
            $errors[] = "This Student Number is already registered. Please check your number or contact the Registrar.";
        }
    }

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (strtolower($email) === 'n/a') {
        $errors[] = "Email address cannot be 'N/A'.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($email !== $confirm_email) {
        $errors[] = "Email addresses do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already registered. Please use a different email.";
        }
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z).";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z).";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9).";
    } elseif ($password !== $confirm_pass) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile number is required.";
    } elseif (strtolower($mobile) === 'n/a') {
        $errors[] = "Mobile number cannot be 'N/A'.";
    } elseif (!preg_match('/^\d{11}$/', $mobile)) {
        $errors[] = "Mobile number must be exactly 11 numeric digits (e.g. 09171234567).";
    }

    if (empty($last_name))  $errors[] = "Last name is required.";
    if (empty($first_name)) $errors[] = "First name is required.";

    if (empty($middle_name)) {
        $errors[] = "Middle name is required. Enter \"N/A\" if you have no middle name.";
    }

    // Date of birth: must be a real Y-m-d date with a 4-digit year
    if (empty($dob)) {
        $errors[] = "Date of birth is required.";
    } else {
        $d     = DateTime::createFromFormat('Y-m-d', $dob);
        $valid = $d && $d->format('Y-m-d') === $dob;

        if (!$valid) {
            $errors[] = "Date of birth is invalid. Please use a 4-digit year.";
        } elseif ($d > new DateTime('today')) {
            $errors[] = "Date of birth cannot be in the future.";
        } elseif ((int)$d->format('Y') < 1920) {
            $errors[] = "Date of birth year must be 1920 or later.";
        }
    }

    if (empty($address)) $errors[] = "Permanent address is required.";

    if (empty($course)) {
        $errors[] = "Course/Program is required.";
    } elseif (!in_array($course, $courses, true)) {
        $errors[] = "Please select a valid Course/Program.";
    }

    if (empty($year_admitted)) {
        $errors[] = "Year admitted is required.";
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Store all form data as JSON — do NOT insert into users yet.
            // The user row is only created after they click the verification link.
            $formData = json_encode([
                'student_number' => $student_number,
                'last_name'      => $last_name,
                'first_name'     => $first_name,
                'middle_name'    => $middle_name,
                'suffix'         => $suffix_final ?: null,
                'email'          => $email,
                'password'       => $hashed_password,
                'date_of_birth'  => $dob,
                'address'        => $address,
                'mobile_number'  => $mobile,
                'course'         => $course,
                'year_admitted'  => $year_admitted ?: null,
                'admitted_as'    => $admitted_as,
                'program_status' => $program_status,
                'high_school'    => $high_school,
                'hs_year_grad'   => $hs_year_grad ?: null,
                'elementary'     => $elementary,
                'elem_year_grad' => $elem_year_grad ?: null,
            ]);

            $token     = bin2hex(random_bytes(32)); // 64-char secure token
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // INSERT or replace if same email re-submits before expiry
            $pdo->prepare("
                INSERT INTO email_verifications (token, student_number, email, form_data, expires_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    token          = VALUES(token),
                    student_number = VALUES(student_number),
                    form_data      = VALUES(form_data),
                    expires_at     = VALUES(expires_at)
            ")->execute([$token, $student_number, $email, $formData, $expiresAt]);

            $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $verifyUrl = $scheme . '://' . $host . '/edocuserve/auth/verify_email.php?token=' . urlencode($token);

            // Send verification email — failure must never block the page
            try {
                sendMail($pdo, $email, $first_name,
                    'Verify Your Email — PUP e-DocuServe',
                    emailVerifyAddress($first_name, $verifyUrl));
            } catch (Throwable $e) {
                error_log('Verification email send failed: ' . $e->getMessage());
            }

        } catch (PDOException $e) {
            error_log('Email verification store failed: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                $errors[] = "This Student Number or email is already registered.";
            } else {
                $errors[] = "Registration failed. Please check your details and try again.";
            }
        }

        if (empty($errors)) {
            header("Location: login.php?verify_sent=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pup-red: #6D1A1A;
            --pup-gold: #FFD700;
        }

        body {
            background-color: #f0f0f0;
            font-family: 'Segoe UI', sans-serif;
            font-size: 13px;
        }

        .navbar-pup {
            background-color: var(--pup-red);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        .navbar-pup .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .navbar-pup .brand img { height: 40px; }

        .navbar-pup .brand-text {
            color: white;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.3;
        }

        .navbar-pup .brand-text span {
            display: block;
            font-size: 11px;
            font-weight: 400;
            opacity: 0.85;
        }

        .navbar-pup .nav-actions { display: flex; gap: 8px; }

        .btn-nav-login {
            background: var(--pup-gold);
            color: #1a1a1a;
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-nav-login:hover { background: #e6c200; color: #1a1a1a; }

        .register-wrapper {
            max-width: 960px;
            margin: 30px auto;
            padding: 0 16px 40px;
        }

        .register-notice {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 5px;
            padding: 10px 16px;
            font-size: 13px;
            margin-bottom: 20px;
            color: #555;
        }

        .register-notice strong { color: var(--pup-red); }

        .register-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
        }

        .register-title i { color: var(--pup-red); }

        .section-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .section-header {
            background: #e8e8e8;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ddd;
        }

        .section-body { padding: 20px; }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }

        .form-label .req { color: var(--pup-red); }

        .form-control, .form-select {
            font-size: 13px;
            border-radius: 4px;
            border: 1px solid #ccc;
            padding: 7px 10px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--pup-red);
            box-shadow: 0 0 0 2px rgba(109,26,26,0.12);
        }

        .field-hint {
            font-size: 11px;
            color: #888;
            margin-top: 3px;
        }

        .field-hint.warn { color: var(--pup-red); }

        .program-status-group {
            display: flex;
            gap: 20px;
            margin-bottom: 12px;
        }

        .program-status-group label {
            font-size: 13px;
            cursor: pointer;
        }

        .btn-submit {
            background: var(--pup-red);
            color: white;
            border: none;
            padding: 10px 32px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover { background: #8B2020; }

        .footer-pup {
            background: #f8f8f8;
            border-top: 1px solid #ddd;
            padding: 12px 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .footer-pup a { color: var(--pup-red); text-decoration: none; }

        .suffix-wrapper { display: flex; gap: 8px; }
        .suffix-wrapper select { flex: 0 0 120px; }
        .suffix-wrapper input  { flex: 1; }

        .password-wrapper { position: relative; }
        .password-wrapper .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 13px;
            z-index: 5;
            background: none;
            border: none;
            padding: 0;
        }
        .password-wrapper .toggle-pass:hover { color: var(--pup-red); }
        .password-wrapper .form-control { padding-right: 36px; }

        .pwd-checklist {
            list-style: none;
            padding-left: 0;
            margin-top: 8px;
            margin-bottom: 0;
            font-size: 11px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 8px 12px;
        }
        .pwd-req {
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #777;
            transition: all 0.2s;
        }
        .pwd-req:last-child { margin-bottom: 0; }
        .pwd-req.valid { color: #27ae60; font-weight: 600; }
        .pwd-req.valid i { color: #27ae60; }
        .pwd-req.invalid { color: #888; }
        .pwd-req.invalid i { color: #bbb; }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="../index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP Logo" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
    </a>
    <div class="nav-actions">
        <a href="login.php" class="btn-nav-login">Login</a>
    </div>
</nav>

<div class="register-wrapper">

    <div class="register-notice">
         <strong>For all students and graduates of PUP Biñan Campus, including bachelor’s degree and diploma programs.</strong>
    </div>

    <p class="text-muted mb-1" style="font-size:13px;">Use the form below to create a new account.</p>
    <div class="register-title"><i class="fas fa-pencil-alt me-2"></i>Register</div>
    <hr class="mb-4">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="font-size:13px;">
            <strong><i class="fas fa-exclamation-triangle me-1"></i> Please fix the following errors:</strong>
            <ul class="mb-0 ps-3 mt-1">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="row g-4">

            <div class="col-md-6">

                <div class="section-card">
                    <div class="section-header">Account Information</div>
                    <div class="section-body">

                        <!-- Student Number FIRST (per spec) -->
                        <div class="mb-3">
                            <label for="student_number" class="form-label">Student Number <span class="req">*</span></label>
                            <input type="text" id="student_number" name="student_number" class="form-control"
                                placeholder="e.g. 2023-00001-BN-0"
                                aria-required="true"
                                aria-describedby="snHint"
                                value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>">
                            <div id="snHint" class="field-hint">Format: YYYY-XXXXX-BN-0</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="req">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Email"
                                aria-required="true"
                                aria-describedby="emailHint"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_email" class="form-label">Confirm Email <span class="req">*</span></label>
                            <input type="email" id="confirm_email" name="confirm_email" class="form-control" placeholder="Repeat Email"
                                aria-required="true"
                                value="<?= htmlspecialchars($_POST['confirm_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="req">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" class="form-control" placeholder="Password"
                                    aria-required="true"
                                    aria-describedby="pwdChecklist"
                                    autocomplete="new-password">
                                <button type="button" class="toggle-pass" id="togglePasswordBtn" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <ul class="pwd-checklist" id="pwdChecklist" aria-live="polite">
                                <li id="req-len" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 8 characters</li>
                                <li id="req-upper" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 uppercase letter (A-Z)</li>
                                <li id="req-lower" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 lowercase letter (a-z)</li>
                                <li id="req-num" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> At least 1 number (0-9)</li>
                                <li id="req-match" class="pwd-req invalid"><i class="fas fa-circle-xmark"></i> Passwords match</li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="req">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat Password"
                                    aria-required="true"
                                    autocomplete="new-password">
                                <button type="button" class="toggle-pass" id="toggleConfirmBtn" aria-label="Toggle confirm password visibility">
                                    <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label for="mobile_number" class="form-label">Mobile Number <span class="req">*</span></label>
                            <input type="text" id="mobile_number" name="mobile_number" class="form-control"
                                placeholder="e.g. 09171234567"
                                maxlength="11"
                                aria-required="true"
                                aria-describedby="mobileHint"
                                value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>">
                            <div id="mobileHint" class="field-hint">11 digits, numbers only.</div>
                        </div>

                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">Educational Information</div>
                    <div class="section-body">

                        <div class="mb-3">
                            <label class="form-label">Program Status <span class="req">*</span></label>
                            <div class="program-status-group">
                                <label>
                                    <input type="radio" name="program_status" value="Undergraduate"
                                        <?= (!isset($_POST['program_status']) || $_POST['program_status'] === 'Undergraduate') ? 'checked' : '' ?>>
                                    Undergraduate (Unfinished Degree)
                                </label>
                                <label>
                                    <input type="radio" name="program_status" value="Graduate"
                                        <?= (isset($_POST['program_status']) && $_POST['program_status'] === 'Graduate') ? 'checked' : '' ?>>
                                    Graduate (Finished Degree)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Course/Program <span class="req">*</span></label>
                            <select name="course" class="form-select">
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>" <?= (isset($_POST['course']) && $_POST['course'] === $c) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Year Admitted <span class="req">*</span></label>
                                <select name="year_admitted" class="form-select">
                                    <option value="">Year</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>" <?= (isset($_POST['year_admitted']) && $_POST['year_admitted'] == $y) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Admitted As <span class="req">*</span></label>
                                <select name="admitted_as" class="form-select">
                                    <option value="New Freshman" <?= (!isset($_POST['admitted_as']) || $_POST['admitted_as'] === 'New Freshman') ? 'selected' : '' ?>>New Freshman</option>
                                    <option value="Transferee"   <?= (isset($_POST['admitted_as']) && $_POST['admitted_as'] === 'Transferee')   ? 'selected' : '' ?>>Transferee</option>
                                    <option value="Cross Enrollee" <?= (isset($_POST['admitted_as']) && $_POST['admitted_as'] === 'Cross Enrollee') ? 'selected' : '' ?>>Cross Enrollee</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-8">
                                <label class="form-label">High School Graduated From</label>
                                <input type="text" name="high_school" class="form-control" placeholder="High School"
                                    value="<?= htmlspecialchars($_POST['high_school'] ?? '') ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Year Graduated</label>
                                <select name="hs_year_grad" class="form-select">
                                    <option value="">Year</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>" <?= (isset($_POST['hs_year_grad']) && $_POST['hs_year_grad'] == $y) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-8">
                                <label class="form-label">Elementary School Graduated From</label>
                                <input type="text" name="elementary" class="form-control" placeholder="Elementary School"
                                    value="<?= htmlspecialchars($_POST['elementary'] ?? '') ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Year Graduated</label>
                                <select name="elem_year_grad" class="form-select">
                                    <option value="">Year</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>" <?= (isset($_POST['elem_year_grad']) && $_POST['elem_year_grad'] == $y) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

            </div><!-- /LEFT COLUMN -->

            <div class="col-md-6">

                <div class="section-card">
                    <div class="section-header">Personal Information</div>
                    <div class="section-body">

                        <div class="mb-3">
                            <label class="form-label">Last Name (while in PUP) <span class="req">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name"
                                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">First Name <span class="req">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="First Name"
                                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Middle Name (while in PUP) <span class="req">*</span></label>
                            <input type="text" name="middle_name" class="form-control"
                                placeholder='Middle Name (enter "N/A" if none)'
                                id="middleNameInput"
                                value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                            <div class="field-hint">
                                If you have no middle name, type <strong>N/A</strong> in the field above.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Suffix <span style="color:#aaa; font-weight:400;">(optional)</span></label>
                            <div class="suffix-wrapper">
                                <select name="suffix_select" id="suffixSelect" class="form-select">
                                    <option value="none" <?= (($_POST['suffix_select'] ?? 'none') === 'none') ? 'selected' : '' ?>>None</option>
                                    <?php foreach ($suffix_options as $s): ?>
                                        <option value="<?= $s ?>" <?= (isset($_POST['suffix_select']) && $_POST['suffix_select'] === $s) ? 'selected' : '' ?>>
                                            <?= $s ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="other" <?= (isset($_POST['suffix_select']) && $_POST['suffix_select'] === 'other') ? 'selected' : '' ?>>Other...</option>
                                </select>
                                <input type="text" name="suffix_custom" id="suffixCustom"
                                    class="form-control"
                                    placeholder="Type suffix"
                                    style="display:none;"
                                    value="<?= htmlspecialchars($_POST['suffix_custom'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Birth <span class="req">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control"
                                min="1920-01-01" max="<?= date('Y-m-d') ?>"
                                value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Permanent Address <span class="req">*</span></label>
                            <textarea name="address" class="form-control" rows="3"
                                placeholder="No. | Street | Barangay | City/Town | Zip Code"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            <div class="field-hint">No. | Street | Barangay | City/Town | Zip Code</div>
                        </div>

                    </div>
                </div>

                <div class="section-card">
                    <div class="section-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" name="agree_terms" required>
                            <label class="form-check-label" for="agreeTerms" style="font-size:12px;">
                                I am a student/graduate of PUP Biñan Campus and I agree to the
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" style="color: var(--pup-red);">Terms of Use</a> and
                                <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal" style="color: var(--pup-red);">Privacy Statement</a>.
                            </label>
                        </div>
                        <button type="submit" class="btn-submit w-100">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                        <div class="text-center mt-3" style="font-size:12px; color:#666;">
                            Already have an account? <a href="login.php" style="color: var(--pup-red); font-weight:600;">Login here</a>
                        </div>
                    </div>
                </div>

            </div><!-- /RIGHT COLUMN -->
        </div>
    </form>

</div><!-- /register-wrapper -->

<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Use</a> |
    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Statement</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const suffixSelect = document.getElementById('suffixSelect');
    const suffixCustom = document.getElementById('suffixCustom');

    function toggleSuffixCustom() {
        if (suffixSelect.value === 'other') {
            suffixCustom.style.display = 'block';
            suffixCustom.focus();
        } else {
            suffixCustom.style.display = 'none';
            suffixCustom.value = '';
        }
    }

    suffixSelect.addEventListener('change', toggleSuffixCustom);

    // On page load: if "other" was previously selected (form re-render after error), show it
    if (suffixSelect.value === 'other') {
        suffixCustom.style.display = 'block';
    }

    document.querySelector('input[name="mobile_number"]')?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    function setupPassToggle(btnId, iconId, inputId) {
        const btn = document.getElementById(btnId);
        const icon = document.getElementById(iconId);
        const input = document.getElementById(inputId);
        if (btn && icon && input) {
            btn.addEventListener('click', function () {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            });
        }
    }
    setupPassToggle('togglePasswordBtn', 'togglePasswordIcon', 'password');
    setupPassToggle('toggleConfirmBtn', 'toggleConfirmIcon', 'confirm_password');

    const pwdInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');

    function updatePwdRequirement(elemId, isValid) {
        const elem = document.getElementById(elemId);
        if (!elem) return;
        const icon = elem.querySelector('i');
        if (isValid) {
            elem.classList.remove('invalid');
            elem.classList.add('valid');
            if (icon) {
                icon.className = 'fas fa-circle-check';
            }
        } else {
            elem.classList.remove('valid');
            elem.classList.add('invalid');
            if (icon) {
                icon.className = 'fas fa-circle-xmark';
            }
        }
    }

    function checkPasswordComplexity() {
        if (!pwdInput) return;
        const val = pwdInput.value;
        const confVal = confirmInput ? confirmInput.value : '';

        updatePwdRequirement('req-len', val.length >= 8);
        updatePwdRequirement('req-upper', /[A-Z]/.test(val));
        updatePwdRequirement('req-lower', /[a-z]/.test(val));
        updatePwdRequirement('req-num', /[0-9]/.test(val));
        updatePwdRequirement('req-match', val.length > 0 && val === confVal);
    }

    if (pwdInput) {
        pwdInput.addEventListener('input', checkPasswordComplexity);
    }
    if (confirmInput) {
        confirmInput.addEventListener('input', checkPasswordComplexity);
    }
</script>
<?php include '../includes/legal_modals.php'; ?>
</body>
</html>