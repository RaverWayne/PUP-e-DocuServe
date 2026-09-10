<?php
// ============================================================
// DATABASE CONNECTION — TEMPLATE
// ============================================================

$host     = 'localhost';
$dbname   = 'edocuserve';
$username = 'your_db_username_here';
$password = 'your_db_password_here';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}