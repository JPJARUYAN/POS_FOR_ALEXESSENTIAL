<?php
/**
 * POS Controller - Handles payment processing for the cashier interface
 */

// Start output buffering to capture any stray output
ob_start();

// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files
require_once __DIR__ . '/../_init.php';

// Clear any output that may have occurred during includes
ob_end_clean();

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in (cashier or admin)
// Session keys are: user_id_cashier and user_id_admin
$user = null;
if (isset($_SESSION['user_id_cashier'])) {
    $user = User::getAuthenticatedUser(ROLE_CASHIER);
} elseif (isset($_SESSION['user_id_admin'])) {
    $user = User::getAuthenticatedUser(ROLE_ADMIN);
}

if (!$user) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Handle POST request for order creation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
if (empty($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items in cart']);
    exit;
}

$items = $input['items'];
$payment = floatval($input['payment'] ?? 0);
$total = floatval($input['total'] ?? 0);
$change = floatval($input['change'] ?? 0);
$paymentMethod = $input['payment_method'] ?? 'cash';

// Validate payment
if ($payment < $total) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Insufficient payment amount']);
    exit;
}

try {
    // Start transaction
    global $connection;
    $connection->beginTransaction();

    // Skip customer creation - just set to null
    $customerId = null;

    // Create order
    $order = Order::create(
        $customerId,           // customer_id
        $user->id,             // cashier_id
        $paymentMethod,        // payment_method
        $payment,              // payment_amount
        $change                // change_amount
    );

    if (!$order) {
        throw new Exception('Failed to create order');
    }

    // Add order items
    foreach ($items as $item) {
        $productId = $item['product_id'] ?? $item['id'] ?? null;

        if (empty($productId)) {
            throw new Exception('Invalid item: missing product_id');
        }

        $itemData = [
            'id' => intval($productId),
            'quantity' => intval($item['quantity'] ?? 1),
            'size' => $item['size'] ?? null
        ];

        OrderItem::add($order->id, $itemData);
    }

    // Commit transaction
    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order->id,
        'change' => $change
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
