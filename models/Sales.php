<?php

require_once __DIR__.'/../_init.php';

class Sales
{
    public static function getTodaySales()
    {
        global $connection;

        $sql_command = ("
            SELECT 
                SUM(order_items.quantity*order_items.price) as today,
                DATE_FORMAT(orders.created_at, '%Y-%m-%d') as _date
            FROM 
                `order_items` 
            INNER JOIN 
                orders on order_items.order_id = orders.id 
            WHERE Date(created_at)=Curdate()
            GROUP BY 
                _date;
        ");

        $stmt = $connection->prepare($sql_command);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return $result[0]['today'];
        }

        return 0;
    }

    
    public static function getTotalSales()
    {
        global $connection;

        $sql_command = "SELECT SUM(quantity*price) as total FROM order_items";

        $stmt = $connection->prepare($sql_command);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return $result[0]['total'];
        }

        return 0;
    }

    /**
     * Get total sales for a given date range (inclusive) based on order created_at.
     */
    public static function getSalesByDateRange($start, $end)
    {
        global $connection;

        $sql = "SELECT SUM(order_items.quantity * order_items.price) as total
                FROM order_items
                INNER JOIN orders on order_items.order_id = orders.id
                WHERE DATE(orders.created_at) BETWEEN :start AND :end";

        $stmt = $connection->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();

        if (count($result) >= 1 && $result[0]['total'] !== null) {
            return floatval($result[0]['total']);
        }

        return 0;
    }

    public static function getTotalExpenses($start = null, $end = null)
    {
        global $connection;

        // Calculate total expenses from cost of sold products
        if ($start && $end) {
            // With date filtering
            $sql_command = "SELECT SUM(order_items.quantity * products.cost) as total 
                            FROM order_items 
                            INNER JOIN products ON order_items.product_id = products.id
                            INNER JOIN orders ON order_items.order_id = orders.id
                            WHERE DATE(orders.created_at) BETWEEN :start AND :end";
            $stmt = $connection->prepare($sql_command);
            $stmt->execute([':start' => $start, ':end' => $end]);
        } else {
            // All time (no date filter)
            $sql_command = "SELECT SUM(order_items.quantity * products.cost) as total 
                            FROM order_items 
                            INNER JOIN products ON order_items.product_id = products.id";
            $stmt = $connection->prepare($sql_command);
            $stmt->execute();
        }

        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();

        if (count($result) >= 1 && $result[0]['total'] !== null) {
            return floatval($result[0]['total']);
        }

        return 0;
    }

    public static function getMonthlySales()
    {
        global $connection;

        $sql = "SELECT SUM(order_items.quantity*order_items.price) as month_total
                FROM order_items
                INNER JOIN orders on order_items.order_id = orders.id
                WHERE MONTH(orders.created_at) = MONTH(CURDATE())
                AND YEAR(orders.created_at) = YEAR(CURDATE())";

        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();

        if (count($result) >= 1 && $result[0]['month_total'] !== null) {
            return $result[0]['month_total'];
        }

        return 0;
    }

    public static function getYearlySales()
    {
        global $connection;

        $sql = "SELECT SUM(order_items.quantity*order_items.price) as year_total
                FROM order_items
                INNER JOIN orders on order_items.order_id = orders.id
                WHERE YEAR(orders.created_at) = YEAR(CURDATE())";

        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();

        if (count($result) >= 1 && $result[0]['year_total'] !== null) {
            return $result[0]['year_total'];
        }

        return 0;
    }

    /**
     * Returns an array of totals for the last 12 months (including current month).
     * The array keys are in 'YYYY-MM' format and values are numeric totals.
     */
    public static function getMonthlyTotalsLastYear()
    {
        global $connection;

        $sql = "SELECT DATE_FORMAT(orders.created_at, '%Y-%m') as ym, SUM(order_items.quantity*order_items.price) as total
                FROM order_items
                INNER JOIN orders on order_items.order_id = orders.id
                WHERE orders.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                GROUP BY ym
                ORDER BY ym";

        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $rows = $stmt->fetchAll();

        // Initialize months array for the last 12 months
        $months = [];
        $dt = new DateTime();
        // Start from 11 months ago up to current month
        $dt->modify('-11 months');
        for ($i = 0; $i < 12; $i++) {
            $key = $dt->format('Y-m');
            $months[$key] = 0;
            $dt->modify('+1 month');
        }

        // Fill totals from query results
        foreach ($rows as $r) {
            if (isset($months[$r['ym']])) {
                $months[$r['ym']] = $r['total'] !== null ? $r['total'] : 0;
            }
        }

        return $months;
    }

}