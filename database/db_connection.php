<?php
/* Bella Vista Oasis Spa - Central Database Connection (mysqli)
   Reads connection settings directly from php/config.php to ensure
   one unified source of truth across all PHP endpoints. */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db_connection.php' && basename($_SERVER['PHP_SELF'] ?? '') === 'db_connection.php') {
    http_response_code(404);
    exit();
}

$configFile = dirname(__DIR__) . '/php/config.php';
$cfg = file_exists($configFile) ? (include $configFile) : [];

$host = $cfg['DB_HOST'] ?? '127.0.0.1';
$user = $cfg['DB_USER'] ?? 'root';
$pass = $cfg['DB_PASS'] ?? '';
$db   = $cfg['DB_NAME'] ?? 'bellavista';
$port = (int)($cfg['DB_PORT'] ?? 3306);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    // Attempt fallback to localhost if 127.0.0.1 fails
    $conn = @new mysqli("localhost", $user, $pass, $db);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection error. Please try again later.");
    }
}

$conn->set_charset("utf8mb4");
