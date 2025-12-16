<?php
require_once __DIR__ . '/../_init.php';

class Product
{
    public $id;
    public $name;
    public $category_id;
    public $quantity;
    public $price;
    public $cost;
    public $size;
    public $sku;
    public $barcode;
    public $image;
    public $created_at;
    public $updated_at;
    public $supplier_id;
    public $tax_rate;
    public $is_taxable;

    public function __construct($product)
    {
        $this->id = $product['id'];
        $this->name = $product['name'];
        $this->category_id = $product['category_id'];
        $this->quantity = $product['quantity'];
        $this->price = $product['price'];
        $this->cost = $product['cost'] ?? 0;
        $this->size = $product['size'] ?? '';
        $this->sku = $product['sku'] ?? null;
        $this->barcode = $product['barcode'] ?? null;
        $this->image = $product['image'] ?? null;
        $this->created_at = $product['created_at'] ?? null;
        $this->updated_at = $product['updated_at'] ?? null;
        $this->supplier_id = $product['supplier_id'] ?? null;
        $this->tax_rate = $product['tax_rate'] ?? null;
        $this->is_taxable = $product['is_taxable'] ?? 1;
    }

    public static function all()
    {
        global $connection;
        $stmt = $connection->prepare("SELECT * FROM products ORDER BY name");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($p) {
            return new Product($p);
        }, $products);
    }

    public static function find($id)
    {
        global $connection;
        $stmt = $connection->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ? new Product($product) : null;
    }

    public static function add($name, $category_id, $quantity, $price, $cost, $size, $sku, $image, $supplier_id = null)
    {
        global $connection;
        $sql = "INSERT INTO products (name, category_id, quantity, price, cost, size, sku, image, supplier_id) 
                VALUES (:name, :category_id, :quantity, :price, :cost, :size, :sku, :image, :supplier_id)";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':cost', $cost);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':sku', $sku);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':supplier_id', $supplier_id);
        $stmt->execute();
        
        return $connection->lastInsertId();
    }

    public function update()
    {
        global $connection;
        $sql = "UPDATE products SET 
                name = :name, 
                category_id = :category_id, 
                quantity = :quantity, 
                price = :price, 
                cost = :cost, 
                size = :size, 
                sku = :sku, 
                image = :image
                , supplier_id = :supplier_id
                WHERE id = :id";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':quantity', $this->quantity);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':cost', $this->cost);
        $stmt->bindParam(':size', $this->size);
        $stmt->bindParam(':sku', $this->sku);
        $stmt->bindParam(':image', $this->image);
        $stmt->bindParam(':supplier_id', $this->supplier_id);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    public function delete()
    {
        global $connection;
        $stmt = $connection->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    public function __get($prop)
    {
        if ($prop === 'category') {
            return Category::find($this->category_id);
        }
        return null;
    }

    /**
     * Get stock quantities per size for this product
     * @return array Array of ['size' => 'S', 'quantity' => 2] format
     */
    public function getSizeStocks()
    {
        global $connection;
        
        // Ensure table exists
        self::ensureProductSizesTable();
        
        try {
            $stmt = $connection->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = :product_id ORDER BY size");
            $stmt->bindParam(':product_id', $this->id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table doesn't exist or error, return empty array
            return [];
        }
    }

    /**
     * Get formatted size stock display (e.g., "S: 2, M: 2, L: 2, XL: 2, XXL: 2")
     * @return string Formatted string showing stock per size
     */
    public function getFormattedSizeStocks()
    {
        $sizeStocks = $this->getSizeStocks();
        if (empty($sizeStocks)) {
            // If no size-specific stocks, return the size list or total quantity
            if (!empty($this->size)) {
                return htmlspecialchars($this->size, ENT_QUOTES, 'UTF-8');
            }
            return '-';
        }

        // Sort sizes intelligently
        usort($sizeStocks, function($a, $b) {
            $sizeA = trim($a['size']);
            $sizeB = trim($b['size']);
            
            // Check if both are numeric
            $isNumericA = is_numeric($sizeA);
            $isNumericB = is_numeric($sizeB);
            
            if ($isNumericA && $isNumericB) {
                // Both numeric - sort numerically
                return (int)$sizeA - (int)$sizeB;
            } elseif ($isNumericA) {
                // A is numeric, B is not - numeric comes first
                return -1;
            } elseif ($isNumericB) {
                // B is numeric, A is not - numeric comes first
                return 1;
            } else {
                // Both are alphabetical - define standard order
                $sizeOrder = [
                    'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 
                    'XL' => 5, 'XXL' => 6, 'XXXL' => 7,
                    'XXS' => 0.5
                ];
                
                $orderA = $sizeOrder[strtoupper($sizeA)] ?? 999;
                $orderB = $sizeOrder[strtoupper($sizeB)] ?? 999;
                
                if ($orderA !== 999 || $orderB !== 999) {
                    // At least one is a standard size
                    return $orderA <=> $orderB;
                } else {
                    // Both are non-standard - sort alphabetically
                    return strcasecmp($sizeA, $sizeB);
                }
            }
        });

        // Format the sorted sizes
        $formatted = [];
        foreach ($sizeStocks as $item) {
            $formatted[] = $item['size'] . ': ' . $item['quantity'];
        }
        return implode(', ', $formatted);
    }

    /**
     * Ensure product_sizes table exists, create if it doesn't
     */
    public static function ensureProductSizesTable()
    {
        global $connection;
        
        // Check if table exists
        $stmt = $connection->query("SHOW TABLES LIKE 'product_sizes'");
        if ($stmt->rowCount() > 0) {
            return; // Table exists
        }
        
        // Create the table
        $sql = "CREATE TABLE IF NOT EXISTS `product_sizes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) NOT NULL,
          `size` varchar(50) NOT NULL,
          `quantity` int(11) NOT NULL DEFAULT 0,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_product_size` (`product_id`, `size`),
          KEY `idx_product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
        
        try {
            $connection->exec($sql);
        } catch (PDOException $e) {
            // If foreign key constraint fails, try without it
            $sql = "CREATE TABLE IF NOT EXISTS `product_sizes` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `product_id` int(11) NOT NULL,
              `size` varchar(50) NOT NULL,
              `quantity` int(11) NOT NULL DEFAULT 0,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_product_size` (`product_id`, `size`),
              KEY `idx_product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
            $connection->exec($sql);
        }
    }

    /**
     * Add stock for a specific size
     * @param string $size The size (e.g., 'S', 'M', 'L')
     * @param int $quantity The quantity to add
     */
    public function addSizeStock($size, $quantity)
    {
        global $connection;
        
        // Ensure table exists
        self::ensureProductSizesTable();
        
        // Check if size entry exists
        $stmt = $connection->prepare("SELECT id, quantity FROM product_sizes WHERE product_id = :product_id AND size = :size");
        $stmt->bindParam(':product_id', $this->id);
        $stmt->bindParam(':size', $size);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $newQuantity = $existing['quantity'] + $quantity;
            $stmt = $connection->prepare("UPDATE product_sizes SET quantity = :quantity WHERE id = :id");
            $stmt->bindParam(':quantity', $newQuantity);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();
        } else {
            // Insert new - let database handle timestamps with defaults
            $stmt = $connection->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (:product_id, :size, :quantity)");
            $stmt->bindParam(':product_id', $this->id);
            $stmt->bindParam(':size', $size);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->execute();
        }

        // Update total quantity in products table
        $this->recalculateTotalQuantity();
    }

    /**
     * Recalculate total quantity from size stocks
     */
    public function recalculateTotalQuantity()
    {
        global $connection;
        
        // Ensure table exists
        self::ensureProductSizesTable();
        
        try {
            $stmt = $connection->prepare("SELECT SUM(quantity) as total FROM product_sizes WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $this->id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = $result['total'] ?? 0;

            $this->quantity = $total;
            $stmt = $connection->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
            $stmt->bindParam(':quantity', $this->quantity);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
        } catch (PDOException $e) {
            // If table doesn't exist, keep current quantity
            // Error is logged but doesn't break the flow
        }
    }

    /**
     * Initialize size stocks when product is created
     * This should be called after product creation to set up initial size stocks
     */
    public function initializeSizeStocks($initialQuantity = 0)
    {
        if (empty($this->size)) {
            return;
        }

        $sizes = array_map('trim', explode(',', $this->size));
        foreach ($sizes as $size) {
            if (!empty($size)) {
                // Distribute initial quantity evenly, or set to 0 if not specified
                $qtyPerSize = $initialQuantity > 0 ? floor($initialQuantity / count($sizes)) : 0;
                $this->addSizeStock($size, $qtyPerSize);
            }
        }
    }

    /**
     * Get the effective tax rate for this product
     * Returns product-specific tax rate if set, otherwise returns category tax rate
     */
    public function getEffectiveTaxRate()
    {
        // If product has specific tax rate, use it
        if ($this->tax_rate !== null) {
            return floatval($this->tax_rate);
        }

        // Otherwise use category tax rate
        if ($this->category_id) {
            $category = Category::find($this->category_id);
            if ($category) {
                return floatval($category->tax_rate ?? 12.00);
            }
        }

        // Default fallback
        return 12.00;
    }

    /**
     * Check if this product is taxable
     */
    public function isTaxable()
    {
        return (bool) $this->is_taxable;
    }}