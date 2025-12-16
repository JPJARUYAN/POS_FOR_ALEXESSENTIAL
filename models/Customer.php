<?php

require_once __DIR__.'/../_init.php';

class Customer
{
    public $id;
    public $name;
    public $email;
    public $phone;
    public $first_purchase_at;
    public $total_purchases;

    public function __construct($data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->first_purchase_at = $data['first_purchase_at'] ?? null;
        $this->total_purchases = $data['total_purchases'] ?? 1;
    }

    /**
     * Find or create a customer by email/phone/name
     * Returns customer ID
     */
    public static function findOrCreate($name = null, $email = null, $phone = null)
    {
        global $connection;

        // Try to find by email first
        if ($email) {
            $stmt = $connection->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // Update purchase count
                $stmt = $connection->prepare('UPDATE customers SET total_purchases = total_purchases + 1 WHERE id = :id');
                $stmt->execute([':id' => $result['id']]);
                return $result['id'];
            }
        }

        // Try to find by phone
        if ($phone) {
            $stmt = $connection->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
            $stmt->execute([':phone' => $phone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $stmt = $connection->prepare('UPDATE customers SET total_purchases = total_purchases + 1 WHERE id = :id');
                $stmt->execute([':id' => $result['id']]);
                return $result['id'];
            }
        }

        // If no identifying info provided (cashier flow), create a customer and then update their name to the numeric id.
        $placeholderName = $name ?? 'Customer';
        $stmt = $connection->prepare('INSERT INTO customers (name, email, phone) VALUES (:name, :email, :phone)');
        $stmt->execute([
            ':name' => $placeholderName,
            ':email' => $email,
            ':phone' => $phone
        ]);

        $newId = $connection->lastInsertId();

        // If no name/email/phone were provided, set the name to the numeric id (1,2,3...)
        if (empty($name) && empty($email) && empty($phone)) {
            $generatedName = (string) intval($newId);
            $u = $connection->prepare('UPDATE customers SET name = :name WHERE id = :id');
            $u->execute([':name' => $generatedName, ':id' => $newId]);
        }

        return $newId;
    }

    /**
     * Get total count of unique customers
     */
    public static function getTotalCustomers()
    {
        global $connection;
        $stmt = $connection->prepare('SELECT COUNT(*) as total FROM customers');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total'] ?? 0);
    }

    /**
     * Get customers who made purchases in a date range
     */
    public static function getCustomersByDateRange($start, $end)
    {
        global $connection;
        $stmt = $connection->prepare('SELECT COUNT(DISTINCT customer_id) as total FROM orders WHERE customer_id IS NOT NULL AND DATE(created_at) BETWEEN :start AND :end');
        $stmt->execute([':start' => $start, ':end' => $end]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total'] ?? 0);
    }

    /**
     * Get new customers in a date range
     */
    public static function getNewCustomersByDateRange($start, $end)
    {
        global $connection;
        $stmt = $connection->prepare('SELECT COUNT(*) as total FROM customers WHERE DATE(first_purchase_at) BETWEEN :start AND :end');
        $stmt->execute([':start' => $start, ':end' => $end]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total'] ?? 0);
    }
}

?>
