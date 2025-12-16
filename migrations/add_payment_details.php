<?php
/**
 * Migration: Add payment details to orders table
 * This allows tracking payment method, amount, and change for each order
 */

require_once __DIR__ . '/../_init.php';

try {
    echo "Starting migration: Add payment details to orders table...\n";

    // Check if payment_method column already exists
    $stmt = $connection->prepare("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {
        echo "Columns already exist in orders table. Skipping.\n";
    } else {
        // Add payment_method column
        $connection->exec("
            ALTER TABLE orders 
            ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cash' AFTER cashier_id
        ");
        echo "✓ Added payment_method column to orders table\n";

        // Add payment_amount column
        $connection->exec("
            ALTER TABLE orders 
            ADD COLUMN payment_amount DECIMAL(10,2) NULL AFTER payment_method
        ");
        echo "✓ Added payment_amount column to orders table\n";

        // Add change_amount column
        $connection->exec("
            ALTER TABLE orders 
            ADD COLUMN change_amount DECIMAL(10,2) NULL AFTER payment_amount
        ");
        echo "✓ Added change_amount column to orders table\n";

        echo "Migration completed successfully!\n";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
