<?php
/**
 * api/user/login.php
 * Handles user and vendor authentication.
 * Accepts a POST request with a JSON body.
 */

// Set response headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../db_connect.php';

/**
 * Sends a JSON response and terminates the script.
 * @param array $data The data to be encoded as JSON.
 * @param int $statusCode The HTTP status code to send.
 */
function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'error' => 'Invalid request method. Only POST is accepted.'], 405);
}

// Get raw POST data
$data = json_decode(file_get_contents("php://input"));

// --- Input Validation ---
if (!isset($data->username) || !isset($data->password) || empty(trim($data->username))) {
    send_json_response(['success' => false, 'error' => 'Username and password are required.'], 400);
}

$username = trim($data->username);
$password = $data->password;

// --- Fetch User and Verify Password ---
// Using a prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT user_id, password_hash, role FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Securely verify the password against the stored hash
    if (password_verify($password, $user['password_hash'])) {

        // Business Logic: Unapproved vendors cannot log in.
        if ($user['role'] === 'vendor') {
            $stmt_vendor = $conn->prepare("SELECT is_approved FROM vendors WHERE user_id = ?");
            $stmt_vendor->bind_param("i", $user['user_id']);
            $stmt_vendor->execute();
            $vendor_result = $stmt_vendor->get_result();

            if ($vendor_result->num_rows === 1) {
                $vendor = $vendor_result->fetch_assoc();
                if (!$vendor['is_approved']) {
                    send_json_response(['success' => false, 'error' => 'Your vendor account is pending approval.'], 403); // 403 Forbidden
                }
            } else {
                // This indicates a data integrity issue (user with role 'vendor' but no vendor record).
                send_json_response(['success' => false, 'error' => 'Vendor profile not found. Please contact support.'], 404);
            }
            $stmt_vendor->close();
        }

        // Login successful
        send_json_response([
            'success' => true,
            'message' => 'Login successful.',
            'user_id' => $user['user_id'],
            'role'    => $user['role']
        ]);

    } else {
        // Password does not match
        send_json_response(['success' => false, 'error' => 'Invalid username or password.'], 401); // 401 Unauthorized
    }
} else {
    // User does not exist
    send_json_response(['success' => false, 'error' => 'Invalid username or password.'], 401); // Use a generic error message for security
}

$stmt->close();
$conn->close();
