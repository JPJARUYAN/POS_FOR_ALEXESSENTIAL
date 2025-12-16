<?php
/**
 * Export Debug & Test
 * Run this to test if exports are working
 */
require_once '_init.php';

// Check if user is admin
if (!isset($_SESSION['user_id_admin'])) {
    die('You must be logged in as admin to test exports');
}

echo "<h1>Export Test Page</h1>";
echo "<p>If you see this, you are logged in as admin.</p>";

echo "<h2>Testing CSV Export:</h2>";
$testDate = date('Y-m-d');
echo "<form method='POST' action='api/export_expenses_csv.php'>";
echo "<input type='hidden' name='test' value='1'>";
echo "Start Date: <input type='date' name='start_date' value='" . date('Y-m-01') . "'><br>";
echo "End Date: <input type='date' name='end_date' value='" . $testDate . "'><br>";
echo "<button type='submit'>Test CSV Export</button>";
echo "</form>";

echo "<h2>Testing PDF Export:</h2>";
echo "<form method='POST' action='api/export_expenses_pdf.php'>";
echo "<input type='hidden' name='test' value='1'>";
echo "Start Date: <input type='date' name='start_date' value='" . date('Y-m-01') . "'><br>";
echo "End Date: <input type='date' name='end_date' value='" . $testDate . "'><br>";
echo "<button type='submit'>Test PDF Export</button>";
echo "</form>";

// Test database connection
echo "<h2>Database Check:</h2>";
try {
    global $connection;
    $stmt = $connection->query("SELECT COUNT(*) as total FROM orders");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✓ Database connected. Total orders: " . $result['total'] . "</p>";
} catch (Exception $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin_expenses.php'>← Back to Expense Report</a></p>";
?>