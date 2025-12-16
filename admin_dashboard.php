<?php
// Guard
require_once '_guards.php';
Guard::adminOnly();

require_once 'models/Sales.php';
require_once 'models/Product.php';
require_once 'models/Category.php';

// Ensure templates use the admin user when rendering this admin page
$currentUser = User::getAuthenticatedUser(ROLE_ADMIN);

// Cashier filter
$cashierId = isset($_GET['cashier']) && $_GET['cashier'] !== '' ? intval($_GET['cashier']) : null;
$cashiers = User::getAll('CASHIER');

global $connection;

// Total sales
$totalSales = Sales::getTotalSales();

// Total expenses (cost of goods sold)
$totalExpenses = Sales::getTotalExpenses();

// Actual profit (sales - expenses)
$actualProfit = $totalSales - $totalExpenses;

// Total transactions (count of order items)
$stmt = $connection->prepare('SELECT COUNT(*) as total_transactions FROM order_items');
$stmt->execute();
$totalTransactions = intval($stmt->fetch(PDO::FETCH_ASSOC)['total_transactions'] ?? 0);

// Low stock items (products with quantity < 10)
$stmt = $connection->prepare('SELECT COUNT(*) as low_stock FROM products WHERE quantity < 10');
$stmt->execute();
$lowStockCount = intval($stmt->fetch(PDO::FETCH_ASSOC)['low_stock'] ?? 0);


?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/util.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric.clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .metric.clickable:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
            border-color: var(--primary);
        }

        /* Modal Styles */
        .metric-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .metric-modal-overlay.active {
            display: flex;
        }

        .metric-modal {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 0;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .metric-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--header-bg);
        }

        .metric-modal-title {
            font-size: 1.25em;
            font-weight: 700;
            color: var(--text-body);
        }

        .metric-modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 24px;
            padding: 4px;
        }

        .metric-modal-close:hover {
            color: var(--text-body);
        }

        .metric-modal-body {
            padding: 24px;
            overflow-y: auto;
            max-height: calc(80vh - 80px);
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-table th {
            background: var(--header-bg);
            font-weight: 600;
            color: var(--text-body);
        }

        .detail-table td {
            color: var(--text-body);
        }

        .detail-table tbody tr:hover {
            background: var(--btn-hover-bg);
        }

        .detail-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .detail-summary-item {
            background: var(--input-bg);
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .detail-summary-label {
            font-size: 0.85em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .detail-summary-value {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--primary);
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin: 16px;
            padding: 0;
        }

        @media (max-width: 1024px) {
            .charts-grid {
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 12px;
            }

            .charts-grid {
                margin: 12px;
            }
        }
    </style>
</head>

<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main style="flex: 1; width: 100%;">
            <div class="wrapper" style="max-width: 100%; padding: 0; width: 100%;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:24px;">
                        <div>
                            <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">📊 Dashboard</h1>
                            <p style="margin: 0; opacity: 0.9; font-size: 1em;">Real-time sales metrics and key performance indicators</p>
                        </div>
                        <form method="get" style="display:flex; gap:12px; align-items:center;">
                            <label style="font-size:0.95em; color: white; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                <span>Filter by Cashier:</span>
                                <select name="cashier" onchange="this.form.submit()"
                                    style="padding:10px 14px; background:rgba(255,255,255,0.2); border:2px solid rgba(255,255,255,0.3); color:white; border-radius:6px; cursor:pointer; font-weight: 600; backdrop-filter: blur(4px);">
                                    <option value="" style="background: #1e293b; color: white;">All Cashiers</option>
                                    <?php foreach ($cashiers as $c): ?>
                                        <option value="<?= $c->id ?>" <?= $cashierId == $c->id ? 'selected' : '' ?> style="background: #1e293b; color: white;">
                                            <?= htmlspecialchars($c->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                    </div>
                </div>

                <!-- Metrics Grid - Row 1 -->
                <div class="grid gap-16"
                    style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin: 16px; padding: 0;">
                    <div class="card metric clickable" onclick="showMetricDetails('sales')">
                        <div class="metric-title">Total Sales (All Time)</div>
                        <div class="metric-value">₱ <?= number_format($totalSales, 2) ?></div>
                    </div>
                    <div class="card metric clickable" onclick="showMetricDetails('expenses')">
                        <div class="metric-title">Total Expenses</div>
                        <div class="metric-value">₱ <?= number_format($totalExpenses, 2) ?></div>
                    </div>
                    <div class="card metric clickable" onclick="showMetricDetails('profit')">
                        <div class="metric-title">Actual Profit</div>
                        <div class="metric-value" style="color: <?= $actualProfit >= 0 ? '#4ade80' : '#ef4444' ?>;">₱
                            <?= number_format($actualProfit, 2) ?></div>
                    </div>
                    <div class="card metric clickable" onclick="showMetricDetails('transactions')">
                        <div class="metric-title">Total Transactions</div>
                        <div class="metric-value"><?= number_format($totalTransactions) ?></div>
                    </div>
                    <div class="card metric clickable" onclick="showMetricDetails('lowstock')">
                        <div class="metric-title">Low Stock Items</div>
                        <div class="metric-value"><?= number_format($lowStockCount) ?></div>
                    </div>
                </div>

                <!-- Sales Overview Chart -->
                <div class="card" style="margin: 16px; margin-top: 24px;">
                    <div class="card-header"
                        style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Sales Overview</span>
                        <div style="display: flex; gap: 8px;">
                            <button id="weeklyBtn" class="btn"
                                style="padding: 8px 16px; font-size: 0.9em; background-color: #3b82f6; color: white;">Weekly</button>
                            <button id="monthlyBtn" class="btn"
                                style="padding: 8px 16px; font-size: 0.9em;">Monthly</button>
                            <button id="yearlyBtn" class="btn"
                                style="padding: 8px 16px; font-size: 0.9em;">Yearly</button>
                        </div>
                    </div>
                    <div class="card-body" style="height:400px; position: relative;">
                        <canvas id="salesOverviewChart"></canvas>
                    </div>
                </div>

                <!-- Charts Row - 3 columns side by side -->
                <div class="charts-grid mt-16">
                    <div class="card">
                        <div class="card-header">Sales By Day This Week</div>
                        <div class="card-body" style="height:400px; position: relative;">
                            <canvas id="salesByDayChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Top 5 Products Sales (All Time)</div>
                        <div class="card-body" style="height:400px; position: relative;">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions (Full Width) -->
                <div class="card" style="margin: 16px; padding: 0;">
                    <div class="card-header">Recent Transactions</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="recentTransactions">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Products</th>
                                        <th>Quantity</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="muted">Loading transactions...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Metric Details Modal -->
    <div class="metric-modal-overlay" id="metricModal">
        <div class="metric-modal">
            <div class="metric-modal-header">
                <span class="metric-modal-title" id="modalTitle">Details</span>
                <button class="metric-modal-close" onclick="closeMetricModal()">&times;</button>
            </div>
            <div class="metric-modal-body" id="modalBody">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

    <script src="js/admin_dashboard.js?v=<?= time() ?>"></script>
    <script>
        // Metric details functionality
        function showMetricDetails(type) {
            const modal = document.getElementById('metricModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');

            modal.classList.add('active');
            modalBody.innerHTML = '<div class="loading-spinner">Loading...</div>';

            fetch(`api/metric_details.php?type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = data.title;
                        modalBody.innerHTML = data.html;
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    console.error('Error:', error);
                });
        }

        function closeMetricModal() {
            document.getElementById('metricModal').classList.remove('active');
        }

        // Close modal on overlay click
        document.getElementById('metricModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeMetricModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMetricModal();
            }
        });
    </script>

</body>

</html>