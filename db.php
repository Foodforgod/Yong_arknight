<?php
// 1. Read and parse the .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;

        if (strpos($trimmed, '=') === false) continue;
        list($name, $value) = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 2. Fetch connection configurations securely using fallback defaults
$host   = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'arknights_db';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("CRITICAL ERROR: System Database Connection Failed.");
}