<?php
/**
 * Migration: Add tax rate columns to products and categories tables
 * This allows setting different tax rates per product or category
 */

require_once __DIR__ . '/../_init.php';

try {
    global $connection;

    // Check if tax_rate column exists in categories
    $stmt = $connection->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME='categories' AND COLUMN_NAME='tax_rate'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Adding tax_rate column to categories table...\n";
        $connection->exec("
            ALTER TABLE categories ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 12.00 
            AFTER name
        ");
        echo "✓ Added tax_rate column to categories\n";
    } else {
        echo "✓ tax_rate column already exists in categories\n";
    }

    // Check if tax_rate column exists in products
    $stmt = $connection->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME='products' AND COLUMN_NAME='tax_rate'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Adding tax_rate column to products table...\n";
        $connection->exec("
            ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT NULL 
            AFTER supplier_id
        ");
        echo "✓ Added tax_rate column to products\n";
    } else {
        echo "✓ tax_rate column already exists in products\n";
    }

    // Check if is_taxable column exists in products (for exempt items)
    $stmt = $connection->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME='products' AND COLUMN_NAME='is_taxable'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Adding is_taxable column to products table...\n";
        $connection->exec("
            ALTER TABLE products ADD COLUMN is_taxable BOOLEAN DEFAULT 1 
            AFTER tax_rate
        ");
        echo "✓ Added is_taxable column to products\n";
    } else {
        echo "✓ is_taxable column already exists in products\n";
    }

    echo "\n✅ Tax rate migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
