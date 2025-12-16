<?php
require_once '../_init.php';

header('Content-Type: application/json');

// Only allow admin access
if (!isset($_SESSION['user_id_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$categoryId = !empty($data['category_id']) ? $data['category_id'] : null;

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Start date and end date are required']);
    exit;
}

global $connection;

try {
    // Build query for total metrics
    $metricsQuery = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(oi.quantity) as total_items_sold,
            SUM(oi.quantity * oi.price) as total_revenue,
            SUM(oi.quantity * p.cost) as total_expenses
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
    ";
    
    $params = [$startDate, $endDate];
    
    if ($categoryId) {
        $metricsQuery .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
    
    $stmt = $connection->prepare($metricsQuery);
    if (!$stmt->execute($params)) {
        throw new Exception('Failed to execute metrics query: ' . implode(' | ', $stmt->errorInfo()));
    }
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$metrics) {
        $metrics = [
            'total_orders' => 0,
            'total_items_sold' => 0,
            'total_revenue' => 0,
            'total_expenses' => 0
        ];
    }
    
    $totalRevenue = floatval($metrics['total_revenue'] ?? 0);
    $totalExpenses = floatval($metrics['total_expenses'] ?? 0);
    $totalProfit = $totalRevenue - $totalExpenses;
    
    // Build query for product breakdown
    $productQuery = "
        SELECT 
            p.id,
            p.name as product_name,
            c.name as category_name,
            p.cost,
            p.price,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * p.cost) as total_cost,
            SUM(oi.quantity * oi.price) as total_revenue,
            SUM(oi.quantity * oi.price) - SUM(oi.quantity * p.cost) as profit
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
    ";
    
    $productParams = [$startDate, $endDate];
    
    if ($categoryId) {
        $productQuery .= " AND p.category_id = ?";
        $productParams[] = $categoryId;
    }
    
    $productQuery .= "
        GROUP BY p.id, p.name, c.name, p.cost, p.price
        ORDER BY total_revenue DESC
    ";
    
    $stmt = $connection->prepare($productQuery);
    if (!$stmt->execute($productParams)) {
        throw new Exception('Failed to execute product query: ' . implode(' | ', $stmt->errorInfo()));
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$products) {
        $products = [];
    }
    
    // Format the response
    $response = [
        'total_orders' => intval($metrics['total_orders'] ?? 0),
        'total_items_sold' => intval($metrics['total_items_sold'] ?? 0),
        'total_revenue' => $totalRevenue,
        'total_expenses' => $totalExpenses,
        'total_profit' => $totalProfit,
        'products' => array_map(function($product) {
            return [
                'product_name' => $product['product_name'],
                'category_name' => $product['category_name'],
                'cost' => floatval($product['cost'] ?? 0),
                'price' => floatval($product['price'] ?? 0),
                'units_sold' => intval($product['units_sold']),
                'total_cost' => floatval($product['total_cost'] ?? 0),
                'total_revenue' => floatval($product['total_revenue'] ?? 0),
                'profit' => floatval($product['profit'] ?? 0)
            ];
        }, $products)
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
