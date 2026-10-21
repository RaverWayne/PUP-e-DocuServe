<?php
/**
 * get_slip_url.php — Returns a fresh signed slip URL.
 * Shared by admin and superadmin — role is checked from session.
 * Called via AJAX from manage_requests modals.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/slip_url.php';

header('Content-Type: application/json');

$role = $_SESSION['admin_role'] ?? '';
if (!isset($_SESSION['admin_id']) || !in_array($role, ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$request_id = intval($_GET['request_id'] ?? 0);
if ($request_id <= 0) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$stmt = $pdo->prepare("SELECT bank_slip_path FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['error' => 'Not found']);
    exit();
}

echo json_encode(['url' => getSlipUrl($row['bank_slip_path'])]);
