<?php
// Include after session_start() in any file with a POST form.

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function csrf_verify() {
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($submitted) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(403);
        die('Security check failed. Please go back, refresh the page, and try again.');
    }
}