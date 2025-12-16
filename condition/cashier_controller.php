<?php

require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
require_once __DIR__ . '/../models/Customer.php';

// Ensure only authenticated cashiers can process orders
Guard::cashierOnly();

// Check if request is JSON
$isJsonRequest = false;
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $isJsonRequest = true;
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if ($jsonData) {
        $_POST = array_merge($_POST, $jsonData);
    }
}

// Handle GET request for order history
if (isset($_GET['action']) && $_GET['action'] === 'get_orders') {
    global $connection;
    
    $cashier = User::getAuthenticatedUser(ROLE_CASHIER);
    $cashier_id = $cashier ? $cashier->id : null;
    
    // Get current cashier's orders from last 30 days
    $stmt = $connection->prepare("
        SELECT o.*, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               GROUP_CONCAT(
                   JSON_OBJECT(
                       'product_id', oi.product_id,
                       'product_name', p.name,
                       'quantity', oi.quantity,
                       'price', oi.price
                   )
               ) as items
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.cashier_id = ? 
        AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute([$cashier_id]);
    $orders = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $order_data = [
            'order_id' => $row['id'],
            'created_at' => $row['created_at'],
            'total_amount' => $row['total_amount'],
            'payment_method' => $row['payment_method'],
            'customer_name' => !empty($row['customer_name']) && $row['customer_name'] !== ' ' ? trim($row['customer_name']) : null,
            'items' => !empty($row['items']) ? json_decode('[' . $row['items'] . ']', true) : []
        ];
        
        $orders[] = $order_data;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    exit;
}

if (post('action') === 'proccess_order' || (isset($_POST['items']) && is_array($_POST['items']))) {
    // Wrap the whole order creation in a transaction so stock isn't deducted
    // unless the order succeeds end-to-end.
    global $connection;
    $connection->beginTransaction();

    try {
        // Handle both old form format and new JSON format
        $cartItems = isset($_POST['cart_item']) ? $_POST['cart_item'] : (isset($_POST['items']) ? $_POST['items'] : null);

        // Basic validation
        if (!$cartItems || !is_array($cartItems) || count($cartItems) === 0) {
            throw new Exception('No cart items submitted');
        }

        // Capture payment details
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
        $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : null;
        $change_amount = isset($_POST['change_amount']) ? floatval($_POST['change_amount']) : null;

        // Validate payment method
        $valid_methods = ['cash', 'card', 'e-wallet'];
        if (!in_array($payment_method, $valid_methods)) {
            $payment_method = 'cash';
        }

        // Capture customer info if provided; otherwise create an anonymous 'Guest' customer
        $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : null;
        $customer_email = isset($_POST['customer_email']) ? trim($_POST['customer_email']) : null;
        $customer_phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : null;

        if ($customer_name || $customer_email || $customer_phone) {
            $customer_id = Customer::findOrCreate($customer_name, $customer_email, $customer_phone);
        } else {
            // create anonymous customer with sequential numeric id as name (1,2,3...)
            $customer_id = Customer::findOrCreate(null, null, null);
        }

        // Get current cashier's ID
        $cashier = User::getAuthenticatedUser(ROLE_CASHIER);
        $cashier_id = $cashier ? $cashier->id : null;

        // Create order with payment details
        $order = Order::create($customer_id, $cashier_id, $payment_method, $payment_amount, $change_amount);

        foreach ($cartItems as $item) {
            // Handle both formats: cart_item[n][id] and items[n].id
            $itemId = isset($item['id']) ? $item['id'] : null;
            $itemQty = isset($item['quantity']) ? $item['quantity'] : null;

            if (!$itemId || !$itemQty)
                continue;

            // Convert to expected format if from JSON
            $orderItem = [
                'id' => $itemId,
                'quantity' => $itemQty
            ];
            if (isset($item['size'])) {
                $orderItem['size'] = $item['size'];
            }

            OrderItem::add($order->id, $orderItem);
        }

        flashMessage('transaction', 'Successful transaction.', FLASH_SUCCESS);

        // If the client expects JSON (AJAX), return JSON with order info
        $isAjax = $isJsonRequest;
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        if (strpos($accept, 'application/json') !== false)
            $isAjax = true;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            $isAjax = true;

        // Everything went fine, persist the changes
        $connection->commit();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'order_id' => $order->id,
                'created_at' => $order->created_at,
                'payment_method' => $payment_method,
                'payment_amount' => $payment_amount,
                'change_amount' => $change_amount
            ]);
            exit;
        }

        // Fallback for regular form submission
        redirect('../index.php');

    } catch (Exception $e) {
        // On any failure, revert deductions/changes
        if ($connection && $connection->inTransaction()) {
            $connection->rollBack();
        }

        // If AJAX, return JSON error
        $isAjax = $isJsonRequest;
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        if (strpos($accept, 'application/json') !== false)
            $isAjax = true;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            $isAjax = true;

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }

        // non-AJAX fallback: flash and redirect
        flashMessage('transaction', 'Transaction failed: ' . $e->getMessage(), FLASH_ERROR);
        redirect('../index.php');
    }
}
