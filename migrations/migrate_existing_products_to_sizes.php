<?php
/**
 * Migration script to populate product_sizes table for existing products
 * 
 * This script will:
 * 1. Find all products that have sizes defined
 * 2. Create product_sizes entries for each size
 * 3. Distribute existing quantity evenly across sizes (or set to 0 if quantity is 0)
 * 
 * Run this script once after creating the product_sizes table
 */

require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../models/Product.php';

echo "Starting migration of existing products to size-based inventory...\n\n";

try {
    $products = Product::all();
    $migrated = 0;
    $skipped = 0;
    
    foreach ($products as $product) {
        if (empty($product->size)) {
            $skipped++;
            echo "Skipping product '{$product->name}' (no sizes defined)\n";
            continue;
        }
        
        // Check if size stocks already exist
        $existingSizes = $product->getSizeStocks();
        if (!empty($existingSizes)) {
            $skipped++;
            echo "Skipping product '{$product->name}' (size stocks already exist)\n";
            continue;
        }
        
        // Parse sizes
        $sizes = array_map('trim', explode(',', $product->size));
        $sizes = array_filter($sizes); // Remove empty values
        
        if (empty($sizes)) {
            $skipped++;
            echo "Skipping product '{$product->name}' (no valid sizes found)\n";
            continue;
        }
        
        // Distribute quantity evenly across sizes
        $qtyPerSize = $product->quantity > 0 ? floor($product->quantity / count($sizes)) : 0;
        $remainder = $product->quantity > 0 ? $product->quantity % count($sizes) : 0;
        
        foreach ($sizes as $index => $size) {
            $qty = $qtyPerSize;
            // Add remainder to first size
            if ($index === 0 && $remainder > 0) {
                $qty += $remainder;
            }
            
            $product->addSizeStock($size, $qty);
        }
        
        $migrated++;
        echo "Migrated product '{$product->name}': " . count($sizes) . " sizes, total qty: {$product->quantity}\n";
    }
    
    echo "\nMigration complete!\n";
    echo "Migrated: {$migrated} products\n";
    echo "Skipped: {$skipped} products\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}

