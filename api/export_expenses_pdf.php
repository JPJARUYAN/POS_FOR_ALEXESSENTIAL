<?php
require_once '../_init.php';

// Only allow admin access
if (!isset($_SESSION['user_id_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$categoryId = !empty($data['category_id']) ? $data['category_id'] : null;

if (!$startDate || !$endDate) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date range']);
    exit;
}

global $connection;

try {
    // Build query for metrics
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
    $stmt->execute($params);
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
        ORDER BY profit DESC
    ";
    
    $stmt = $connection->prepare($productQuery);
    $stmt->execute($productParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build HTML content FIRST (no output yet)
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense & Cost Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .metric-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .metric-box:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .metric-box:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .metric-box:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .metric-box:nth-child(5) {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .metric-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            word-break: break-word;
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        thead {
            background-color: #2c3e50;
            color: white;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tbody tr:hover {
            background-color: #f0f0f0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            color: #999;
            font-size: 12px;
        }
        
        @media print {
            body {
                background: white;
            }
            .container {
                box-shadow: none;
            }
            table {
                box-shadow: none;
            }
            tbody tr:hover {
                background-color: transparent;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Expense & Cost Report</h1>
            <p>Period: ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . '</p>
            <p>Generated: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>
        
        <div class="metrics-container">
            <div class="metric-box">
                <div class="metric-label">Total Orders</div>
                <div class="metric-value">' . intval($metrics['total_orders']) . '</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Items Sold</div>
                <div class="metric-value">' . intval($metrics['total_items_sold']) . '</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Revenue</div>
                <div class="metric-value">₱' . number_format($totalRevenue, 2) . '</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Expenses</div>
                <div class="metric-value">₱' . number_format($totalExpenses, 2) . '</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Profit</div>
                <div class="metric-value">₱' . number_format($totalProfit, 2) . '</div>
            </div>';
    
    if ($totalRevenue > 0) {
        $profitMargin = ($totalProfit / $totalRevenue * 100);
        $html .= '<div class="metric-box">
                    <div class="metric-label">Profit Margin</div>
                    <div class="metric-value">' . number_format($profitMargin, 2) . '%</div>
                </div>';
    }
    
    $html .= '</div>';
    
    if (!empty($products)) {
        $html .= '<div class="section-title">Product Performance</div>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Qty Sold</th>
                    <th class="text-right">Total Cost</th>
                    <th class="text-right">Total Revenue</th>
                    <th class="text-right">Total Profit</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($products as $product) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($product['product_name']) . '</td>
                        <td>' . htmlspecialchars($product['category_name']) . '</td>
                        <td class="text-right">₱' . number_format($product['cost'], 2) . '</td>
                        <td class="text-right">₱' . number_format($product['price'], 2) . '</td>
                        <td class="text-right">' . intval($product['units_sold']) . '</td>
                        <td class="text-right">₱' . number_format($product['total_cost'], 2) . '</td>
                        <td class="text-right">₱' . number_format($product['total_revenue'], 2) . '</td>
                        <td class="text-right"><strong>₱' . number_format($product['profit'], 2) . '</strong></td>
                    </tr>';
        }
        
        $html .= '</tbody>
        </table>';
    }
    
    $html .= '<div class="footer">
        <p>This is an automatically generated report. Please verify data before making business decisions.</p>
        <p>💡 Tip: Use Ctrl+P (or Cmd+P on Mac) to print and save as PDF</p>
    </div>
    </div>
</body>
</html>';
    
    // NOW set headers - AFTER building HTML, BEFORE outputting
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="expense_report_' . date('Y-m-d_H-i-s') . '.html"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the complete HTML
    echo $html;
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
