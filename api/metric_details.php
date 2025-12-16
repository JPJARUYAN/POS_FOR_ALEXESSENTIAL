<?php
/**
 * API endpoint to fetch detailed metric data
 */
require_once '../_guards.php';
Guard::adminOnly();

require_once '../models/Sales.php';
require_once '../models/Product.php';
require_once '../models/Category.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

global $connection;

$response = ['success' => false, 'title' => '', 'html' => ''];

switch ($type) {
    case 'sales':
        $response['title'] = 'Total Sales Breakdown';

        // Get sales by category
        $stmt = $connection->prepare("
            SELECT c.name as category, 
                   SUM(oi.quantity * oi.price) as total,
                   COUNT(DISTINCT o.id) as orders
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            JOIN orders o ON oi.order_id = o.id
            GROUP BY c.id, c.name
            ORDER BY total DESC
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get top selling products
        $stmt = $connection->prepare("
            SELECT p.name, SUM(oi.quantity) as qty_sold, SUM(oi.quantity * oi.price) as revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSales = Sales::getTotalSales();

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Revenue</div><div class="detail-summary-value">₱' . number_format($totalSales, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Categories</div><div class="detail-summary-value">' . count($categories) . '</div></div>';
        $html .= '</div>';

        $html .= '<h4 style="margin-bottom: 12px; color: var(--text-body);">Sales by Category</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Category</th><th>Orders</th><th>Total Sales</th></tr></thead><tbody>';
        foreach ($categories as $cat) {
            $html .= '<tr><td>' . htmlspecialchars($cat['category']) . '</td><td>' . number_format($cat['orders']) . '</td><td>₱' . number_format($cat['total'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Top 10 Products</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead><tbody>';
        foreach ($topProducts as $prod) {
            $html .= '<tr><td>' . htmlspecialchars($prod['name']) . '</td><td>' . number_format($prod['qty_sold']) . '</td><td>₱' . number_format($prod['revenue'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'expenses':
        $response['title'] = 'Expenses Breakdown (Cost of Goods Sold)';

        // Get expenses by category
        $stmt = $connection->prepare("
            SELECT c.name as category, 
                   SUM(oi.quantity * p.cost) as total_cost,
                   SUM(oi.quantity) as qty_sold
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY total_cost DESC
        ");
        $stmt->execute();
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalExpenses = Sales::getTotalExpenses();

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total COGS</div><div class="detail-summary-value">₱' . number_format($totalExpenses, 2) . '</div></div>';
        $html .= '</div>';

        $html .= '<h4 style="margin-bottom: 12px; color: var(--text-body);">Cost by Category</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Category</th><th>Units Sold</th><th>Total Cost</th></tr></thead><tbody>';
        foreach ($expenses as $exp) {
            $html .= '<tr><td>' . htmlspecialchars($exp['category']) . '</td><td>' . number_format($exp['qty_sold']) . '</td><td>₱' . number_format($exp['total_cost'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'profit':
        $response['title'] = 'Profit Analysis';

        $totalSales = Sales::getTotalSales();
        $totalExpenses = Sales::getTotalExpenses();
        $profit = $totalSales - $totalExpenses;
        $margin = $totalSales > 0 ? ($profit / $totalSales) * 100 : 0;

        // Get profit by category
        $stmt = $connection->prepare("
            SELECT c.name as category, 
                   SUM(oi.quantity * oi.price) as revenue,
                   SUM(oi.quantity * p.cost) as cost,
                   SUM(oi.quantity * (oi.price - p.cost)) as profit
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY profit DESC
        ");
        $stmt->execute();
        $profits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Revenue</div><div class="detail-summary-value">₱' . number_format($totalSales, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Cost</div><div class="detail-summary-value">₱' . number_format($totalExpenses, 2) . '</div></div>';
        $profitClass = $profit >= 0 ? 'profit-positive' : 'profit-negative';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Net Profit</div><div class="detail-summary-value ' . $profitClass . '">₱' . number_format($profit, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Profit Margin</div><div class="detail-summary-value">' . number_format($margin, 1) . '%</div></div>';
        $html .= '</div>';

        $html .= '<h4 class="detail-section-title">Profit by Category</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Category</th><th>Revenue</th><th>Cost</th><th>Profit</th></tr></thead><tbody>';
        foreach ($profits as $p) {
            $profitClass = $p['profit'] >= 0 ? 'profit-positive' : 'profit-negative';
            $html .= '<tr><td>' . htmlspecialchars($p['category']) . '</td><td>₱' . number_format($p['revenue'], 2) . '</td><td>₱' . number_format($p['cost'], 2) . '</td><td class="' . $profitClass . '">₱' . number_format($p['profit'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'transactions':
        $response['title'] = 'Transaction Details';

        // Get recent orders
        $stmt = $connection->prepare("
            SELECT o.id, o.created_at, 
                   SUM(oi.quantity * oi.price) as total,
                   COUNT(oi.id) as items
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            GROUP BY o.id, o.created_at
            ORDER BY o.created_at DESC
            LIMIT 20
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare('SELECT COUNT(DISTINCT id) as total FROM orders');
        $stmt->execute();
        $totalOrders = intval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $connection->prepare('SELECT COUNT(*) as total FROM order_items');
        $stmt->execute();
        $totalItems = intval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Orders</div><div class="detail-summary-value">' . number_format($totalOrders) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Items Sold</div><div class="detail-summary-value">' . number_format($totalItems) . '</div></div>';
        $html .= '</div>';

        $html .= '<h4 style="margin-bottom: 12px; color: var(--text-body);">Recent Orders</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Order #</th><th>Items</th><th>Total</th><th>Date</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            $html .= '<tr><td>#' . $order['id'] . '</td><td>' . $order['items'] . '</td><td>₱' . number_format($order['total'], 2) . '</td><td>' . date('M j, Y g:i A', strtotime($order['created_at'])) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'lowstock':
        $response['title'] = 'Low Stock Items';

        $stmt = $connection->prepare("
            SELECT p.id, p.name, p.quantity, p.price, c.name as category
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.quantity < 10
            ORDER BY p.quantity ASC
        ");
        $stmt->execute();
        $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Items Need Restocking</div><div class="detail-summary-value warning-color">' . count($lowStock) . '</div></div>';
        $html .= '</div>';

        if (count($lowStock) > 0) {
            $html .= '<table class="detail-table"><thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Price</th></tr></thead><tbody>';
            foreach ($lowStock as $item) {
                $stockClass = $item['quantity'] <= 5 ? 'stock-critical' : 'stock-warning';
                $html .= '<tr><td>' . htmlspecialchars($item['name']) . '</td><td>' . htmlspecialchars($item['category']) . '</td><td class="' . $stockClass . '">' . $item['quantity'] . '</td><td>₱' . number_format($item['price'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p class="stock-good">All items are well stocked!</p>';
        }

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'today':
        $response['title'] = "Today's Sales Details";

        $stmt = $connection->prepare("
            SELECT o.id, o.created_at, 
                   SUM(oi.quantity * oi.price) as total,
                   COUNT(oi.id) as items
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) = CURDATE()
            GROUP BY o.id, o.created_at
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare("SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) = CURDATE()");
        $stmt->execute();
        $todayTotal = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Get top products sold today
        $stmt = $connection->prepare("
            SELECT p.name, 
                   SUM(oi.quantity) as qty_sold, 
                   SUM(oi.quantity * oi.price) as revenue,
                   oi.size
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) = CURDATE()
            GROUP BY p.id, p.name, oi.size
            ORDER BY revenue DESC
            LIMIT 20
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Today\'s Revenue</div><div class="detail-summary-value">₱' . number_format($todayTotal, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Orders Today</div><div class="detail-summary-value">' . count($orders) . '</div></div>';
        $html .= '</div>';

        if (count($topProducts) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Products Sold Today</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Product Name</th><th>Size</th><th>Qty Sold</th><th>Revenue</th></tr></thead><tbody>';
            foreach ($topProducts as $prod) {
                $sizeDisplay = !empty($prod['size']) ? htmlspecialchars($prod['size']) : '-';
                $html .= '<tr><td>' . htmlspecialchars($prod['name']) . '</td><td>' . $sizeDisplay . '</td><td>' . number_format($prod['qty_sold']) . '</td><td>₱' . number_format($prod['revenue'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if (count($orders) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Orders Today</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Order #</th><th>Items</th><th>Total</th><th>Time</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                $html .= '<tr><td>#' . $order['id'] . '</td><td>' . $order['items'] . '</td><td>₱' . number_format($order['total'], 2) . '</td><td>' . date('g:i A', strtotime($order['created_at'])) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p style="color: var(--text-muted); text-align: center; padding: 20px;">No sales today yet.</p>';
        }

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'week':
        $response['title'] = "This Week's Sales Details";

        $stmt = $connection->prepare("
            SELECT DATE(o.created_at) as sale_date,
                   COUNT(DISTINCT o.id) as orders,
                   SUM(oi.quantity * oi.price) as total
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY DATE(o.created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute();
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare("SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)");
        $stmt->execute();
        $weekTotal = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Get top products sold this week
        $stmt = $connection->prepare("
            SELECT p.name, 
                   SUM(oi.quantity) as qty_sold, 
                   SUM(oi.quantity * oi.price) as revenue,
                   oi.size
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY p.id, p.name, oi.size
            ORDER BY revenue DESC
            LIMIT 20
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Week\'s Revenue</div><div class="detail-summary-value">₱' . number_format($weekTotal, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Active Days</div><div class="detail-summary-value">' . count($days) . '</div></div>';
        $html .= '</div>';

        if (count($topProducts) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Top Products This Week</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Product Name</th><th>Size</th><th>Qty Sold</th><th>Revenue</th></tr></thead><tbody>';
            foreach ($topProducts as $prod) {
                $sizeDisplay = !empty($prod['size']) ? htmlspecialchars($prod['size']) : '-';
                $html .= '<tr><td>' . htmlspecialchars($prod['name']) . '</td><td>' . $sizeDisplay . '</td><td>' . number_format($prod['qty_sold']) . '</td><td>₱' . number_format($prod['revenue'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if (count($days) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Sales by Day</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Date</th><th>Orders</th><th>Sales</th></tr></thead><tbody>';
            foreach ($days as $day) {
                $html .= '<tr><td>' . date('l, M j', strtotime($day['sale_date'])) . '</td><td>' . $day['orders'] . '</td><td>₱' . number_format($day['total'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p style="color: var(--text-muted); text-align: center; padding: 20px;">No sales this week yet.</p>';
        }

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'month':
        $response['title'] = "This Month's Sales Details";

        $stmt = $connection->prepare("
            SELECT DATE(o.created_at) as sale_date,
                   COUNT(DISTINCT o.id) as orders,
                   SUM(oi.quantity * oi.price) as total
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())
            GROUP BY DATE(o.created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute();
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare("SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())");
        $stmt->execute();
        $monthTotal = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Get top products sold this month
        $stmt = $connection->prepare("
            SELECT p.name, 
                   SUM(oi.quantity) as qty_sold, 
                   SUM(oi.quantity * oi.price) as revenue,
                   oi.size
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())
            GROUP BY p.id, p.name, oi.size
            ORDER BY revenue DESC
            LIMIT 20
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Month\'s Revenue</div><div class="detail-summary-value">₱' . number_format($monthTotal, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Active Days</div><div class="detail-summary-value">' . count($days) . '</div></div>';
        $html .= '</div>';

        if (count($topProducts) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Top Products This Month</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Product Name</th><th>Size</th><th>Qty Sold</th><th>Revenue</th></tr></thead><tbody>';
            foreach ($topProducts as $prod) {
                $sizeDisplay = !empty($prod['size']) ? htmlspecialchars($prod['size']) : '-';
                $html .= '<tr><td>' . htmlspecialchars($prod['name']) . '</td><td>' . $sizeDisplay . '</td><td>' . number_format($prod['qty_sold']) . '</td><td>₱' . number_format($prod['revenue'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if (count($days) > 0) {
            $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">Sales by Day</h4>';
            $html .= '<table class="detail-table"><thead><tr><th>Date</th><th>Orders</th><th>Sales</th></tr></thead><tbody>';
            foreach ($days as $day) {
                $html .= '<tr><td>' . date('M j, Y', strtotime($day['sale_date'])) . '</td><td>' . $day['orders'] . '</td><td>₱' . number_format($day['total'], 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p style="color: var(--text-muted); text-align: center; padding: 20px;">No sales this month yet.</p>';
        }

        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'total_products':
        $response['title'] = 'Product Inventory Overview';
        
        $stmt = $connection->prepare("
            SELECT c.name as category, COUNT(p.id) as product_count, SUM(p.quantity) as total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY product_count DESC
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<h4 style="margin-bottom: 12px; color: var(--text-body);">Products by Category</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Category</th><th>Products</th><th>Total Stock</th></tr></thead><tbody>';
        foreach ($categories as $cat) {
            $html .= '<tr><td>' . htmlspecialchars($cat['category']) . '</td><td>' . number_format($cat['product_count']) . '</td><td>' . number_format($cat['total_qty']) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        
        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'total_stock':
        $response['title'] = 'Stock Level Details by Size';
        
        $stmt = $connection->prepare("
            SELECT p.id, p.name, c.name as category, p.quantity, p.price, p.cost, p.size,
                   (p.quantity * p.price) as stock_value,
                   (p.quantity * p.cost) as stock_cost
            FROM products p
            JOIN categories c ON p.category_id = c.id
            ORDER BY p.quantity DESC, p.name ASC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalValue = 0;
        foreach ($products as $prod) {
            $totalValue += floatval($prod['stock_value']);
        }
        
        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Units</div><div class="detail-summary-value">' . number_format(array_sum(array_column($products, 'quantity'))) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Retail Value</div><div class="detail-summary-value">₱' . number_format($totalValue, 2) . '</div></div>';
        $html .= '</div>';
        
        $html .= '<h4 style="margin-bottom: 12px; color: var(--text-body);">Stock by Product & Size</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Product</th><th>Category</th><th>Total Qty</th><th>Retail Value</th><th>Stock by Size</th></tr></thead><tbody>';
        foreach ($products as $prod) {
            // Get size stock details
            $sizeStmt = $connection->prepare("
                SELECT size, quantity FROM product_sizes 
                WHERE product_id = :product_id 
                ORDER BY size ASC
            ");
            $sizeStmt->bindParam(':product_id', $prod['id']);
            $sizeStmt->execute();
            $sizeStocks = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build size stock display
            $sizeDisplay = '';
            if (count($sizeStocks) > 0) {
                $sizeDisplay = '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">';
                foreach ($sizeStocks as $sz) {
                    $stockPercentage = ($sz['quantity'] / $prod['quantity']) * 100;
                    $sizeColor = $sz['quantity'] == 0 ? '#ef4444' : ($sz['quantity'] < 10 ? '#f59e0b' : '#3b82f6');
                    $sizeDisplay .= '<span style="background: ' . $sizeColor . '20; border: 1.5px solid ' . $sizeColor . '; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">' . htmlspecialchars($sz['size']) . ': <strong>' . $sz['quantity'] . '</strong></span>';
                }
                $sizeDisplay .= '</div>';
            } else if (!empty($prod['size'])) {
                $sizeDisplay = '<div style="color: var(--text-muted); font-size: 0.85em; margin-top: 4px;">Available sizes: ' . htmlspecialchars($prod['size']) . '</div>';
            } else {
                $sizeDisplay = '<div style="color: var(--text-muted); font-size: 0.85em; margin-top: 4px;">No size tracking</div>';
            }
            
            $html .= '<tr style="vertical-align: top;"><td>' . htmlspecialchars($prod['name']) . '</td><td>' . htmlspecialchars($prod['category']) . '</td><td style="font-weight:bold; text-align:center;">' . number_format($prod['quantity']) . '</td><td>₱' . number_format($prod['stock_value'], 2) . '</td><td>' . $sizeDisplay . '</td></tr>';
        }
        $html .= '</tbody></table>';
        
        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'low_stock':
        $response['title'] = 'Low Stock Items (< 10 Units)';
        
        // Get products with total quantity < 10 OR any size < 10
        $stmt = $connection->prepare("
            SELECT DISTINCT p.id, p.name, c.name as category, p.quantity, p.price, p.cost, p.size
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.quantity < 10
               OR p.id IN (
                   SELECT DISTINCT product_id FROM product_sizes WHERE quantity < 10
               )
            ORDER BY p.quantity ASC, p.name ASC
        ");
        $stmt->execute();
        $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Products With Low Stock</div><div class="detail-summary-value">' . count($lowStockItems) . '</div></div>';
        $html .= '</div>';
        
        if (count($lowStockItems) > 0) {
            $html .= '<table class="detail-table"><thead><tr><th>Product</th><th>Category</th><th>Total Stock</th><th>Unit Price</th><th>Stock by Size</th></tr></thead><tbody>';
            foreach ($lowStockItems as $item) {
                $color = $item['quantity'] == 0 ? '#ef4444' : '#f59e0b';
                
                // Get size stock details
                $sizeStmt = $connection->prepare("
                    SELECT size, quantity FROM product_sizes 
                    WHERE product_id = :product_id 
                    ORDER BY size ASC
                ");
                $sizeStmt->bindParam(':product_id', $item['id']);
                $sizeStmt->execute();
                $sizeStocks = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Build size stock display with highlighting for low stock sizes
                $sizeDisplay = '';
                if (count($sizeStocks) > 0) {
                    $sizeDisplay = '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">';
                    foreach ($sizeStocks as $sz) {
                        $sizeColor = $sz['quantity'] == 0 ? '#ef4444' : ($sz['quantity'] < 10 ? '#f59e0b' : '#22c55e');
                        $sizeDisplay .= '<span style="background: ' . $sizeColor . '20; border: 2px solid ' . $sizeColor . '; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">' . htmlspecialchars($sz['size']) . ': <strong>' . $sz['quantity'] . '</strong></span>';
                    }
                    $sizeDisplay .= '</div>';
                    // Show note if total is ok but some sizes are low
                    if ($item['quantity'] >= 10) {
                        $lowSizeCount = array_filter($sizeStocks, fn($s) => $s['quantity'] < 10);
                        if (count($lowSizeCount) > 0) {
                            $sizeDisplay .= '<div style="color: #f59e0b; font-size: 0.8em; margin-top: 6px; font-style: italic;">⚠️ ' . count($lowSizeCount) . ' size(s) below 10 units</div>';
                        }
                    }
                } else if (!empty($item['size'])) {
                    $sizeDisplay = '<div style="color: var(--text-muted); font-size: 0.85em; margin-top: 4px;">Available sizes: ' . htmlspecialchars($item['size']) . '</div>';
                }
                
                $html .= '<tr style="border-left: 4px solid ' . $color . '; vertical-align: top;"><td>' . htmlspecialchars($item['name']) . '</td><td>' . htmlspecialchars($item['category']) . '</td><td style="font-weight:bold;">' . $item['quantity'] . '</td><td>₱' . number_format($item['price'], 2) . '</td><td>' . $sizeDisplay . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p style="color: var(--text-muted); text-align: center; padding: 20px;">All products have sufficient stock!</p>';
        }
        
        $response['html'] = $html;
        $response['success'] = true;
        break;

    case 'inventory_value':
        $response['title'] = 'Inventory Valuation';
        
        $stmt = $connection->prepare("
            SELECT c.name as category, 
                   COUNT(p.id) as product_count,
                   SUM(p.quantity) as total_qty,
                   SUM(p.quantity * p.cost) as total_cost,
                   SUM(p.quantity * p.price) as retail_value
            FROM products p
            JOIN categories c ON p.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY total_cost DESC
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCost = 0;
        $totalRetail = 0;
        foreach ($categories as $cat) {
            $totalCost += floatval($cat['total_cost']);
            $totalRetail += floatval($cat['retail_value']);
        }
        
        $totalProfit = $totalRetail - $totalCost;
        
        $html = '<div class="detail-summary">';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Total Cost</div><div class="detail-summary-value">₱' . number_format($totalCost, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Retail Value</div><div class="detail-summary-value">₱' . number_format($totalRetail, 2) . '</div></div>';
        $html .= '<div class="detail-summary-item"><div class="detail-summary-label">Potential Profit</div><div class="detail-summary-value" style="color:#4ade80;">₱' . number_format($totalProfit, 2) . '</div></div>';
        $html .= '</div>';
        
        $html .= '<h4 style="margin: 24px 0 12px; color: var(--text-body);">By Category</h4>';
        $html .= '<table class="detail-table"><thead><tr><th>Category</th><th>Items</th><th>Qty</th><th>Cost Value</th><th>Retail Value</th></tr></thead><tbody>';
        foreach ($categories as $cat) {
            $catProfit = floatval($cat['retail_value']) - floatval($cat['total_cost']);
            $html .= '<tr><td>' . htmlspecialchars($cat['category']) . '</td><td>' . $cat['product_count'] . '</td><td>' . number_format($cat['total_qty']) . '</td><td>₱' . number_format($cat['total_cost'], 2) . '</td><td>₱' . number_format($cat['retail_value'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        
        $response['html'] = $html;
        $response['success'] = true;
        break;

    default:
        $response['html'] = '<p style="color: var(--text-muted);">Unknown metric type.</p>';
        break;
}

echo json_encode($response);
