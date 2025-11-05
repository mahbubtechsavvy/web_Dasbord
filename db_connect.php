<?php
/**
 * db_connect.php
 * Establishes a connection to the MySQL database using MySQLi.
 * 
 * Instructions for Hostinger:
 * 1. Create a MySQL database and user in your Hostinger hPanel.
 * 2. Replace the placeholder values below with your actual database credentials.
 * 3. For better security, consider defining these constants in a file outside of your public_html directory
 *    and including it, or use Hostinger's environment variable settings if available.
 */

// --- DATABASE CREDENTIALS - CONFIGURE THESE ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_db_username'); // <-- Replace with your Hostinger DB username
define('DB_PASSWORD', 'your_db_password'); // <-- Replace with your Hostinger DB password
define('DB_NAME', 'your_db_name');       // <-- Replace with your Hostinger DB name

// --- ESTABLISH CONNECTION ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check for connection errors
if ($conn->connect_error) {
    // In a production environment, you should log this error instead of displaying it publicly.
    // For this project, we'll terminate with a clear error message.
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set the character set to utf8mb4 to support a wide range of characters, including emojis.
$conn->set_charset("utf8mb4");