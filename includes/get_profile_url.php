<?php
/**
 * get_profile_url.php — Returns a fresh signed profile photo URL for a student.
 * Shared by admin and superadmin — role is checked from session.
 * Called via AJAX from the student view modal in students.php.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/profile_url.php';

header('Content-Type: application/json');

$role = $_SESSION['admin_role'] ?? '';
if (!isset($_SESSION['admin_id']) || !in_array($role, ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = intval($_GET['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['url' => '']);
    exit();
}

$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch();

echo json_encode(['url' => $row ? getProfileUrl($row['profile_photo'] ?? '') : '']);
