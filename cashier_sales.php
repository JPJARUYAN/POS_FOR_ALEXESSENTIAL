<?php
// Cashier guard - only cashiers can access this page
require_once '_guards.php';
Guard::cashierOnly();

// Get current cashier info
$currentCashier = User::getAuthenticatedUser(ROLE_CASHIER);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History :: Cashier</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">

    <style>
        body {
            background: var(--bg-body);
            color: var(--text-body);
        }

        .sales-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            color: white;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .filters-section {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .filter-row {
            display: flex;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            flex: 1;
        }

        .filter-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-body);
            font-size: 14px;
            font-weight: 600;
        }

        .filter-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 8px;
            color: var(--input-text);
            font-size: 14px;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-filter {
            padding: 12px 24px;
            background: var(--primary);
            border: none;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-body);
        }

        .stat-value.green {
            color: #4ade80;
        }

        .stat-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 48px;
            opacity: 0.15;
        }

        .orders-section {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 18px;
            color: var(--text-body);
            font-weight: 700;
            margin: 0;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background: var(--header-bg);
            padding: 16px;
            text-align: left;
            color: var(--text-body);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .orders-table td {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            color: var(--text-body);
        }

        .orders-table tr:hover {
            background: var(--btn-hover-bg);
        }

        .order-id {
            font-weight: 700;
            color: var(--primary);
        }

        .payment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .payment-badge.cash {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .payment-badge.card {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .payment-badge.e-wallet {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
        }

        .amount {
            font-weight: 700;
            color: #4ade80;
        }

        .btn-reprint {
            padding: 6px 12px;
            background: var(--btn-bg);
            border: 1px solid var(--btn-border);
            color: var(--btn-text);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .btn-reprint:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .spinner {
            border: 3px solid var(--border-color);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php require 'templates/admin_header.php' ?>

    <div class="sales-container">
        <div class="page-header">
            <h1>📊 Sales History</h1>
            <p class="page-subtitle">View your transaction history and performance</p>
        </div>

        <div class="filters-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <input type="date" id="dateFrom" class="filter-input" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <input type="date" id="dateTo" class="filter-input" value="<?= date('Y-m-d') ?>">
                </div>
                <button class="btn-filter" onclick="loadSales()">Apply Filter</button>
            </div>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value green" id="totalSales">₱ 0.00</div>
                <div class="stat-icon">💰</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value" id="totalOrders">0</div>
                <div class="stat-icon">📦</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Cash Payments</div>
                <div class="stat-value green" id="cashSales">₱ 0.00</div>
                <div class="stat-icon">💵</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Card/E-Wallet</div>
                <div class="stat-value green" id="digitalSales">₱ 0.00</div>
                <div class="stat-icon">💳</div>
            </div>
        </div>

        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Transaction History</h2>
            </div>

            <div id="ordersContent">
                <div class="loading">
                    <div class="spinner"></div>
                    <div>Loading transactions...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load sales data on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadSales();
        });

        async function loadSales() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const ordersContent = document.getElementById('ordersContent');

            ordersContent.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <div>Loading transactions...</div>
            </div>
        `;

            try {
                const response = await fetch(
                    `api/cashier_sales.php?date_from=${dateFrom}&date_to=${dateTo}`
                );
                const data = await response.json();

                if (data.success) {
                    updateStats(data.summary);
                    renderOrders(data.orders);
                } else {
                    ordersContent.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">❌</div>
                        <div>Error loading sales data</div>
                    </div>
                `;
                }
            } catch (error) {
                console.error(error);
                ordersContent.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <div>Failed to load sales data</div>
                </div>
            `;
            }
        }

        function updateStats(summary) {
            document.getElementById('totalSales').textContent = '₱ ' + parseFloat(summary.total_sales || 0)
                .toFixed(2);
            document.getElementById('totalOrders').textContent = summary.total_orders || 0;
            document.getElementById('cashSales').textContent = '₱ ' + parseFloat(summary.cash_sales || 0)
                .toFixed(2);

            const digitalTotal = parseFloat(summary.card_sales || 0) + parseFloat(summary.ewallet_sales ||
                0);
            document.getElementById('digitalSales').textContent = '₱ ' + digitalTotal.toFixed(2);
        }

        function renderOrders(orders) {
            const ordersContent = document.getElementById('ordersContent');

            if (orders.length === 0) {
                ordersContent.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div>No transactions found for this period</div>
                </div>
            `;
                return;
            }

            let html = `
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Payment</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
        `;

            orders.forEach(order => {
                const date = new Date(order.created_at);
                const formattedDate = date.toLocaleDateString('en-PH', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                const formattedTime = date.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const customerName = order.customer_name || 'Walk-in Customer';
                const paymentMethod = order.payment_method || 'cash';
                const amount = parseFloat(order.total_amount || 0);

                html += `
                <tr>
                    <td><span class="order-id">#${order.id}</span></td>
                    <td>
                        <div>${formattedDate}</div>
                        <div class="order-time">${formattedTime}</div>
                    </td>
                    <td>${customerName}</td>
                    <td>${order.item_count} item(s)</td>
                    <td><span class="payment-badge ${paymentMethod}">${paymentMethod}</span></td>
                    <td><span class="amount">₱ ${amount.toFixed(2)}</span></td>
                    <td>
                        <button class="btn-reprint" onclick="reprintReceipt(${order.id})">
                            🖨️ Reprint
                        </button>
                    </td>
                </tr>
            `;
            });

            html += `
                </tbody>
            </table>
        `;

            ordersContent.innerHTML = html;
        }

        function reprintReceipt(orderId) {
            window.open(`api/generate_receipt_pdf.php?order_id=${orderId}`, '_blank');
        }
    </script>
</body>

</html>