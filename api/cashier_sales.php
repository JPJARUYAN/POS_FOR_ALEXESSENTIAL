<?php
/**
 * API Endpoint: Get cashier sales history
 * Returns orders and sales data for the authenticated cashier
 */

require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';

// Ensure only authenticated cashiers can access
Guard::cashierOnly();

header('Content-Type: application/json');

try {
    $cashier = User::getAuthenticatedUser(ROLE_CASHIER);
    $cashier_id = $cashier->id;

    // Get date filters from request
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

    global $connection;

    // Fetch orders for this cashier within date range
    $stmt = $connection->prepare("
        SELECT o.*, c.name as customer_name, c.phone as customer_phone,
               (SELECT SUM(oi.quantity * p.price) 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = o.id) as total_amount,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.cashier_id = :cashier_id
          AND DATE(o.created_at) BETWEEN :date_from AND :date_to
        ORDER BY o.created_at DESC
    ");

    $stmt->execute([
        ':cashier_id' => $cashier_id,
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ]);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $total_sales = 0;
    $total_orders = count($orders);
    $cash_sales = 0;
    $card_sales = 0;
    $ewallet_sales = 0;

    foreach ($orders as $order) {
        $amount = floatval($order['total_amount']);
        $total_sales += $amount;

        $method = $order['payment_method'] ?? 'cash';
        if ($method === 'cash') {
            $cash_sales += $amount;
        } elseif ($method === 'card') {
            $card_sales += $amount;
        } elseif ($method === 'e-wallet') {
            $ewallet_sales += $amount;
        }
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'summary' => [
            'total_sales' => $total_sales,
            'total_orders' => $total_orders,
            'cash_sales' => $cash_sales,
            'card_sales' => $card_sales,
            'ewallet_sales' => $ewallet_sales,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
