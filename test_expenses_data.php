<?php
require_once '_init.php';

global $connection;

echo "=== Checking Database for Orders and Sales Data ===\n\n";

// Check tables exist
echo "1. Checking tables exist:\n";
$tables = ['orders', 'order_items', 'products', 'categories', 'users'];
foreach ($tables as $table) {
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM $table");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - $table: " . $result['count'] . " records\n";
}

echo "\n2. Sample orders:\n";
$stmt = $connection->prepare("SELECT id, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($orders) > 0) {
    foreach ($orders as $order) {
        echo "   - Order #{$order['id']} - " . $order['created_at'] . "\n";
    }
} else {
    echo "   - No orders found!\n";
}

echo "\n3. Sample order items:\n";
$stmt = $connection->prepare("SELECT oi.id, oi.product_id, oi.quantity, oi.price, p.name, p.cost FROM order_items oi JOIN products p ON oi.product_id = p.id LIMIT 5");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($items) > 0) {
    foreach ($items as $item) {
        echo "   - Item #{$item['id']}: {$item['name']} (qty={$item['quantity']}, price={$item['price']}, cost={$item['cost']})\n";
    }
} else {
    echo "   - No order items found!\n";
}

echo "\n4. Products with costs:\n";
$stmt = $connection->prepare("SELECT id, name, cost, price FROM products WHERE cost > 0 LIMIT 5");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Found " . count($products) . " products with cost > 0\n";
if (count($products) > 0) {
    foreach ($products as $product) {
        echo "   - {$product['name']}: cost={$product['cost']}, price={$product['price']}\n";
    }
}

echo "\n5. Date range for current month:\n";
$startDate = date('Y-m-01');
$endDate = date('Y-m-d');
echo "   From: $startDate to $endDate\n";

echo "\n6. Testing metrics query:\n";
$metricsQuery = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        SUM(oi.quantity) as total_items_sold,
        SUM(oi.quantity * oi.price) as total_revenue,
        SUM(oi.quantity * p.cost) as total_expenses
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
";
$stmt = $connection->prepare($metricsQuery);
$stmt->execute([$startDate, $endDate]);
$metrics = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Total Orders: " . ($metrics['total_orders'] ?? 'NULL') . "\n";
echo "   Total Items: " . ($metrics['total_items_sold'] ?? 'NULL') . "\n";
echo "   Total Revenue: " . ($metrics['total_revenue'] ?? 'NULL') . "\n";
echo "   Total Expenses: " . ($metrics['total_expenses'] ?? 'NULL') . "\n";

echo "\n✓ Check complete! If you see data above, the expense report should work.\n";
echo "✓ If no orders found, you need to create some test sales first.\n";
?>
