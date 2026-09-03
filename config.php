<?php
// Environment-based configuration. Stored secrets (DB credentials, pepper) should come from environment variables.
// This file is safe to commit because it reads secrets from env; DO NOT hardcode secrets here.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app_env = getenv('APP_ENV') ?: 'development';

// Toggle error display in production
if ($app_env === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$host = getenv('DB_HOST') ?: getenv('DATABASE_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: getenv('DATABASE_NAME') ?: 'gccp';
$user = getenv('DB_USER') ?: getenv('DATABASE_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: getenv('DATABASE_PASSWORD') ?: '';

// Optional: when running in some managed environments, DATABASE_URL may be provided.
$databaseUrl = getenv('DATABASE_URL') ?: getenv('CLEARDB_DATABASE_URL');
if ($databaseUrl) {
    // Support DATABASE_URL in the form mysql://user:pass@host/dbname
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        $host = $parts['host'] ?? $host;
        $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : $dbname;
        $user = $parts['user'] ?? $user;
        $pass = $parts['pass'] ?? $pass;
    }
}

// Read pepper from environment (do NOT store it in the DB)
$pepper = getenv('PASSWORD_PEPPER') ?: '';

// Create PDO
try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Recommended PDO defaults
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    if ($app_env === 'production') {
        error_log('Database connection failed: ' . $e->getMessage());
        // Show a generic message in production
        die('Database connection error.');
    }

    // In development show detailed error
    die('Connection failed: ' . $e->getMessage());
}

// Expose pepper to other scripts that include config.php
if (!defined('PASSWORD_PEPPER')) {
    define('PASSWORD_PEPPER', $pepper);
}
