document.addEventListener('DOMContentLoaded', function () {
    const recentTransactionsEl = document.getElementById('recentTransactions').querySelector('tbody');
    
    // Chart instances
    let salesByDayChart = null;
    let topProductsChart = null;
    let salesOverviewChart = null;
    let currentOverviewType = 'weekly';
    let metricsData = null;
    // Plugin to draw value labels above points for the Sales Overview chart
    const valueLabelPlugin = {
        id: 'valueLabelPlugin',
        afterDatasetsDraw: (chart) => {
            // Only render labels for the salesOverviewChart canvas
            if (!chart || !chart.canvas || chart.canvas.id !== 'salesOverviewChart') return;
            const ctx = chart.ctx;
            chart.data.datasets.forEach((dataset, datasetIndex) => {
                const meta = chart.getDatasetMeta(datasetIndex);
                if (!meta || !meta.data) return;
                meta.data.forEach((element, index) => {
                    const value = dataset.data[index];
                    if (value === null || value === undefined) return;
                    const pos = element.tooltipPosition();
                    ctx.save();
                    ctx.font = '12px Arial';
                    ctx.fillStyle = '#111827';
                    ctx.textAlign = 'center';
                    const text = Number(value).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    ctx.fillText(text, pos.x, pos.y - 10);
                    ctx.restore();
                });
            });
        }
    };
    // Register plugin (Chart may already be loaded by the time this runs)
    if (typeof Chart !== 'undefined' && Chart.register) {
        try { Chart.register(valueLabelPlugin); } catch (e) { /* ignore if already registered */ }
    }

    async function fetchMetrics() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const cashierId = urlParams.get('cashier');
            const apiUrl = cashierId 
                ? `/POS_SYSTEM/api/dashboard_metrics.php?cashier=${cashierId}` 
                : '/POS_SYSTEM/api/dashboard_metrics.php';
            const res = await fetch(apiUrl, { cache: 'no-store' });
            if (!res.ok) throw new Error('Failed to fetch metrics');
            const json = await res.json();
            metricsData = json;

            // Update Recent Transactions
            if (json.recent && json.recent.length) {
                recentTransactionsEl.innerHTML = json.recent.map(r => {
                    const total = Number(r.total || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    // Safely format MySQL datetime (YYYY-MM-DD HH:MM:SS) for display
                    let dateStr = r.created_at || '';
                    try {
                        const jsDate = new Date(dateStr.replace(' ', 'T'));
                        if (!isNaN(jsDate.getTime())) {
                            dateStr = jsDate.toLocaleString();
                        }
                    } catch (e) {
                        // fallback to raw created_at if parsing fails
                    }
                    const receiptUrl = `api/generate_receipt_pdf.php?order_id=${encodeURIComponent(r.id)}`;
                    const products = r.products || 'N/A';
                    const totalQty = r.total_quantity || 0;
                    const itemCount = r.item_count || 0;
                    
                    // Truncate products if too long
                    let productsDisplay = products;
                    if (products.length > 80) {
                        productsDisplay = products.substring(0, 77) + '...';
                    }
                    
                    return `
                        <tr>
                            <td>
                                <a href="${receiptUrl}" target="_blank" rel="noopener noreferrer" style="color: #3b82f6; text-decoration: none;">
                                    #${r.id}
                                </a>
                            </td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${products}">
                                ${productsDisplay}
                            </td>
                            <td>${totalQty}</td>
                            <td>${itemCount}</td>
                            <td>₱ ${total}</td>
                            <td>${dateStr}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                recentTransactionsEl.innerHTML = '<tr><td colspan="6" class="muted">No recent transactions</td></tr>';
            }

            // Update Sales by Day (last 7 days)
            if (json.last_7_days) {
                const days = Object.keys(json.last_7_days).map(k => json.last_7_days[k].label);
                const totals = Object.keys(json.last_7_days).map(k => Number(json.last_7_days[k].total || 0));
                updateSalesByDay(days, totals);
            }

            // Update Top Categories
            if (json.top_products) {
                renderTopProducts(json.top_products);
            }

            // Update Sales Overview (default to weekly)
            updateSalesOverview(currentOverviewType);

        } catch (err) {
            console.error('Metrics fetch error:', err);
            // Show a user-friendly message in the Recent Transactions table if loading fails
            if (recentTransactionsEl) {
                recentTransactionsEl.innerHTML = '<tr><td colspan="6" class="muted">Failed to load transactions</td></tr>';
            }
        }
    }

    function updateSalesByDay(labels, data) {
        if (!salesByDayChart) {
            salesByDayChart = new Chart(document.getElementById('salesByDayChart').getContext('2d'), {
                type: 'bar',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        label: 'Sales', 
                        data: data, 
                        backgroundColor: ['#60a5fa','#3b82f6','#2563eb','#1d4ed8','#1e40af','#1e3a8a','#1e293b'] 
                    }] 
                },
                options: { 
                    responsive:true, 
                    maintainAspectRatio:false, 
                    plugins:{ legend:{display:false} }, 
                    scales:{ y:{ beginAtZero:true } } 
                }
            });
        } else {
            salesByDayChart.data.labels = labels;
            salesByDayChart.data.datasets[0].data = data;
            salesByDayChart.update();
        }
    }

    function renderTopProducts(items) {
        const labels = items.map(i => i.name);
        const data = items.map(i => Number(i.total));
        const colors = ['#3b82f6','#06b6d4','#10b981','#f59e0b','#ef4444'];
        
        // Calculate total for percentage
        const total = data.reduce((a, b) => a + b, 0);
        const percentages = data.map(v => ((v / total) * 100).toFixed(1));
        
        // Create labels with percentages
        const labelsWithPercentage = labels.map((label, i) => label + ' (' + percentages[i] + '%)');
        
        if (!topProductsChart) {
            topProductsChart = new Chart(document.getElementById('topProductsChart').getContext('2d'), {
                type: 'pie',
                data: { 
                    labels: labelsWithPercentage, 
                    datasets: [{ 
                        data: data, 
                        backgroundColor: colors 
                    }] 
                },
                options: { 
                    responsive:true, 
                    maintainAspectRatio:false, 
                    plugins:{ 
                        legend:{ position:'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return '₱ ' + value.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                                }
                            }
                        }
                    } 
                }
            });
        } else {
            topProductsChart.data.labels = labelsWithPercentage;
            topProductsChart.data.datasets[0].data = data;
            topProductsChart.update();
        }
    }

    function updateSalesOverview(type) {
        if (!metricsData) return;
        let labels = [];
        let data = [];
        
        if (type === 'weekly' && metricsData.weekly) {
            if (Array.isArray(metricsData.weekly_labels) && Array.isArray(metricsData.weekly_values)) {
                labels = metricsData.weekly_labels;
                data = metricsData.weekly_values.map(Number);
            } else {
                labels = Object.keys(metricsData.weekly);
                data = Object.values(metricsData.weekly).map(Number);
            }
        } else if (type === 'monthly' && metricsData.monthly) {
            if (Array.isArray(metricsData.monthly_labels) && Array.isArray(metricsData.monthly_values)) {
                labels = metricsData.monthly_labels;
                data = metricsData.monthly_values.map(Number);
            } else {
                labels = Object.keys(metricsData.monthly);
                data = Object.values(metricsData.monthly).map(Number);
            }
        } else if (type === 'yearly' && metricsData.yearly) {
            if (Array.isArray(metricsData.yearly_labels) && Array.isArray(metricsData.yearly_values)) {
                labels = metricsData.yearly_labels;
                data = metricsData.yearly_values.map(Number);
            } else {
                labels = Object.keys(metricsData.yearly);
                data = Object.values(metricsData.yearly).map(Number);
            }
        }

        if (!salesOverviewChart) {
            salesOverviewChart = new Chart(document.getElementById('salesOverviewChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales',
                        data: data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        } else {
            salesOverviewChart.data.labels = labels;
            salesOverviewChart.data.datasets[0].data = data;
            salesOverviewChart.update();
        }
    }

    // Button event listeners
    document.getElementById('weeklyBtn').addEventListener('click', function() {
        currentOverviewType = 'weekly';
        document.getElementById('weeklyBtn').style.backgroundColor = '#3b82f6';
        document.getElementById('weeklyBtn').style.color = 'white';
        document.getElementById('monthlyBtn').style.backgroundColor = '';
        document.getElementById('monthlyBtn').style.color = '';
        document.getElementById('yearlyBtn').style.backgroundColor = '';
        document.getElementById('yearlyBtn').style.color = '';
        updateSalesOverview('weekly');
    });

    document.getElementById('monthlyBtn').addEventListener('click', function() {
        currentOverviewType = 'monthly';
        document.getElementById('monthlyBtn').style.backgroundColor = '#3b82f6';
        document.getElementById('monthlyBtn').style.color = 'white';
        document.getElementById('weeklyBtn').style.backgroundColor = '';
        document.getElementById('weeklyBtn').style.color = '';
        document.getElementById('yearlyBtn').style.backgroundColor = '';
        document.getElementById('yearlyBtn').style.color = '';
        updateSalesOverview('monthly');
    });

    document.getElementById('yearlyBtn').addEventListener('click', function() {
        currentOverviewType = 'yearly';
        document.getElementById('yearlyBtn').style.backgroundColor = '#3b82f6';
        document.getElementById('yearlyBtn').style.color = 'white';
        document.getElementById('weeklyBtn').style.backgroundColor = '';
        document.getElementById('weeklyBtn').style.color = '';
        document.getElementById('monthlyBtn').style.backgroundColor = '';
        document.getElementById('monthlyBtn').style.color = '';
        updateSalesOverview('yearly');
    });

    // Initial load
    fetchMetrics();
    
    // Refresh every 30 seconds
    setInterval(fetchMetrics, 30000);
});
