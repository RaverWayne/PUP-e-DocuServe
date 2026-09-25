<?php
// Brevo API key test — called via AJAX from system_settings.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'test_brevo') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit();
}

$apiKey = trim($_POST['api_key'] ?? '');

// If no new key submitted, pull the stored one
if (empty($apiKey)) {
    require_once '../config/db.php';
    $apiKey = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='brevo_api_key'")->fetchColumn() ?? '';
}

if (empty($apiKey)) {
    echo json_encode(['ok' => false, 'message' => 'Brevo API Key is required.']);
    exit();
}

$ch = curl_init('https://api.brevo.com/v3/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 10,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['ok' => false, 'message' => 'Connection failed: ' . htmlspecialchars($curlError)]);
    exit();
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $email = $data['email'] ?? 'unknown account';
    echo json_encode(['ok' => true, 'message' => 'Connection successful! Verified as ' . htmlspecialchars($email) . '.']);
} elseif ($httpCode === 401) {
    echo json_encode(['ok' => false, 'message' => 'Invalid API key. Make sure you copied an API key (not an SMTP key) from Brevo.']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Brevo returned HTTP ' . $httpCode . '. ' . htmlspecialchars($response)]);
}