<?php
require_once '_init.php';

global $connection;

// Check if cost column exists
$stmt = $connection->prepare("SHOW COLUMNS FROM products LIKE 'cost'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✓ Cost column EXISTS in products table\n";
    echo "  Type: " . $result['Type'] . "\n";
    echo "  Null: " . $result['Null'] . "\n";
    echo "  Default: " . $result['Default'] . "\n\n";
} else {
    echo "✗ Cost column NOT FOUND in products table\n";
    echo "  You need to run the migration: migrations/add_product_cost_and_size.sql\n\n";
}

// Try the expenses query
echo "Testing expense query...\n";
$stmt = $connection->prepare('SELECT SUM(oi.quantity * p.cost) as total_expenses FROM order_items oi JOIN products p ON oi.product_id = p.id');
try {
    $stmt->execute();
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Query executed successfully\n";
    echo "  Total Expenses: " . ($res['total_expenses'] ?? 'NULL') . "\n\n";
} catch (Exception $e) {
    echo "✗ Query failed: " . $e->getMessage() . "\n\n";
}

// Check for products with cost values
echo "Products with cost values:\n";
$stmt = $connection->prepare('SELECT id, name, cost, price FROM products WHERE cost > 0 LIMIT 5');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "  - {$row['name']}: cost={$row['cost']}, price={$row['price']}\n";
    }
} else {
    echo "  No products with cost > 0 found. You may need to add products with cost values.\n";
}

echo "\n✓ Verification complete\n";
?>
