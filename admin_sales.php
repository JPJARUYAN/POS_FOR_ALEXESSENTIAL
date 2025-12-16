<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

// Ensure required models are available
// (Sales, OrderItem, Product, etc. are already autoloaded via _init.php/includes)

// Cashier filter
$cashierId = isset($_GET['cashier']) && $_GET['cashier'] !== '' ? intval($_GET['cashier']) : null;
$cashiers = User::getAll('CASHIER');

// Date range filters
// Default: from first recorded order date up to today,
// so the admin always sees the full sales history unless they filter.
global $connection;
$stmt = $connection->prepare("SELECT MIN(DATE(created_at)) as first_date FROM orders");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$firstOrderDate = $row && $row['first_date'] ? $row['first_date'] : date('Y-m-d');

$start = isset($_GET['start']) ? $_GET['start'] : $firstOrderDate;
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// Basic validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = date('Y-m-d');
}
if ($start > $end) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}


// Helper ranges for today / this week / this month (for quick KPIs)
$today = date('Y-m-d');
// ISO week: Monday as first day
$weekStart = date('Y-m-d', strtotime('monday this week'));
if ($weekStart > $today) {
    // if today is Monday, "monday this week" is today; guard for locale quirks
    $weekStart = $today;
}
$monthStart = date('Y-m-01');

$todaySales = Sales::getSalesByDateRange($today, $today);
$weekSales = Sales::getSalesByDateRange($weekStart, $today);
$monthSales = Sales::getSalesByDateRange($monthStart, $today);

// Aggregate orders in range
global $connection;
$cashierWhere = $cashierId ? ' AND o.cashier_id = :cashier_id' : '';
$stmt = $connection->prepare("
    SELECT 
        o.id,
        o.created_at,
        COUNT(oi.id) as line_items,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as products,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_amount,
        SUM(oi.quantity * p.cost) as total_cost
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN :start AND :end{$cashierWhere}
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$params = [':start' => $start, ':end' => $end];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily aggregates for chart (within selected range)
$stmt = $connection->prepare("
    SELECT 
        DATE(o.created_at) as d,
        COUNT(DISTINCT o.id) as orders,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_amount,
        SUM(oi.quantity * p.cost) as total_cost
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN :start AND :end{$cashierWhere}
    GROUP BY DATE(o.created_at)
    ORDER BY d ASC
");
$params = [':start' => $start, ':end' => $end];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$chartSales = [];
$chartProfit = [];
foreach ($dailyRows as $row) {
    $chartLabels[] = $row['d'];
    $totalAmt = floatval($row['total_amount'] ?? 0);
    $totalCost = floatval($row['total_cost'] ?? 0);
    $chartSales[] = $totalAmt;
    $chartProfit[] = $totalAmt - $totalCost;
}

// Today-only summary (independent of selected range)
$stmt = $connection->prepare("
    SELECT 
        DATE(o.created_at) as d,
        COUNT(DISTINCT o.id) as orders,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as products,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_amount,
        SUM(oi.quantity * p.cost) as total_cost
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) = :today
    GROUP BY DATE(o.created_at)
    ORDER BY d ASC
");
$stmt->execute([':today' => $today]);
$todayRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Weekly summary within range
$stmt = $connection->prepare("
    SELECT 
        YEAR(o.created_at) as yr,
        WEEK(o.created_at, 1) as wk,
        MIN(DATE(o.created_at)) as start_date,
        MAX(DATE(o.created_at)) as end_date,
        COUNT(DISTINCT o.id) as orders,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as products,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_amount,
        SUM(oi.quantity * p.cost) as total_cost
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN :start AND :end
    GROUP BY yr, wk
    ORDER BY yr DESC, wk DESC
");
$stmt->execute([':start' => $start, ':end' => $end]);
$weeklyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly summary within range
$stmt = $connection->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as ym,
        COUNT(DISTINCT o.id) as orders,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as products,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_amount,
        SUM(oi.quantity * p.cost) as total_cost
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN :start AND :end
    GROUP BY ym
    ORDER BY ym DESC
");
$stmt->execute([':start' => $start, ':end' => $end]);
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Sales</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">

    <style>
        .metric.clickable {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .metric.clickable:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
        }

        /* Sales Details Modal */
        .sales-modal-overlay {
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

        .sales-modal-overlay.active {
            display: flex;
        }

        .sales-modal {
            background: var(--card-bg, #0f172a);
            border: 1px solid var(--border-color, #334155);
            border-radius: 16px;
            padding: 0;
            max-width: 900px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .sales-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color, #334155);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--header-bg, #1e293b);
        }

        .sales-modal-title {
            font-size: 1.25em;
            font-weight: 700;
            color: var(--text-body, #e2e8f0);
        }

        .sales-modal-close {
            background: none;
            border: none;
            color: var(--text-muted, #94a3b8);
            cursor: pointer;
            font-size: 24px;
            padding: 4px;
            line-height: 1;
        }

        .sales-modal-close:hover {
            color: var(--text-body, #e2e8f0);
        }

        .sales-modal-body {
            padding: 24px;
            overflow-y: auto;
            max-height: calc(80vh - 80px);
        }

        .detail-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .detail-summary-item {
            background: var(--input-bg, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 8px;
            padding: 16px;
        }

        .detail-summary-label {
            font-size: 0.85em;
            color: var(--text-muted, #94a3b8);
            margin-bottom: 8px;
        }

        .detail-summary-value {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--primary, #3b82f6);
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .detail-table th {
            background: var(--header-bg, #1e293b);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-body, #e2e8f0);
            border-bottom: 2px solid var(--border-color, #334155);
        }

        .detail-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color, #334155);
            color: var(--text-body, #e2e8f0);
        }

        .detail-table tr:hover {
            background: var(--input-bg, #1e293b);
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--text-muted, #94a3b8);
        }
    </style>

    <!-- Datatables  Library -->
    <link rel="stylesheet" type="text/css" href="./css/datatable.css">
    <script src="./js/datatable.js"></script>
    <script src="./js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper" style="max-width: 100%; width:100%; padding:0 16px;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">📊 Sales</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">View sales performance and transactions for a selected date range.</p>
                </div>

                <!-- Filter Section -->
                <div style="background: linear-gradient(to bottom, rgba(102, 126, 234, 0.05), transparent); backdrop-filter: blur(10px); border: 1px solid rgba(102, 126, 234, 0.1); padding: 24px; margin-bottom: 32px; border-radius: 12px;">
                    <form method="get" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.9em; color: #94a3b8; font-weight: 600;">Cashier</label>
                            <select name="cashier"
                                style="padding: 10px 12px; background: #1e293b; border: 2px solid #334155; color: #e2e8f0; border-radius: 8px; font-size: 0.95em; transition: all 0.3s ease; cursor: pointer;">
                                <option value="">All Cashiers</option>
                                <?php foreach ($cashiers as $c): ?>
                                    <option value="<?= $c->id ?>" <?= $cashierId == $c->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.9em; color: #94a3b8; font-weight: 600;">From</label>
                            <input type="date" name="start" value="<?= htmlspecialchars($start) ?>"
                                style="padding: 10px 12px; background: #1e293b; border: 2px solid #334155; color: #e2e8f0; border-radius: 8px; font-size: 0.95em; transition: all 0.3s ease;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.9em; color: #94a3b8; font-weight: 600;">To</label>
                            <input type="date" name="end" value="<?= htmlspecialchars($end) ?>"
                                style="padding: 10px 12px; background: #1e293b; border: 2px solid #334155; color: #e2e8f0; border-radius: 8px; font-size: 0.95em; transition: all 0.3s ease;">
                        </div>
                        <button type="submit" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">Apply Filter</button>
                    </form>
                </div>

                <!-- Today / Week / Month Sales cards -->
                <div class="grid gap-16"
                    style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin:8px 0 24px 0;">
                    <div class="card metric clickable"
                        data-type="today"
                        data-start="<?= $today ?>"
                        data-end="<?= $today ?>"
                        onclick="showSalesDetailsFromCard(this)"
                        style="cursor: pointer;">
                        <div class="metric-title">Today Sales</div>
                        <div class="metric-value">₱ <?= number_format($todaySales, 2) ?></div>
                    </div>
                    <div class="card metric clickable"
                        data-type="week"
                        data-start="<?= $weekStart ?>"
                        data-end="<?= $today ?>"
                        onclick="showSalesDetailsFromCard(this)"
                        style="cursor: pointer;">
                        <div class="metric-title">This Week Sales</div>
                        <div class="metric-value">₱ <?= number_format($weekSales, 2) ?></div>
                    </div>
                    <div class="card metric clickable"
                        data-type="month"
                        data-start="<?= $monthStart ?>"
                        data-end="<?= $today ?>"
                        onclick="showSalesDetailsFromCard(this)"
                        style="cursor: pointer;">
                        <div class="metric-title">This Month Sales</div>
                        <div class="metric-value">₱ <?= number_format($monthSales, 2) ?></div>
                    </div>
                </div>

                <!-- Sales vs Profit chart -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-body">
                        <div
                            style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div class="subtitle" style="margin:0;">Sales vs Profit (Daily)</div>
                        </div>
                        <div style="height:320px;">
                            <canvas id="salesProfitChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Transactions table -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-body">
                        <div
                            style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div class="subtitle" style="margin:0;">Transactions</div>
                        </div>

                        <div class="table-responsive">
                            <table id="transactionsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Products</th>
                                        <th>Quantity</th>
                                        <th>Total Sales</th>
                                        <th>Expenses</th>
                                        <th>Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order):
                                        $profit = floatval($order['total_amount'] ?? 0) - floatval($order['total_cost'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($order['id']) ?></td>
                                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['created_at']))) ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['products'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= number_format($order['total_quantity']) ?></td>
                                            <td>₱ <?= number_format($order['total_amount'], 2) ?></td>
                                            <td>₱ <?= number_format($order['total_cost'], 2) ?></td>
                                            <td style="color: <?= $profit >= 0 ? '#4ade80' : '#ef4444' ?>;">₱
                                                <?= number_format($profit, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <!-- Daily / Weekly / Monthly summaries -->
                <div class="grid gap-16"
                    style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-bottom:24px;">
                    <!-- Daily -->
                    <div class="card">
                        <div class="card-body">
                            <div class="subtitle" style="margin:0 0 8px 0;">Today Summary</div>
                            <div class="table-responsive">
                                <table id="dailySummaryTable" class="table"
                                    style="font-size:0.85em; table-layout:fixed; width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Products</th>
                                            <th>Transactions</th>
                                            <th>Qty</th>
                                            <th>Sales</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayRows as $row):
                                            $dProfit = floatval($row['total_amount'] ?? 0) - floatval($row['total_cost'] ?? 0);
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['d']) ?></td>
                                                <td><?= htmlspecialchars($row['products'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format($row['orders']) ?></td>
                                                <td><?= number_format($row['total_quantity']) ?></td>
                                                <td>₱ <?= number_format($row['total_amount'], 2) ?></td>
                                                <td style="color: <?= $dProfit >= 0 ? '#4ade80' : '#ef4444' ?>;">₱
                                                    <?= number_format($dProfit, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly -->
                    <div class="card">
                        <div class="card-body">
                            <div class="subtitle" style="margin:0 0 8px 0;">Weekly Summary</div>
                            <div class="table-responsive">
                                <table id="weeklySummaryTable" class="table"
                                    style="font-size:0.85em; table-layout:fixed; width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Week</th>
                                            <th>Products</th>
                                            <th>Transactions</th>
                                            <th>Qty</th>
                                            <th>Sales</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($weeklyRows as $row):
                                            $wProfit = floatval($row['total_amount'] ?? 0) - floatval($row['total_cost'] ?? 0);
                                            $label = date('M j', strtotime($row['start_date'])) . ' - ' . date('M j', strtotime($row['end_date']));
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($label) ?></td>
                                                <td><?= htmlspecialchars($row['products'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format($row['orders']) ?></td>
                                                <td><?= number_format($row['total_quantity']) ?></td>
                                                <td>₱ <?= number_format($row['total_amount'], 2) ?></td>
                                                <td style="color: <?= $wProfit >= 0 ? '#4ade80' : '#ef4444' ?>;">₱
                                                    <?= number_format($wProfit, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly -->
                    <div class="card">
                        <div class="card-body">
                            <div class="subtitle" style="margin:0 0 8px 0;">Monthly Summary</div>
                            <div class="table-responsive">
                                <table id="monthlySummaryTable" class="table"
                                    style="font-size:0.85em; table-layout:fixed; width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Products</th>
                                            <th>Transactions</th>
                                            <th>Qty</th>
                                            <th>Sales</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthlyRows as $row):
                                            $mProfit = floatval($row['total_amount'] ?? 0) - floatval($row['total_cost'] ?? 0);
                                            $label = date('M Y', strtotime($row['ym'] . '-01'));
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($label) ?></td>
                                                <td><?= htmlspecialchars($row['products'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format($row['orders']) ?></td>
                                                <td><?= number_format($row['total_quantity']) ?></td>
                                                <td>₱ <?= number_format($row['total_amount'], 2) ?></td>
                                                <td style="color: <?= $mProfit >= 0 ? '#4ade80' : '#ef4444' ?>;">₱
                                                    <?= number_format($mProfit, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script type="text/javascript">
        function showSalesDetailsFromCard(cardEl) {
            if (!cardEl) return;
            const type = cardEl.dataset.type;
            const startDate = cardEl.dataset.start;
            const endDate = cardEl.dataset.end;
            showSalesDetails(type, startDate, endDate);
        }

        // Function to show sales details in modal
        function showSalesDetails(type, startDate, endDate) {
            const modal = document.getElementById('salesModal');
            const modalTitle = document.getElementById('salesModalTitle');
            const modalBody = document.getElementById('salesModalBody');

            modal.classList.add('active');
            modalBody.innerHTML = '<div class="loading-spinner">Loading...</div>';

            // Fetch details from API
            const params = new URLSearchParams({ type });
            if (startDate) params.append('start', startDate);
            if (endDate) params.append('end', endDate);

            // Add range indicator in title
            const rangeLabel = startDate && endDate ? ` (${startDate} → ${endDate})` : '';
            modalTitle.textContent = `Sales Details - ${type.toUpperCase()}${rangeLabel}`;

            fetch(`api/metric_details.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = data.title;
                        modalBody.innerHTML = data.html;
                        
                        // Add filter button at the bottom
                        const filterBtn = document.createElement('div');
                        filterBtn.style.cssText = 'margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color, #334155); text-align: center;';
                        filterBtn.innerHTML = `<button class="btn btn-primary" onclick="filterByDateRange('${startDate}', '${endDate}')" style="padding: 10px 24px;">Filter by This Period</button>`;
                        modalBody.appendChild(filterBtn);
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    console.error('Error:', error);
                });
        }

        function closeSalesModal() {
            document.getElementById('salesModal').classList.remove('active');
        }

        // Function to filter by date range when clicking on metric cards
        function filterByDateRange(startDate, endDate) {
            closeSalesModal();
            const form = document.querySelector('form[method="get"]');
            if (form) {
                const startInput = form.querySelector('input[name="start"]');
                const endInput = form.querySelector('input[name="end"]');
                if (startInput && endInput) {
                    startInput.value = startDate;
                    endInput.value = endDate;
                    form.submit();
                }
            }
        }

        // Close modal on overlay click
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('salesModal');
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeSalesModal();
                    }
                });
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeSalesModal();
                }
            });
        });

        // DataTables with entries-per-page controls
        var dataTable = new simpleDatatables.DataTable("#transactionsTable", {
            perPage: 10,
            perPageSelect: [10, 25, 50, 100]
        });

        var dailySummaryTable = new simpleDatatables.DataTable("#dailySummaryTable", {
            perPage: 5,
            perPageSelect: [5, 10, 25]
        });

        var weeklySummaryTable = new simpleDatatables.DataTable("#weeklySummaryTable", {
            perPage: 5,
            perPageSelect: [5, 10, 25]
        });

        var monthlySummaryTable = new simpleDatatables.DataTable("#monthlySummaryTable", {
            perPage: 5,
            perPageSelect: [5, 10, 25]
        });

        // Sales vs Profit chart
        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('salesProfitChart');
            if (ctx && typeof Chart !== 'undefined') {
                var labels = <?= json_encode($chartLabels) ?>;
                var salesData = <?= json_encode($chartSales) ?>;
                var profitData = <?= json_encode($chartProfit) ?>;

                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Sales',
                                data: salesData,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59,130,246,0.15)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2
                            },
                            {
                                label: 'Profit',
                                data: profitData,
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34,197,94,0.15)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: { color: '#e5e7eb' }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#9ca3af' },
                                grid: { color: 'rgba(148,163,184,0.1)' }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#9ca3af' },
                                grid: { color: 'rgba(148,163,184,0.1)' }
                            }
                        }
                    }
                });
            }
        });
    </script>

    <!-- Sales Details Modal -->
    <div class="sales-modal-overlay" id="salesModal">
        <div class="sales-modal">
            <div class="sales-modal-header">
                <span class="sales-modal-title" id="salesModalTitle">Sales Details</span>
                <button class="sales-modal-close" onclick="closeSalesModal()">&times;</button>
            </div>
            <div class="sales-modal-body" id="salesModalBody">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

</body>

</html>