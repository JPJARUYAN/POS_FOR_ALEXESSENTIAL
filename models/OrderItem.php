<?php 

require_once __DIR__.'/../_init.php';

class OrderItem 
{
    public $id;
    public $order_id;
    public $product_id;
    public $quantity;
    public $price;
    public $size;
    public $product_name;

    public function __construct($data)
    {
        $this->id = $data['id'];
        $this->order_id = $data['order_id'];
        $this->product_id = $data['product_id'];
        $this->quantity = $data['quantity'];
        $this->price = $data['price'];
        $this->size = $data['size'] ?? null;
        $this->product_name = $data['product_name'];
    }

    public static function add($orderId, $item)
    {
        global $connection;
        $product = Product::find($item['id']);

        if (!$product) {
            throw new Exception('Product not found for id: ' . ($item['id'] ?? 'unknown'));
        }

        $requestedQty = intval($item['quantity']);
        if ($requestedQty < 1) {
            throw new Exception('Invalid quantity for product: ' . $product->name);
        }

        $hasSize = isset($item['size']) && $item['size'] !== '';
        $selectedSize = $hasSize ? trim($item['size']) : '';

        // If product has size-specific stock, check and deduct from that
        if ($hasSize && !empty($selectedSize)) {
            // Ensure product_sizes table exists
            Product::ensureProductSizesTable();
            
            // Check size-specific stock
            $stmt = $connection->prepare("SELECT quantity FROM product_sizes WHERE product_id = :product_id AND size = :size");
            $stmt->bindParam(':product_id', $product->id);
            $stmt->bindParam(':size', $selectedSize);
            $stmt->execute();
            $sizeStock = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sizeStock) {
                $availableStock = intval($sizeStock['quantity']);
                if ($availableStock < $requestedQty) {
                    throw new Exception('Insufficient stock for size ' . $selectedSize . ' of product: ' . $product->name . ' (Available: ' . $availableStock . ', Requested: ' . $requestedQty . ')');
                }

                // Deduct from size-specific stock
                $newQty = $availableStock - $requestedQty;
                $stmt = $connection->prepare("UPDATE product_sizes SET quantity = :quantity WHERE product_id = :product_id AND size = :size");
                $stmt->bindParam(':quantity', $newQty);
                $stmt->bindParam(':product_id', $product->id);
                $stmt->bindParam(':size', $selectedSize);
                $stmt->execute();

                // Recalculate total quantity
                $product->recalculateTotalQuantity();
            } else {
                // Size entry doesn't exist, check total stock as fallback
                if ($product->quantity < $requestedQty) {
                    throw new Exception('Insufficient stock for product: ' . $product->name);
                }
                $product->quantity -= $requestedQty;
                $product->update();
            }
        } else {
            // No size specified, use total stock
            if ($product->quantity < $requestedQty) {
                throw new Exception('Insufficient stock for product: ' . $product->name);
            }
            $product->quantity -= $requestedQty;
            $product->update();
        }

        // support optional size column in order_items
        if ($hasSize) {
            $stmt = $connection->prepare('INSERT INTO `order_items`(order_id, product_id, quantity, price, size) VALUES (:order_id, :product_id, :quantity, :price, :size)');
            $stmt->bindParam(':size', $selectedSize);
        } else {
            $stmt = $connection->prepare('INSERT INTO `order_items`(order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)');
        }
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':product_id', $item['id']);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':price', $product->price);

        $stmt->execute();
    }

    public static function all()
    {
        global $connection;

        $stmt = $connection->prepare('
            SELECT 
                order_items.*, 
                products.name as product_name
            FROM order_items
            INNER JOIN products
            ON order_items.product_id = products.id
        ');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        $result = array_map(fn($item) => new OrderItem($item), $result);

        return $result;

    }
}