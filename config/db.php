<?php
// ============================================================
// DATABASE CONNECTION (Cloud & Local Compatible)
// ============================================================

// Helper to load .env file if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}

// Check for cloud connection strings (Railway / Heroku / Render)
$dbUrl = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_ENV['MYSQL_URL'] ?? '')));

if (!empty($dbUrl)) {
    $url = parse_url($dbUrl);
    $host     = $url['host'] ?? 'localhost';
    $port     = $url['port'] ?? 3306;
    $username = $url['user'] ?? 'root';
    $password = $url['pass'] ?? '';
    $dbname   = ltrim($url['path'] ?? '/edocuserve', '/');
} else {
   $host = getenv('MYSQLHOST') ?: 'localhost';
   $port = getenv('MYSQLPORT') ?: 3306;
   $db   = getenv('MYSQLDATABASE') ?: 'edocuserve';
   $user = getenv('MYSQLUSER') ?: 'root';
   $pass = getenv('MYSQLPASSWORD') ?: '';
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
