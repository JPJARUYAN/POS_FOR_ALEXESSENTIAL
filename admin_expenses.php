<?php
require_once '_init.php';

Guard::adminOnly();

// Set role context for navbar
$GLOBALS['CURRENT_ROLE_CONTEXT'] = ROLE_ADMIN;

$title = 'Expense & Cost Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/util.css">
    <link rel="stylesheet" href="css/datatable.css">
    <style>
        main {
            width: 100%;
        }
        
        .wrapper {
            max-width: 100%;
            padding: 16px;
            width: 100%;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 24px;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-body);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--input-text);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .btn-generate {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-darker) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }
        
        .btn-generate:active {
            transform: translateY(0);
        }
        
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .metric-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-lighter) 100%);
        }
        
        .metric-card.revenue::before {
            background: linear-gradient(90deg, #ec4899 0%, #f43f5e 100%);
        }
        
        .metric-card.expense::before {
            background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%);
        }
        
        .metric-card.profit::before {
            background: linear-gradient(90deg, #10b981 0%, #14b8a6 100%);
        }
        
        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
            border-color: var(--primary);
        }
        
        .metric-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 8px;
        }
        
        .metric-subtext {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-body);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        
        th {
            background: var(--input-bg);
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-body);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-body);
        }
        
        tbody tr:hover {
            background: var(--input-bg);
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-muted {
            color: var(--text-muted);
        }
        
        .profit-positive {
            color: var(--green-500);
            font-weight: 600;
        }
        
        .profit-negative {
            color: var(--red-500);
            font-weight: 600;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-size: 16px;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--red-500);
            color: var(--red-500);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .no-data-message {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .btn-export {
            padding: 10px 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-export:active {
            transform: translateY(0);
        }
        
        @media (max-width: 1024px) {
            .filters-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .metrics-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .wrapper {
                padding: 12px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .metrics-container {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .metric-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'templates/admin_header.php'; ?>
    
    <div class="flex">
        <?php require_once 'templates/admin_navbar.php'; ?>
        
        <main style="flex: 1; width: 100%;">
            <div class="wrapper">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">💸 <?= $title ?></h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Track and analyze your business expenses</p>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Filter & Generate Report</h2>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="startDate">Start Date</label>
                            <input type="date" id="startDate">
                        </div>
                        <div class="filter-group">
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate">
                        </div>
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select id="category">
                                <option value="">All Categories</option>
                                <?php
                                $categories = Category::all();
                                foreach ($categories as $cat):
                                ?>
                                    <option value="<?= $cat->id ?>"><?= htmlspecialchars($cat->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button class="btn-generate" onclick="generateReport()">Generate Report</button>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                    <button class="btn-export" onclick="exportToPDF()" title="Export data as PDF">
                        <span>📄</span> Export PDF
                    </button>
                </div>
                
                <div id="reportContent" class="card">
                    <div class="loading">Loading...</div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Wait for page to fully load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPage);
        } else {
            initPage();
        }
        
        function initPage() {
            console.log('Page initialized');
            
            // Set date inputs
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            const pad = (n) => String(n).padStart(2, '0');
            const y = today.getFullYear();
            const m = pad(today.getMonth() + 1);
            const d = pad(today.getDate());
            const fy = firstDay.getFullYear();
            const fm = pad(firstDay.getMonth() + 1);
            const fd = pad(firstDay.getDate());
            
            const startDate = `${fy}-${fm}-${fd}`;
            const endDate = `${y}-${m}-${d}`;
            
            document.getElementById('startDate').value = startDate;
            document.getElementById('endDate').value = endDate;
            
            console.log('Dates set:', startDate, endDate);
            
            // Load report
            setTimeout(() => generateReport(), 200);
        }
        
        function generateReport() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const categoryId = document.getElementById('category').value;
            
            console.log('Generate report:', { startDate, endDate, categoryId });
            
            if (!startDate || !endDate) {
                alert('Dates required');
                return;
            }
            
            const reportDiv = document.getElementById('reportContent');
            reportDiv.innerHTML = '<div class="loading">Loading expense data...</div>';
            
            const payload = {
                start_date: startDate,
                end_date: endDate,
                category_id: categoryId ? parseInt(categoryId) : null
            };
            
            console.log('Sending:', payload);
            
            fetch('api/expenses_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                
                if (data.error) {
                    reportDiv.innerHTML = `<div class="error-message"><strong>API Error:</strong> ${data.error}</div>`;
                    return;
                }
                
                reportDiv.innerHTML = buildHTML(data);
            })
            .catch(error => {
                console.error('Error:', error);
                reportDiv.innerHTML = `<div class="error-message"><strong>Error:</strong> ${error.message}</div>`;
            });
        }
        
        function buildHTML(data) {
            console.log('Building HTML from:', data);
            
            if (!data || !data.total_orders) {
                return `
                    <div class="no-data-message">No data for this date range</div>
                `;
            }
            
            const profitMargin = data.total_revenue > 0 ? (data.total_profit / data.total_revenue * 100).toFixed(2) : 0;
            
            let html = `<div class="metrics-container">
                <div class="metric-card revenue">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">₱${format(data.total_revenue)}</div>
                    <div class="metric-subtext">${data.total_orders} orders</div>
                </div>
                <div class="metric-card expense">
                    <div class="metric-label">Total Expenses</div>
                    <div class="metric-value">₱${format(data.total_expenses)}</div>
                    <div class="metric-subtext">${data.total_items_sold} items</div>
                </div>
                <div class="metric-card profit">
                    <div class="metric-label">Total Profit</div>
                    <div class="metric-value">₱${format(data.total_profit)}</div>
                    <div class="metric-subtext">${profitMargin}% margin</div>
                </div>
            </div>`;
            
            if (data.products && data.products.length) {
                html += '<div><h3 class="section-title">Product Performance</h3><div class="table-wrapper"><table><thead><tr>';
                html += '<th>Product</th><th class="text-right">Category</th><th class="text-right">Qty</th>';
                html += '<th class="text-right">Cost</th><th class="text-right">Price</th><th class="text-right">Total Cost</th>';
                html += '<th class="text-right">Revenue</th><th class="text-right">Profit</th>';
                html += '</tr></thead><tbody>';
                
                data.products.forEach(p => {
                    const profitClass = p.profit >= 0 ? 'profit-positive' : 'profit-negative';
                    
                    html += `<tr>
                        <td><strong>${sanitize(p.product_name)}</strong></td>
                        <td class="text-right text-muted">${sanitize(p.category_name)}</td>
                        <td class="text-right">${p.units_sold}</td>
                        <td class="text-right">₱${format(p.cost)}</td>
                        <td class="text-right">₱${format(p.price)}</td>
                        <td class="text-right">₱${format(p.total_cost)}</td>
                        <td class="text-right"><strong>₱${format(p.total_revenue)}</strong></td>
                        <td class="text-right ${profitClass}">₱${format(p.profit)}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div></div>';
            }
            
            return html;
        }
        
        function format(n) {
            return parseFloat(n || 0).toFixed(2);
        }
        
        function sanitize(str) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return (str || '').replace(/[&<>"']/g, m => map[m]);
        }
        
        let lastReportData = null;
        
        function exportToPDF() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const categoryId = document.getElementById('category').value;
            
            if (!startDate || !endDate) {
                alert('Please set date range first');
                return;
            }
            
            fetch('api/export_expenses_pdf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    category_id: categoryId ? parseInt(categoryId) : null
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to generate report');
                }
                return response.text();
            })
            .then(html => {
                // Open the HTML in a new window/tab
                const newWindow = window.open();
                newWindow.document.write(html);
                newWindow.document.close();
                
                // Auto-trigger print dialog after short delay
                setTimeout(() => {
                    newWindow.print();
                }, 500);
            })
            .catch(error => {
                console.error('PDF Export Error:', error);
                alert('Failed to export PDF: ' + error.message);
            });
        }
    </script>
</body>
</html>
