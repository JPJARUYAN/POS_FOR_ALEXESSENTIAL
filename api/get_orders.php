<?php
// Debug-friendly endpoint to fetch recent orders for the logged-in cashier
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';

// Only cashiers can access their own orders
Guard::cashierOnly();

global $connection;

$cashier = User::getAuthenticatedUser(ROLE_CASHIER);
$cashier_id = $cashier ? $cashier->id : null;

try {
    // Fetch basic order headers for the cashier (last 30 days)
    $stmt = $connection->prepare(
        "SELECT o.id, o.created_at, o.payment_method, o.customer_id,
                c.name as customer_name,
                (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id) as total_amount
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.id
         WHERE o.cashier_id = ?
         AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY o.created_at DESC
         LIMIT 100"
    );

    $stmt->execute([$cashier_id]);
    $orders = [];

    $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare statement to fetch items per order
    $itemStmt = $connection->prepare(
        "SELECT oi.product_id, p.name as product_name, oi.quantity, oi.price
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?"
    );

    foreach ($orderRows as $row) {
        $items = [];
        $itemStmt->execute([$row['id']]);
        while ($it = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'product_id' => $it['product_id'],
                'product_name' => $it['product_name'],
                'quantity' => $it['quantity'],
                'price' => $it['price']
            ];
        }

        $orders[] = [
            'order_id' => $row['id'],
            'created_at' => $row['created_at'],
            'total_amount' => $row['total_amount'],
            'payment_method' => $row['payment_method'],
            'customer_name' => !empty($row['customer_name']) && $row['customer_name'] !== ' ' ? trim($row['customer_name']) : null,
            'items' => $items
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
