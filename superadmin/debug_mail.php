<?php
// Force errors to actually show up, overriding whatever the server's default is
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "STEP 0: script started\n";
flush();

session_start();
echo "STEP 1: session started. admin_id in session: " . (isset($_SESSION['admin_id']) ? 'YES' : 'NO') . "\n";
flush();

try {
    require_once '../config/db.php';
    echo "STEP 2: db.php loaded, pdo connected: " . (isset($pdo) ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    echo "STEP 2 FAILED: " . $e->getMessage() . "\n";
    exit();
}
flush();

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    echo "STOPPING: not logged in as superadmin. Log in first, then revisit this URL in the same browser.\n";
    exit();
}
echo "STEP 3: superadmin check passed\n";
flush();

$toEmail = $_GET['to'] ?? '';
echo "STEP 4: 'to' param = " . var_export($toEmail, true) . "\n";
flush();

if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo "STOPPING: no valid 'to' email given. Usage: ?to=youraddress@example.com\n";
    exit();
}

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings
                          WHERE setting_key IN ('brevo_api_key','smtp_from_name','smtp_from_email')");
    $cfg  = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cfg[$row['setting_key']] = $row['setting_value'];
    }
} catch (Throwable $e) {
    echo "STEP 5 FAILED (settings query): " . $e->getMessage() . "\n";
    exit();
}

$apiKey    = $cfg['brevo_api_key']   ?? '';
$fromName  = $cfg['smtp_from_name']  ?? 'PUP e-DocuServe';
$fromEmail = $cfg['smtp_from_email'] ?? 'noreply@pup.edu.ph';

echo "STEP 5: brevo_api_key present: " . (!empty($apiKey) ? 'YES (len ' . strlen($apiKey) . ')' : 'NO/EMPTY') . "\n";
echo "STEP 5: fromName = " . var_export($fromName, true) . "\n";
echo "STEP 5: fromEmail = " . var_export($fromEmail, true) . "\n";
flush();

if (empty($apiKey) || empty($fromEmail)) {
    echo "STOPPING: config empty.\n";
    exit();
}

echo "STEP 6: curl extension loaded: " . (extension_loaded('curl') ? 'YES' : 'NO') . "\n";
flush();

$payload = [
    'sender'      => ['name' => $fromName, 'email' => $fromEmail],
    'to'          => [['email' => $toEmail, 'name' => $toEmail]],
    'subject'     => 'PUP e-DocuServe — Debug Test Email',
    'htmlContent' => '<p>Diagnostic test email.</p>',
    'textContent' => 'Diagnostic test email.',
];

echo "STEP 7: about to call curl_init...\n";
flush();

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 15,
]);

echo "STEP 8: curl configured, executing now (this may take a few seconds)...\n";
flush();

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
curl_close($ch);

echo "STEP 9: curl finished.\n";
echo "HTTP status code: $httpCode\n";
echo "curl errno: $curlErrno\n";
echo "curl error: " . ($curlError ?: '(none)') . "\n";
echo "Raw response body:\n";
echo var_export($response, true) . "\n";

if ($httpCode === 201) {
    echo "\n=== RESULT: SUCCESS ===\n";
} else {
    echo "\n=== RESULT: FAILED ===\n";
}
