<?php
/**
 * verify_email.php — Handles the email verification link click.
 *
 * Valid token   → inserts into users (Pending Verification) → notifies admins → redirects to login.
 * Expired/used  → shows error page with link back to register.php.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

$token     = trim($_GET['token'] ?? '');
$pageError = '';

if (empty($token)) {
    $pageError = 'Invalid verification link. Please register again.';
} else {
    // Look up the token — must not be expired
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $pending = $stmt->fetch();

    if (!$pending) {
        // Check if it existed but expired (vs never existed / already used)
        $expStmt = $pdo->prepare("SELECT id FROM email_verifications WHERE token = ?");
        $expStmt->execute([$token]);
        if ($expStmt->fetch()) {
            $pageError = 'This verification link has expired (links are valid for 24 hours). Please register again.';
            $pdo->prepare("DELETE FROM email_verifications WHERE token = ?")->execute([$token]);
        } else {
            $pageError = 'This verification link is invalid or has already been used.';
        }
    } else {
        $formData = json_decode($pending['form_data'], true);
        $email    = $pending['email'];

        // Check if email or student number already in users (edge case: admin added them manually)
        $existing = $pdo->prepare("SELECT id FROM users WHERE email = ? OR student_number = ?");
        $existing->execute([$email, $pending['student_number']]);
        $alreadyExists = $existing->fetch();

        if (!$alreadyExists) {
            try {
                $pdo->prepare("
                    INSERT INTO users (
                        student_number, last_name, first_name, middle_name, suffix,
                        email, password, date_of_birth, address,
                        mobile_number, course,
                        year_admitted, admitted_as, program_status,
                        high_school, hs_year_grad, elementary, elem_year_grad,
                        status, verification_status
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        'Active', 'Pending Verification'
                    )
                ")->execute([
                    $formData['student_number'],
                    $formData['last_name'],
                    $formData['first_name'],
                    $formData['middle_name'],
                    $formData['suffix'],
                    $formData['email'],
                    $formData['password'],
                    $formData['date_of_birth'],
                    $formData['address'],
                    $formData['mobile_number'],
                    $formData['course'],
                    $formData['year_admitted'],
                    $formData['admitted_as'],
                    $formData['program_status'],
                    $formData['high_school'],
                    $formData['hs_year_grad'],
                    $formData['elementary'],
                    $formData['elem_year_grad'],
                ]);

                // Notify admins — failure must never block the user
                try {
                    $newStudent = [
                        'first_name'      => $formData['first_name'],
                        'last_name'       => $formData['last_name'],
                        'student_number'  => $formData['student_number'],
                        'email'           => $formData['email'],
                        'course'          => $formData['course'],
                        'date_registered' => date('Y-m-d H:i:s'),
                    ];
                    notifyAdminsNewRegistration($pdo, $newStudent);
                } catch (Throwable $e) {
                    error_log('Admin notification failed after email verification: ' . $e->getMessage());
                }

            } catch (PDOException $e) {
                error_log('verify_email insert failed: ' . $e->getMessage());
                $pageError = 'An error occurred completing your registration. Please contact the Registrar\'s Office.';
            }
        }

        // Whether newly inserted or already existed, delete the token and redirect
        if (empty($pageError)) {
            $pdo->prepare("DELETE FROM email_verifications WHERE token = ?")->execute([$token]);
            header("Location: login.php?registered=1");
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
    <title>Email Verification | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 14px; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card-box { background: white; border-radius: 8px; padding: 40px 36px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-box h5 { font-size: 17px; font-weight: 700; color: var(--pup-red); margin: 16px 0 10px; }
        .card-box p { color: #555; font-size: 13px; line-height: 1.6; }
        .icon-wrap { font-size: 42px; color: #e74c3c; }
        .btn-back { display: inline-block; margin-top: 20px; background: var(--pup-red); color: white; padding: 10px 28px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-back:hover { background: #5a1515; color: white; }
    </style>
</head>
<body>
    <div class="card-box">
        <div class="icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
        <h5>Verification Failed</h5>
        <p><?= htmlspecialchars($pageError) ?></p>
        <a href="register.php" class="btn-back"><i class="fas fa-redo me-2"></i>Register Again</a>
    </div>
</body>
</html>
