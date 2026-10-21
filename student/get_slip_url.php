<?php
/**
 * get_slip_url.php — Returns a fresh signed slip URL for a request owned by the current student.
 * Called via AJAX from the View Details modal.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/slip_url.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$request_id = intval($_GET['request_id'] ?? 0);
if ($request_id <= 0) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Verify the request belongs to this student
$stmt = $pdo->prepare("SELECT bank_slip_path FROM requests WHERE id = ? AND user_id = ?");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['error' => 'Not found']);
    exit();
}

$url = getSlipUrl($row['bank_slip_path']);
echo json_encode(['url' => $url]);
