<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$request_id = intval($_POST['request_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ? AND user_id = ?");
$stmt->execute([$request_id, $user_id]);
$req  = $stmt->fetch();

if ($req && $req['payment_status'] === 'Unpaid') {
    $stmt = $pdo->prepare("
        UPDATE requests
        SET bank_slip_path = 'walkin', payment_status = 'Pending Verification'
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$request_id, $user_id]);
}

header("Location: requests.php");
exit();
?>