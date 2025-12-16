<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
Guard::adminOnly();

header('Content-Type: application/json');

global $connection;

// Get cashier filter from query parameter
$cashierId = isset($_GET['cashier']) && $_GET['cashier'] !== '' ? intval($_GET['cashier']) : null;
$cashierWhere = $cashierId ? ' AND o.cashier_id = :cashier_id' : '';

// Last 7 days data
$last7_start = date('Y-m-d', strtotime('-6 days'));
$sql = "SELECT DATE(o.created_at) as d, SUM(oi.quantity*oi.price) as total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= :start{$cashierWhere}
        GROUP BY DATE(o.created_at)
        ORDER BY d ASC";
$stmt = $connection->prepare($sql);
$params = [':start' => $last7_start];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$last7_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$last7 = [];
$dt = new DateTime($last7_start);
$today = new DateTime();
while ($dt <= $today) {
    $key = $dt->format('Y-m-d');
    $label = $dt->format('D');
    $last7[$key] = ['label' => $label, 'total' => 0];
    $dt->modify('+1 day');
}
foreach ($last7_rows as $r) {
    if (isset($last7[$r['d']])) {
        $last7[$r['d']]['total'] = floatval($r['total'] ?? 0);
    }
}

// Hourly data for today
$today_str = date('Y-m-d');
$sql = "SELECT HOUR(o.created_at) as hr, SUM(oi.quantity*oi.price) as total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) = :today{$cashierWhere}
        GROUP BY HOUR(o.created_at)";
$stmt = $connection->prepare($sql);
$params = [':today' => $today_str];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$hour_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hours_today = array_fill(0, 24, 0);
foreach ($hour_rows as $h) {
    $hours_today[intval($h['hr'])] = floatval($h['total'] ?? 0);
}

// Top products all time
$sql = "SELECT p.name, SUM(oi.quantity*oi.price) as total
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE 1=1{$cashierWhere}
        GROUP BY p.id, p.name
        ORDER BY total DESC
        LIMIT 5";
$stmt = $connection->prepare($sql);
$params = [];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales overview starting at a fixed date
$overview_start = '2025-10-28';
$overview_dt = new DateTime($overview_start);
$today_dt = new DateTime();

// Weekly sales (from overview start) grouped by ISO year-week
$sql = "SELECT YEAR(o.created_at) AS yy, WEEK(o.created_at,1) AS wk, SUM(oi.quantity*oi.price) AS total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= :start{$cashierWhere}
        GROUP BY yy, wk
        ORDER BY yy ASC, wk ASC";
$stmt = $connection->prepare($sql);
$params = [':start' => $overview_start];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$weekly_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$weekly = [];
$period = clone $overview_dt;
// build weekly keys from start to today
while ($period <= $today_dt) {
    $yy = $period->format('o');
    $wk = $period->format('W');
    $key = $yy . '-W' . $wk;
    $weekly[$key] = 0;
    $period->modify('+1 week');
}
foreach ($weekly_rows as $r) {
    $key = $r['yy'] . '-W' . str_pad($r['wk'], 2, '0', STR_PAD_LEFT);
    if (array_key_exists($key, $weekly))
        $weekly[$key] = floatval($r['total'] ?? 0);
}

// Monthly sales (from overview start)
$sql = "SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month, SUM(oi.quantity*oi.price) as total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= :start{$cashierWhere}
        GROUP BY month
        ORDER BY month ASC";
$stmt = $connection->prepare($sql);
$params = [':start' => $overview_start];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$monthly_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthly = [];
$period = clone $overview_dt;
$period->modify('first day of this month');
while ($period <= $today_dt) {
    $key = $period->format('Y-m');
    $label = $period->format('M Y');
    $monthly[$key] = 0;
    $period->modify('+1 month');
}
foreach ($monthly_rows as $r) {
    $key = $r['month'];
    if (array_key_exists($key, $monthly))
        $monthly[$key] = floatval($r['total'] ?? 0);
}

// Yearly sales (from overview start)
$sql = "SELECT YEAR(o.created_at) as year, SUM(oi.quantity*oi.price) as total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= :start{$cashierWhere}
        GROUP BY year
        ORDER BY year ASC";
$stmt = $connection->prepare($sql);
$params = [':start' => $overview_start];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$yearly_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$yearly = [];
$start_year = intval($overview_dt->format('Y'));
$end_year = intval($today_dt->format('Y'));
for ($y = $start_year; $y <= $end_year; $y++) {
    $yearly[(string) $y] = 0;
}
foreach ($yearly_rows as $r) {
    $k = (string) $r['year'];
    if (array_key_exists($k, $yearly))
        $yearly[$k] = floatval($r['total'] ?? 0);
}

// Build friendly label arrays (weekly: week start date; monthly: 'Mon YYYY'; yearly: 'YYYY')
$weekly_labels = [];
$weekly_values = [];
foreach ($weekly as $k => $v) {
    $parts = explode('-W', $k);
    if (count($parts) === 2) {
        $y = intval($parts[0]);
        $w = intval($parts[1]);
        $dt = new DateTime();
        $dt->setISODate($y, $w);
        $start = clone $dt;
        $end = clone $dt;
        $end->modify('+6 days');
        // Format as range: e.g. "Oct 28 - Nov 3"
        $weekly_labels[] = $start->format('M j') . ' - ' . $end->format('M j');
    } else {
        $weekly_labels[] = $k;
    }
    $weekly_values[] = $v;
}

$monthly_labels = [];
$monthly_values = [];
foreach ($monthly as $k => $v) {
    $ts = strtotime($k . '-01');
    if ($ts !== false)
        $monthly_labels[] = date('M Y', $ts);
    else
        $monthly_labels[] = $k;
    $monthly_values[] = $v;
}

$yearly_labels = [];
$yearly_values = [];
foreach ($yearly as $k => $v) {
    $yearly_labels[] = (string) $k;
    $yearly_values[] = $v;
}

// Recent transactions with full details
$sql = "SELECT o.id, 
               SUM(oi.quantity*oi.price) as total, 
               SUM(oi.quantity) as total_quantity,
               COUNT(oi.id) as item_count,
               GROUP_CONCAT(
                   CONCAT(
                       p.name, 
                       IF(oi.size IS NOT NULL AND oi.size != '', CONCAT(' (', oi.size, ')'), ''), 
                       ' × ', oi.quantity
                   ) 
                   ORDER BY oi.id 
                   SEPARATOR '; '
               ) as products,
               o.created_at
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE 1=1{$cashierWhere}
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 6";
$stmt = $connection->prepare($sql);
$params = [];
if ($cashierId)
    $params[':cashier_id'] = $cashierId;
$stmt->execute($params);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [
    'last_7_days' => $last7,
    'hours_today' => $hours_today,
    'top_products' => $top_products,
    'weekly' => $weekly,
    'weekly_labels' => $weekly_labels,
    'weekly_values' => $weekly_values,
    'monthly' => $monthly,
    'monthly_labels' => $monthly_labels,
    'monthly_values' => $monthly_values,
    'yearly' => $yearly,
    'yearly_labels' => $yearly_labels,
    'yearly_values' => $yearly_values,
    'recent' => $recent
];

echo json_encode($out);