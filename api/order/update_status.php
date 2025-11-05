<?php
/**
 * API Endpoint: Update Order Status
 *
 * Allows a vendor to update the status of one of their orders.
 * Crucially, it verifies that the vendor owns the order before updating.
 *
 * @method POST
 * @param int    vendor_id   - The ID of the vendor making the request.
 * @param int    order_id    - The ID of the order to update.
 * @param string new_status  - The new status for the order.
 * @return JSON - A simple success or failure message.
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
$allowed_statuses = ['confirmed', 'in_progress', 'completed', 'cancelled'];
if (
    !isset($data->vendor_id) ||
    !isset($data->order_id) ||
    !isset($data->new_status) ||
    !is_numeric($data->vendor_id) ||
    !is_numeric($data->order_id) ||
    !in_array($data->new_status, $allowed_statuses)
) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid input. Please provide a valid vendor_id, order_id, and new_status (confirmed, in_progress, completed, cancelled).'
    ]);
    exit;
}

// 5. Sanitize and Prepare Data
$vendor_id = (int)$data->vendor_id;
$order_id = (int)$data->order_id;
$new_status = htmlspecialchars(strip_tags($data->new_status));

// 6. Security Check: Verify Vendor Ownership of the Order
$check_sql = "SELECT vendor_id FROM orders WHERE order_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $order_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
    $check_stmt->close();
    $conn->close();
    exit;
}

$order = $result->fetch_assoc();
if ($order['vendor_id'] != $vendor_id) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. You do not have permission to update this order.']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();


// 7. Prepare and Execute Update Statement
$update_sql = "UPDATE orders SET status = ? WHERE order_id = ? AND vendor_id = ?";
$update_stmt = $conn->prepare($update_sql);

if ($update_stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the SQL statement.']);
    $conn->close();
    exit;
}

// Bind parameters: s = string, i = integer
$update_stmt->bind_param("sii", $new_status, $order_id, $vendor_id);

// 8. Process Result and Send Response
if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Order status updated successfully.']);
    } else {
        // This case can happen if the client tries to update to the same status
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Order status was already set to the requested value. No change made.']);
    }
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to update order status.']);
}

// 9. Close Connections
$update_stmt->close();
$conn->close();
?>