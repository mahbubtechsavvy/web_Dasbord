<?php
/**
 * Database Connection
 *
 * This script establishes a connection to the MySQL database using PDO.
 * It's included in any script that needs to interact with the database.
 */

// --- Database Configuration ---
// Replace with your actual database credentials
define('DB_HOST', '127.0.0.1'); // Or your database host
define('DB_NAME', 'service_marketplace');
define('DB_USER', 'root'); // Your database username
define('DB_PASS', ''); // Your database password
define('DB_CHARSET', 'utf8mb4');

// --- PDO Connection Setup ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // In a production environment, you would log this error and show a generic message.
    // For this internal tool, dying with the error is acceptable.
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please check server logs.");
}
?>