<?php
/**
 * Migration: Add cashier_id to orders table
 * This allows tracking which cashier created each order
 */

require_once __DIR__ . '/../_init.php';

global $connection;

try {
    echo "Starting migration: Add cashier_id to orders table...\n";

    // Check if column already exists
    $stmt = $connection->prepare("SHOW COLUMNS FROM orders LIKE 'cashier_id'");
    $stmt->execute();
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        echo "Column 'cashier_id' already exists in orders table. Skipping.\n";
    } else {
        // Add cashier_id column
        $connection->exec("
            ALTER TABLE orders 
            ADD COLUMN cashier_id INT NULL 
            AFTER id
        ");
        echo "✓ Added cashier_id column to orders table\n";

        // Add foreign key constraint
        $connection->exec("
            ALTER TABLE orders 
            ADD CONSTRAINT fk_orders_cashier 
            FOREIGN KEY (cashier_id) REFERENCES users(id) 
            ON DELETE SET NULL
        ");
        echo "✓ Added foreign key constraint\n";
    }

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>