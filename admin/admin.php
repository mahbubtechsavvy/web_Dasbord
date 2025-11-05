<?php
// Start the session to manage user authentication state.
session_start();

// --- AUTHENTICATION & AUTHORIZATION ---
// SIMULATION: In a real application, a login form would securely set these session variables.
// For testing purposes, you can uncomment the line below to simulate an admin login.
// $_SESSION['user_role'] = 'admin';

// Security Check: Ensure the user is logged in and has the 'admin' role.
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403); // Forbidden
    die('<h1>403 Forbidden</h1><p>Access Denied. You must be an administrator to view this page.</p>');
}

// Include the database connection script.
require_once __DIR__ . '/../config/db_connect.php';

$message = ''; // To store feedback messages for the user.

// --- POST REQUEST HANDLING (Vendor Approval) ---
// Check if the form was submitted to approve a vendor.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_vendor'])) {
    // Sanitize and validate the incoming vendor ID.
    $vendor_id_to_approve = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);

    if ($vendor_id_to_approve) {
        try {
            // Prepare and execute the update query.
            $stmt = $pdo->prepare("UPDATE vendors SET is_approved = TRUE WHERE vendor_id = ?");
            $stmt->execute([$vendor_id_to_approve]);

            // Set a success message and redirect to the same page using the PRG pattern.
            // This prevents form resubmission on page refresh.
            $_SESSION['flash_message'] = "Vendor ID #{$vendor_id_to_approve} has been approved successfully.";
            header("Location: admin.php");
            exit;
        } catch (PDOException $e) {
            // In a real app, log this error.
            $message = "<div class='p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Error approving vendor: " . $e->getMessage() . "</div>";
        }
    }
}

// Check for a flash message from the session (after a redirect).
if (isset($_SESSION['flash_message'])) {
    $message = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>" . $_SESSION['flash_message'] . "</div>";
    unset($_SESSION['flash_message']); // Clear the message after displaying it once.
}

// --- DATA FETCHING (Pending Vendors) ---
// Fetch all vendors that are not yet approved. Join with the users table to get more details.
try {
    $stmt = $pdo->prepare("
        SELECT v.vendor_id, v.company_name, v.description, u.username, u.email, v.created_at
        FROM vendors v
        JOIN users u ON v.user_id = u.user_id
        WHERE v.is_approved = FALSE
        ORDER BY v.created_at ASC
    ");
    $stmt->execute();
    $pending_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a real app, log this error.
    $pending_vendors = [];
    $message = "<div class='p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Error fetching vendors: " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Vendor Approval</title>
    <!-- Using Tailwind CSS via CDN for rapid UI development -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Admin Portal</h1>
            <p class="text-gray-600">Pending Vendor Approvals</p>
        </header>

        <!-- Display any feedback messages -->
        <?php if ($message) echo $message; ?>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Company Name</th>
                            <th scope="col" class="px-6 py-3">Username</th>
                            <th scope="col" class="px-6 py-3">Email</th>
                            <th scope="col" class="px-6 py-3">Registered On</th>
                            <th scope="col" class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_vendors)): ?>
                            <tr class="bg-white border-b">
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No pending vendor approvals at this time.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_vendors as $vendor): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                        <?= htmlspecialchars($vendor['company_name']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($vendor['username']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($vendor['email']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= date('Y-m-d H:i', strtotime($vendor['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <!-- Each "Approve" button is its own form -->
                                        <form method="POST" action="admin.php" onsubmit="return confirm('Are you sure you want to approve this vendor?');">
                                            <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($vendor['vendor_id']) ?>">
                                            <button type="submit" name="approve_vendor" class="font-medium text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 rounded-lg text-sm px-4 py-2">
                                                Approve
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>