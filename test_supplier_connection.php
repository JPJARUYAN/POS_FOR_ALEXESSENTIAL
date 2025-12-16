<?php
require_once '_init.php';
require_once 'models/Supplier.php';

header('Content-Type: application/json');

$output = [
    'php_version' => phpversion(),
    'database' => 'Test connection',
    'suppliers_table' => 'Check if exists'
];

try {
    // Test connection
    $stmt = $connection->prepare("SELECT 1");
    $stmt->execute();
    $output['database'] = 'Connected';
} catch (Exception $e) {
    $output['database'] = 'Failed: ' . $e->getMessage();
}

try {
    // Ensure table exists
    Supplier::ensureSuppliersTable();
    $output['suppliers_table'] = 'OK (auto-created if needed)';
    
    // Try to list suppliers
    $suppliers = Supplier::all();
    $output['suppliers_count'] = count($suppliers);
    
    // Test add
    $testId = Supplier::add('Test Supplier ' . time());
    $output['test_add'] = 'Success (ID: ' . $testId . ')';
    
    // Delete test
    $testSup = Supplier::find($testId);
    if ($testSup) {
        $testSup->delete();
        $output['test_delete'] = 'Success';
    }
} catch (Exception $e) {
    $output['suppliers_test'] = 'Error: ' . $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT);
?>
