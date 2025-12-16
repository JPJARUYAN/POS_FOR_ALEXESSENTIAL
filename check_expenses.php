<?php
require_once '_init.php';

global $connection;

echo "=== Checking Expenses Calculation ===\n\n";

// Check order items
echo "1. Order items in database:\n";
$stmt = $connection->prepare('SELECT oi.id, oi.product_id, oi.quantity, oi.price, p.name, p.cost FROM order_items oi JOIN products p ON oi.product_id = p.id LIMIT 10');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "  Order Item #{$row['id']}: {$row['name']} (qty={$row['quantity']}, price={$row['price']}, cost={$row['cost']})\n";
        echo "    → Expense for this item: " . ($row['quantity'] * ($row['cost'] ?? 0)) . "\n";
    }
} else {
    echo "  No order items found!\n";
}

echo "\n2. Products with cost values:\n";
$stmt = $connection->prepare('SELECT id, name, cost, price FROM products WHERE cost > 0');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Products with cost > 0: " . count($rows) . "\n";

echo "\n3. Total expenses calculation:\n";
$stmt = $connection->prepare('SELECT SUM(oi.quantity * p.cost) as total FROM order_items oi JOIN products p ON oi.product_id = p.id');
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Total Expenses: " . ($result['total'] ?? 0) . "\n";

echo "\n4. All products:\n";
$stmt = $connection->prepare('SELECT id, name, cost, price, quantity FROM products LIMIT 10');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "  - {$row['name']}: cost={$row['cost']}, price={$row['price']}, qty={$row['quantity']}\n";
}

?>
