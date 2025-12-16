<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

global $connection;

// Get all cashier users
$stmt = $connection->prepare("SELECT id, name FROM users WHERE role = 'CASHIER' ORDER BY name");
$stmt->execute();
$cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$selected_cashier = isset($_GET['cashier_id']) ? intval($_GET['cashier_id']) : null;

// Build performance data
$performanceData = [];

foreach ($cashiers as $cashier) {
    $cashier_id = $cashier['id'];
    
    // Get all orders for this cashier in the date range
    $stmt = $connection->prepare("
        SELECT 
            o.id,
            o.created_at,
            SUM(oi.price * oi.quantity) as total_amount,
            COUNT(DISTINCT oi.id) as item_count,
            SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.cashier_id = :cashier_id 
            AND DATE(o.created_at) >= :start_date 
            AND DATE(o.created_at) <= :end_date
        GROUP BY o.id
    ");
    $stmt->bindParam(':cashier_id', $cashier_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate metrics
    $total_transactions = count($orders);
    $total_sales = 0;
    $total_items = 0;
    $total_quantity = 0;
    
    foreach ($orders as $order) {
        $total_sales += $order['total_amount'] ?? 0;
        $total_items += $order['item_count'] ?? 0;
        $total_quantity += $order['total_quantity'] ?? 0;
    }
    
    $avg_transaction = $total_transactions > 0 ? $total_sales / $total_transactions : 0;
    $avg_items_per_transaction = $total_transactions > 0 ? $total_items / $total_transactions : 0;
    
    // Get hourly breakdown for this cashier
    $stmt = $connection->prepare("
        SELECT 
            HOUR(o.created_at) as hour,
            COUNT(o.id) as transaction_count,
            SUM(oi.price * oi.quantity) as sales_amount
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.cashier_id = :cashier_id 
            AND DATE(o.created_at) >= :start_date 
            AND DATE(o.created_at) <= :end_date
        GROUP BY HOUR(o.created_at)
        ORDER BY hour ASC
    ");
    $stmt->bindParam(':cashier_id', $cashier_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily breakdown
    $stmt = $connection->prepare("
        SELECT 
            DATE(o.created_at) as date,
            COUNT(o.id) as transaction_count,
            SUM(oi.price * oi.quantity) as sales_amount
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.cashier_id = :cashier_id 
            AND DATE(o.created_at) >= :start_date 
            AND DATE(o.created_at) <= :end_date
        GROUP BY DATE(o.created_at)
        ORDER BY date ASC
    ");
    $stmt->bindParam(':cashier_id', $cashier_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment method breakdown
    $stmt = $connection->prepare("
        SELECT 
            o.payment_method,
            COUNT(o.id) as transaction_count,
            SUM(oi.price * oi.quantity) as sales_amount
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.cashier_id = :cashier_id 
            AND DATE(o.created_at) >= :start_date 
            AND DATE(o.created_at) <= :end_date
        GROUP BY o.payment_method
    ");
    $stmt->bindParam(':cashier_id', $cashier_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $performanceData[$cashier_id] = [
        'name' => $cashier['name'],
        'total_transactions' => $total_transactions,
        'total_sales' => $total_sales,
        'total_items' => $total_items,
        'total_quantity' => $total_quantity,
        'avg_transaction' => $avg_transaction,
        'avg_items_per_transaction' => $avg_items_per_transaction,
        'hourly_data' => $hourly_data,
        'daily_data' => $daily_data,
        'payment_data' => $payment_data
    ];
}

// Get overall stats for comparison
$stmt = $connection->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_transactions,
        SUM(oi.price * oi.quantity) as total_sales,
        COUNT(DISTINCT o.cashier_id) as active_cashiers
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE DATE(o.created_at) >= :start_date 
        AND DATE(o.created_at) <= :end_date
");
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$overall = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Staff Performance Reports</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">
    <link rel="stylesheet" type="text/css" href="./css/datatable.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .staff-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .staff-header-title {
            font-size: 2em;
            font-weight: 800;
            margin: 0 0 8px 0;
        }

        .staff-header-subtitle {
            font-size: 1em;
            opacity: 0.9;
            margin: 0;
        }

        .staff-filters {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: flex-end;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.85em;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px 14px;
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 8px;
            color: var(--input-text);
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            background: var(--card-bg);
        }

        .btn-filter {
            padding: 12px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-filter:active {
            transform: translateY(0);
        }

        .overall-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 0.8em;
            color: var(--text-muted);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .staff-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .staff-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .staff-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-bottom: none;
            position: relative;
        }

        .staff-card-name {
            font-size: 1.35em;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .staff-card-body {
            padding: 24px;
        }

        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95em;
        }

        .metric-row:last-child {
            border-bottom: none;
        }

        .metric-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .metric-value {
            font-weight: 700;
            color: var(--text-body);
            text-align: right;
        }

        .metric-value.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.15em;
        }

        .staff-chart {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .staff-chart-title {
            font-size: 0.95em;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-body);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-container {
            position: relative;
            height: 250px;
            margin-bottom: 12px;
            background: var(--input-bg);
            border-radius: 8px;
            padding: 12px;
        }

        .payment-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .payment-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .payment-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            transform: translateY(-2px);
        }

        .payment-type {
            font-size: 0.8em;
            color: var(--text-muted);
            margin-bottom: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .payment-amount {
            font-size: 1.2em;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .ranking-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: #667eea;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }

        .ranking-badge.gold {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);
        }

        .ranking-badge.silver {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            color: #374151;
            box-shadow: 0 2px 8px rgba(209, 213, 219, 0.3);
        }

        .ranking-badge.bronze {
            background: linear-gradient(135deg, #d2691e 0%, #cd7f32 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(205, 127, 50, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: var(--card-bg);
            border-radius: 16px;
            border: 2px dashed var(--border-color);
            margin-top: 40px;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state-text {
            color: var(--text-muted);
            font-size: 1.1em;
            margin: 0;
        }

        @media (max-width: 1024px) {
            .staff-filters {
                grid-template-columns: 1fr 1fr;
            }

            .staff-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .staff-filters {
                grid-template-columns: 1fr;
            }

            .staff-grid {
                grid-template-columns: 1fr;
            }

            .payment-breakdown {
                grid-template-columns: 1fr;
            }

            .staff-header {
                padding: 24px 16px;
            }

            .staff-header-title {
                font-size: 1.5em;
            }
        }
    </style>
</head>

<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper">
                <div class="staff-header">
                    <h1 class="staff-header-title">👥 Staff Performance Reports</h1>
                    <p class="staff-header-subtitle">Real-time sales and activity metrics for each cashier</p>
                </div>

                <!-- Filter Section -->
                <form method="GET" class="staff-filters">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>
                    <div class="filter-group">
                        <label>Cashier (Optional)</label>
                        <select name="cashier_id">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $selected_cashier === intval($c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">Filter</button>
                </form>

                <!-- Overall Stats -->
                <div class="overall-stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-value"><?= number_format($overall['total_transactions'] ?? 0) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Sales</div>
                        <div class="stat-value">₱ <?= number_format($overall['total_sales'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active Cashiers</div>
                        <div class="stat-value"><?= number_format($overall['active_cashiers'] ?? 0) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Avg per Cashier</div>
                        <div class="stat-value">
                            ₱ <?= $overall['active_cashiers'] > 0 ? number_format(($overall['total_sales'] ?? 0) / $overall['active_cashiers'], 2) : '0.00' ?>
                        </div>
                    </div>
                </div>

                <!-- Staff Performance Cards -->
                <div class="staff-grid">
                    <?php 
                    // Sort by total sales descending for ranking
                    usort($performanceData, function($a, $b) {
                        return $b['total_sales'] <=> $a['total_sales'];
                    });

                    $rank = 1;
                    foreach ($performanceData as $cashier_id => $data):
                        if (empty($data['total_transactions'])) continue;
                        
                        $badgeClass = '';
                        $badgeText = '';
                        if ($rank === 1) {
                            $badgeClass = 'gold';
                            $badgeText = '🥇 #1';
                        } elseif ($rank === 2) {
                            $badgeClass = 'silver';
                            $badgeText = '🥈 #2';
                        } elseif ($rank === 3) {
                            $badgeClass = 'bronze';
                            $badgeText = '🥉 #3';
                        }
                    ?>
                        <div class="staff-card">
                            <div class="staff-card-header">
                                <h3 class="staff-card-name">
                                    <?= htmlspecialchars($data['name']) ?>
                                    <?php if ($badgeClass): ?>
                                        <span class="ranking-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="staff-card-body">
                                <div class="metric-row">
                                    <span class="metric-label">Total Sales</span>
                                    <span class="metric-value highlight">₱ <?= number_format($data['total_sales'], 2) ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Transactions</span>
                                    <span class="metric-value"><?= number_format($data['total_transactions']) ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Avg per Transaction</span>
                                    <span class="metric-value">₱ <?= number_format($data['avg_transaction'], 2) ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Items Sold</span>
                                    <span class="metric-value"><?= number_format($data['total_items']) ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Total Quantity</span>
                                    <span class="metric-value"><?= number_format($data['total_quantity']) ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Avg Items/Transaction</span>
                                    <span class="metric-value"><?= number_format($data['avg_items_per_transaction'], 1) ?></span>
                                </div>

                                <?php if (!empty($data['payment_data'])): ?>
                                    <div class="staff-chart">
                                        <div class="staff-chart-title">Payment Methods</div>
                                        <div class="payment-breakdown">
                                            <?php foreach ($data['payment_data'] as $payment): ?>
                                                <div class="payment-item">
                                                    <div class="payment-type"><?= htmlspecialchars($payment['payment_method'] ?? 'Unknown') ?></div>
                                                    <div class="payment-amount">₱ <?= number_format($payment['sales_amount'] ?? 0, 2) ?></div>
                                                    <div class="muted" style="font-size: 0.75em; margin-top: 4px;">
                                                        <?= number_format($payment['transaction_count']) ?> trans.
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($data['daily_data'])): ?>
                                    <div class="staff-chart">
                                        <div class="staff-chart-title">Daily Sales Trend</div>
                                        <div class="chart-container">
                                            <canvas class="daily-chart" data-cashier="<?= $cashier_id ?>"></canvas>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </div>

                <?php if (empty(array_filter($performanceData, function($d) { return $d['total_transactions'] > 0; }))): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p class="empty-state-text">No performance data available for the selected date range.</p>
                        <p class="muted" style="margin-top: 12px;">Try adjusting your date range or ensure cashiers have completed transactions.</p>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Prepare data for charts
            const performanceData = <?= json_encode($performanceData) ?>;

            // Create daily sales trend charts
            document.querySelectorAll('.daily-chart').forEach(canvas => {
                const cashierId = parseInt(canvas.dataset.cashier);
                const data = performanceData[cashierId];

                if (data && data.daily_data && data.daily_data.length > 0) {
                    const labels = data.daily_data.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    const sales = data.daily_data.map(d => parseFloat(d.sales_amount || 0));

                    new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Daily Sales',
                                data: sales,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.08)',
                                borderWidth: 3,
                                fill: true,
                                pointRadius: 5,
                                pointBackgroundColor: '#667eea',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return '₱' + value.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                }
            });
        });
    </script>

</body>

</html>
