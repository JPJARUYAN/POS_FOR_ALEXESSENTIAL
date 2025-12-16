<?php
// Test the expenses API directly
require_once '_init.php';

// Simulate admin session
$_SESSION['user_id_admin'] = 1;

echo "Testing Expenses Report API\n";
echo "============================\n\n";

// Test data
$testData = json_encode([
    'start_date' => '2025-12-01',
    'end_date' => '2025-12-15',
    'category_id' => null
]);

echo "Sending request to api/expenses_report.php\n";
echo "Data: " . $testData . "\n\n";

// Simulate the request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Mock the file_get_contents
global $connection;

header('Content-Type: application/json');

$data = json_decode($testData, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$categoryId = !empty($data['category_id']) ? $data['category_id'] : null;

if (!$startDate || !$endDate) {
    echo json_encode(['error' => 'Start date and end date are required']);
    exit;
}

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
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
