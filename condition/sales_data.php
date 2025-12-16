<?php
require_once __DIR__ . '/../_init.php';
header('Content-Type: application/json');

try {
    $today = (float) Sales::getTodaySales();
    $month = (float) Sales::getMonthlySales();
    $year = (float) Sales::getYearlySales();
    $monthlyTrend = Sales::getMonthlyTotalsLastYear();

    echo json_encode([
        'today' => $today,
        'month' => $month,
        'year' => $year,
        'monthlyTrend' => $monthlyTrend
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch sales data']);
}
