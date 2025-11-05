<?php
/**
 * api/user/register.php
 * Handles new user and vendor registration.
 * Accepts a POST request with a JSON body.
 */

// Set response headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Allow access from any origin (for development)
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
if (
    !isset($data->username) || !isset($data->password) || !isset($data->email) || !isset($data->role) ||
    empty(trim($data->username)) || empty(trim($data->password)) || empty(trim($data->email))
) {
    send_json_response(['success' => false, 'error' => 'Missing required fields: username, password, email, role.'], 400);
}
if (!in_array($data->role, ['user', 'vendor'])) {
    send_json_response(['success' => false, 'error' => 'Invalid role. Must be either "user" or "vendor".'], 400);
}
if ($data->role === 'vendor' && (!isset($data->company_name) || empty(trim($data->company_name)))) {
    send_json_response(['success' => false, 'error' => 'Vendor registration requires a non-empty "company_name".'], 400);
}
if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['success' => false, 'error' => 'Invalid email format.'], 400);
}

// Sanitize inputs
$username = trim($data->username);
$email = trim($data->email);
$role = $data->role;
$password_hash = password_hash(trim($data->password), PASSWORD_DEFAULT);

// --- Check for existing user ---
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    send_json_response(['success' => false, 'error' => 'A user with this username or email already exists.'], 409); // 409 Conflict
}
$stmt->close();

// --- Database Insertion with Transaction ---
$conn->begin_transaction();

try {
    // Insert into 'users' table
    $stmt_user = $conn->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
    $stmt_user->bind_param("ssss", $username, $password_hash, $email, $role);
    $stmt_user->execute();

    // If the user is a vendor, also insert into the 'vendors' table
    if ($role === 'vendor') {
        $user_id = $conn->insert_id;
        $company_name = trim($data->company_name);

        $stmt_vendor = $conn->prepare("INSERT INTO vendors (user_id, company_name, description) VALUES (?, ?, ?)");
        $description = isset($data->description) ? trim($data->description) : null;
        $stmt_vendor->bind_param("iss", $user_id, $company_name, $description);
        $stmt_vendor->execute();
        $stmt_vendor->close();
    }

    $stmt_user->close();
    $conn->commit();

    $message = "Registration successful.";
    if ($role === 'vendor') {
        $message .= " Your vendor account is now pending approval.";
    }

    send_json_response(['success' => true, 'message' => $message], 201); // 201 Created

} catch (mysqli_sql_exception $exception) {
    $conn->rollback();
    // In production, log the actual exception message, don't show it to the user.
    send_json_response(['success' => false, 'error' => 'An unexpected error occurred during registration.'], 500);
} finally {
    $conn->close();
}