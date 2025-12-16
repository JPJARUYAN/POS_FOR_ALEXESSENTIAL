<?php

require_once __DIR__ . '/../_init.php';

class Supplier
{
    public $id;
    public $name;
    public $contact_person;
    public $phone;
    public $email;
    public $address;

    public function __construct($s)
    {
        $this->id = $s['id'];
        $this->name = $s['name'];
        $this->contact_person = $s['contact_person'] ?? null;
        $this->phone = $s['phone'] ?? null;
        $this->email = $s['email'] ?? null;
        $this->address = $s['address'] ?? null;
    }

    public static function all()
    {
        global $connection;
        // Ensure table exists to avoid runtime exceptions when migration wasn't applied
        self::ensureSuppliersTable();

        try {
            $stmt = $connection->prepare('SELECT * FROM suppliers ORDER BY name');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map(function($r){ return new Supplier($r); }, $rows);
        } catch (PDOException $e) {
            // If the table still doesn't exist or there is a DB error, return empty list
            return [];
        }
    }

    public static function find($id)
    {
        global $connection;
        self::ensureSuppliersTable();
        try {
            $stmt = $connection->prepare('SELECT * FROM suppliers WHERE id=:id');
            $stmt->bindParam('id', $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? new Supplier($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function findByName($name)
    {
        global $connection;
        try {
            $stmt = $connection->prepare('SELECT * FROM suppliers WHERE name=:name');
            $stmt->bindParam('name', $name);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? new Supplier($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function add($name, $contact_person = null, $phone = null, $email = null, $address = null)
    {
        global $connection;
        self::ensureSuppliersTable();
        try {
            // Check uniqueness by name
            $stmt = $connection->prepare('SELECT id FROM suppliers WHERE name=:name');
            $stmt->bindParam('name', $name);
            $stmt->execute();
            if ($stmt->fetch()) throw new Exception('Supplier with that name already exists');

            $stmt = $connection->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (:name, :contact_person, :phone, :email, :address)');
            $stmt->bindParam('name', $name);
            $stmt->bindParam('contact_person', $contact_person);
            $stmt->bindParam('phone', $phone);
            $stmt->bindParam('email', $email);
            $stmt->bindParam('address', $address);
            $stmt->execute();
            return $connection->lastInsertId();
        } catch (PDOException $e) {
            // Log the actual error for debugging
            error_log('Supplier::add PDOException: ' . $e->getMessage());
            throw new Exception('Database error: ' . $e->getMessage());
        } catch (Exception $e) {
            // Re-throw non-PDO exceptions
            throw $e;
        }
    }

    public function update()
    {
        global $connection;
        $stmt = $connection->prepare('UPDATE suppliers SET name=:name, contact_person=:contact_person, phone=:phone, email=:email, address=:address WHERE id=:id');
        $stmt->bindParam('name', $this->name);
        $stmt->bindParam('contact_person', $this->contact_person);
        $stmt->bindParam('phone', $this->phone);
        $stmt->bindParam('email', $this->email);
        $stmt->bindParam('address', $this->address);
        $stmt->bindParam('id', $this->id);
        $stmt->execute();
    }

    public function delete()
    {
        global $connection;
        $stmt = $connection->prepare('DELETE FROM suppliers WHERE id=:id');
        $stmt->bindParam('id', $this->id);
        $stmt->execute();
    }

    /**
     * Create suppliers table if it does not exist. This prevents runtime errors
     * when the migration hasn't been run yet (useful for development).
     */
    public static function ensureSuppliersTable()
    {
        global $connection;
        try {
            // First check if table exists
            $stmt = $connection->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' LIMIT 1");
            $stmt->execute();
            if ($stmt->fetch()) {
                return; // Table already exists
            }

            // Table doesn't exist, create it
            $sql = "CREATE TABLE `suppliers` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(150) NOT NULL,
              `contact_person` varchar(150) DEFAULT NULL,
              `phone` varchar(50) DEFAULT NULL,
              `email` varchar(150) DEFAULT NULL,
              `address` text DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_suppliers_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            $connection->exec($sql);
            error_log('✓ Suppliers table created successfully');
            
        } catch (PDOException $e) {
            error_log('✗ Error in ensureSuppliersTable: ' . $e->getMessage());
            // Don't throw - let the calling code handle the error
        }
    }
}
