<?php
declare(strict_types=1);

/**
 * ArenaNexus 2026 Configuration & Initialization
 */

// Error reporting: log to file, never expose to users
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Load .env variables if .env exists
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
            $value = $matches[1];
        }
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

// Configuration Constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'promptwars_challenge4');
define('DB_USER', getenv('DB_USER') ?: 'promptwars_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'promptwars_pass_123');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Set up PDO Database Connection
$db = null;
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If the database connection fails, let the developer know but don't crash entirely
    // (useful during initial setup)
    error_log("Database connection failed: " . $e->getMessage());
}

/**
 * Send a JSON HTTP response with security headers and exit.
 *
 * @param mixed $data   Data to JSON-encode.
 * @param int   $status HTTP status code.
 * @return never
 */
function sendResponse(mixed $data, int $status = 200): never {
    // Security headers — applied to every API response
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
