<?php
/**
 * API Endpoint: Create Order
 *
 * Handles the creation of a new order by a user.
 * Accepts a POST request with JSON data.
 *
 * @method POST
 * @param int    user_id       - The ID of the user placing the order.
 * @param int    vendor_id     - The ID of the vendor for the service.
 * @param int    service_id    - The ID of the service being ordered.
 * @param string order_details - Optional text with specific instructions.
 * @param float  total_amount  - The total price of the order.
 * @return JSON - A JSON response with the status and new order_id.
 */

// Set headers for JSON response and allow POST method
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Include Database Connection
require_once __DIR__ . '/../../config/db_connect.php';

// 2. Check for POST Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit;
}

// 3. Get and Decode JSON Input
$data = json_decode(file_get_contents("php://input"));

// 4. Validate Input
if (
    !isset($data->user_id) ||
    !isset($data->vendor_id) ||
    !isset($data->service_id) ||
    !isset($data->total_amount) ||
    !is_numeric($data->user_id) ||
    !is_numeric($data->vendor_id) ||
    !is_numeric($data->service_id) ||
    !is_numeric($data->total_amount)
) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid input. Please provide user_id, vendor_id, service_id, and total_amount.']);
    exit;
}

// 5. Sanitize and Prepare Data
$user_id = (int)$data->user_id;
$vendor_id = (int)$data->vendor_id;
$service_id = (int)$data->service_id;
$total_amount = (float)$data->total_amount;
// Sanitize text field to prevent XSS
$order_details = isset($data->order_details) ? htmlspecialchars(strip_tags($data->order_details)) : null;

// The initial status is always 'pending'
$status = 'pending';

// 6. Prepare and Execute SQL Statement
$sql = "INSERT INTO orders (user_id, vendor_id, service_id, order_details, total_amount, status) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the SQL statement.']);
    $conn->close();
    exit;
}

// Bind parameters: i = integer, d = double, s = string
$stmt->bind_param("iiisds", $user_id, $vendor_id, $service_id, $order_details, $total_amount, $status);

// 7. Process Result and Send Response
if ($stmt->execute()) {
    $new_order_id = $conn->insert_id;
    http_response_code(201); // Created
    echo json_encode([
        'status' => 'success',
        'message' => 'Order created successfully.',
        'order_id' => $new_order_id
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create order.'
        // 'db_error' => $stmt->error // For debugging only, do not expose in production
    ]);
}

// 8. Close Connections
$stmt->close();
$conn->close();
?>