<?php
// SMTP connection test — called via AJAX from system_settings.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'test_smtp') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit();
}

require_once '../includes/phpmailer/Exception.php';
require_once '../includes/phpmailer/SMTP.php';
require_once '../includes/phpmailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$host   = trim($_POST['host']   ?? '');
$port   = intval($_POST['port'] ?? 587);
$user   = trim($_POST['user']   ?? '');
$pass   = trim($_POST['pass']   ?? '');
$secure = trim($_POST['secure'] ?? 'tls');

// If no new password submitted, pull the stored one
if (empty($pass)) {
    require_once '../config/db.php';
    $pass = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='smtp_pass'")->fetchColumn() ?? '';
}

if (empty($host) || empty($user)) {
    echo json_encode(['ok' => false, 'message' => 'SMTP Host and Username are required.']);
    exit();
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = $secure;
    $mail->Port       = $port;
    $mail->Timeout    = 10;

    // Just connect and authenticate — don't send anything
    if ($mail->smtpConnect()) {
        $mail->smtpClose();
        echo json_encode(['ok' => true, 'message' => 'Connection successful! SMTP credentials are working correctly.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Connection failed: ' . htmlspecialchars($mail->ErrorInfo)]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => htmlspecialchars($mail->ErrorInfo ?: $e->getMessage())]);
}