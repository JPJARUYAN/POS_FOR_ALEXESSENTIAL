<?php

require_once __DIR__ . '/../_init.php';

class Order
{
    public $id;
    public $created_at;
    public $customer_id;
    public $cashier_id;
    public $payment_method;
    public $payment_amount;
    public $change_amount;

    public function __construct($order)
    {
        $this->id = $order['id'];
        $this->created_at = $order['created_at'];
        $this->customer_id = $order['customer_id'] ?? null;
        $this->cashier_id = $order['cashier_id'] ?? null;
        $this->payment_method = $order['payment_method'] ?? 'cash';
        $this->payment_amount = $order['payment_amount'] ?? null;
        $this->change_amount = $order['change_amount'] ?? null;
    }

    public static function create($customer_id = null, $cashier_id = null, $payment_method = 'cash', $payment_amount = null, $change_amount = null)
    {
        global $connection;

        $sql_command = 'INSERT INTO orders (customer_id, cashier_id, payment_method, payment_amount, change_amount) 
                        VALUES (:customer_id, :cashier_id, :payment_method, :payment_amount, :change_amount)';
        $stmt = $connection->prepare($sql_command);
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':cashier_id' => $cashier_id,
            ':payment_method' => $payment_method,
            ':payment_amount' => $payment_amount,
            ':change_amount' => $change_amount
        ]);

        return static::getLastRecord();
    }


    public static function getLastRecord()
    {
        global $connection;

        $stmt = $connection->prepare('SELECT * FROM `orders` ORDER BY id DESC limit 1;');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return new Order($result[0]);
        }

        return null;
    }
}