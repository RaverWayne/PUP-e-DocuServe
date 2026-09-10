<?php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../student/index.php");
    exit();
}
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'];
    header("Location: ../" . ($role === 'superadmin' ? 'superadmin' : 'admin') . "/index.php");
    exit();
}

$error = '';
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        // Fetch regardless of status (need distinct messages below)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Only block Rejected accounts
            if ($user['verification_status'] === 'Rejected') {
                $error = "Your account registration has been rejected. Please contact the Registrar's Office for assistance.";
            } elseif (($user['status'] ?? 'Active') !== 'Active') {
                $error = "Your account has been deactivated. Please contact the Registrar's Office.";
            } else {
                // Allow both Active AND Pending Verification to log in
                $_SESSION['user_id']                  = $user['id'];
                $_SESSION['user_name']                = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email']               = $user['email'];
                $_SESSION['user_verification_status'] = $user['verification_status'];
                $_SESSION['last_activity']            = time();
                header("Location: ../student/index.php");
                exit();
            }
        } else {
            // Not a student match — check admins
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? AND status = 'Active'");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                header("Location: ../" . ($admin['role'] === 'superadmin' ? 'superadmin' : 'admin') . "/index.php");
                exit();
            }

            $error = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PUP e-DocuServe</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        .btn-nav-register {
            background: transparent;
            color: white;
            border: 1px solid white;
            padding: 6px 18px;
            border-radius: 4px;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-nav-register:hover {
            background: white;
            color: var(--pup-red);
        }

        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .login-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .login-card-header {
            background: var(--pup-red);
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-card-header img {
            height: 36px;
            width: 36px;
            object-fit: contain;
        }

        .login-card-header .header-text {
            font-size: 16px;
            font-weight: 700;
        }

        .login-card-header .header-sub {
            font-size: 11px;
            opacity: 0.85;
        }

        .login-card-body {
            padding: 28px 24px;
        }

        .login-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--pup-red);
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }

        .form-control {
            font-size: 13px;
            border-radius: 4px;
            border: 1px solid #ccc;
            padding: 8px 10px;
        }

        .form-control:focus {
            border-color: var(--pup-red);
            box-shadow: 0 0 0 2px rgba(139,0,0,0.1);
        }

        .forgot-link {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            display: block;
            text-align: right;
            margin-top: 4px;
        }

        .forgot-link:hover {
            color: var(--pup-red);
            text-decoration: underline;
        }

        .terms-text {
            font-size: 11px;
            color: #888;
            margin: 14px 0;
            line-height: 1.5;
        }

        .terms-text a {
            color: var(--pup-red);
            text-decoration: none;
        }

        .terms-text a:hover { text-decoration: underline; }

        .login-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-register {
            background: transparent;
            color: var(--pup-red);
            border: 1px solid var(--pup-red);
            padding: 8px 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-register:hover {
            background: var(--pup-red);
            color: white;
        }

        .btn-login {
            background: var(--pup-red);
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-login:hover { background: #8B2020; }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .toggle-pass {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            font-size: 13px;
        }

        .password-wrapper .toggle-pass:hover { color: var(--pup-red); }

        .footer-pup {
            background: #f8f8f8;
            border-top: 1px solid #ddd;
            padding: 12px 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .footer-pup a { color: var(--pup-red); text-decoration: none; }
        .footer-pup a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="../index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP Logo" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
    </a>
    <div>
        <a href="register.php" class="btn-nav-register">Register</a>
    </div>
</nav>

<!-- LOGIN WRAPPER -->
<div class="login-wrapper">
    <div class="login-card">

        <div class="login-card-header">
            <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
            <div>
                <div class="header-text">PUP e-DocuServe</div>
                <div class="header-sub">Biñan Campus — Online Document Request</div>
            </div>
        </div>

        <div class="login-card-body">
            <div class="login-title">Login</div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2" style="font-size: 13px;">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($timeout): ?>
                <div class="alert alert-warning py-2" style="font-size: 13px;">
                    <i class="fas fa-clock me-1"></i>
                    Your session has expired due to inactivity. Please log in again.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success py-2" style="font-size: 13px;">
                    <i class="fas fa-check-circle me-1"></i>
                    Registration submitted! You can log in now. The Registrar may follow up for verification.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Username: (Email)</label>
                    <input type="email" name="email" class="form-control"
                        placeholder="Email Address"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autofocus>
                </div>

                <div class="mb-1">
                    <label class="form-label">Password:</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="passwordInput"
                            class="form-control" placeholder="Password">
                        <i class="fas fa-eye toggle-pass" id="togglePass"></i>
                    </div>
                </div>

                <a href="forgot_password.php" class="forgot-link">I forgot my username/password</a>

                <div class="terms-text">
                    By using this service, you understood and agree to the PUP Online Services
                    <a href="#">Terms of Use</a> and <a href="#">Privacy Statement</a>.
                </div>

                <div class="login-actions">
                    <a href="register.php" class="btn-register">Register</a>
                    <button type="submit" class="btn-login">Login</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> | <a href="#">Privacy Statement</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    document.getElementById('togglePass').addEventListener('click', function () {
        const input = document.getElementById('passwordInput');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>
</body>
</html>