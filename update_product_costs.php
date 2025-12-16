<?php
require_once '_init.php';

global $connection;

echo "Updating product costs...\n\n";

// Set costs based on approximate profit margins (e.g., cost = price / 1.5 for 50% profit)
$updates = [
    'Cargo' => 50,      // price 80, cost 50 = 60% profit
    'simwood' => 120,   // price 210, cost 120 = 75% profit
    'penshopee' => 150, // price 299, cost 150 = 99% profit (roughly double)
];

foreach ($updates as $productName => $cost) {
    $stmt = $connection->prepare('UPDATE products SET cost = :cost WHERE name = :name');
    $stmt->execute([':cost' => $cost, ':name' => $productName]);
    echo "✓ Updated {$productName}: cost = {$cost}\n";
}

echo "\nRecalculating expenses...\n";
$stmt = $connection->prepare('SELECT SUM(oi.quantity * p.cost) as total FROM order_items oi JOIN products p ON oi.product_id = p.id');
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalExpenses = $result['total'] ?? 0;

echo "✓ New Total Expenses: ₱" . number_format($totalExpenses, 2) . "\n";

// Show breakdown
echo "\nExpense breakdown:\n";
$stmt = $connection->prepare('SELECT p.name, SUM(oi.quantity) as total_qty, p.cost, SUM(oi.quantity * p.cost) as expense FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "  {$row['name']}: {$row['total_qty']} units × ₱{$row['cost']} = ₱" . number_format($row['expense'], 2) . "\n";
}

?>
