<?php
// Simple reset script: creates a backup SQL dump (for the selected tables)
// then truncates product/category/sales related tables so you can re-add products.

require_once __DIR__ . '/../_init.php';

$tables = [
    'order_items',
    'orders',
    'product_sizes',
    'products',
    'suppliers',
    'categories',
];

$timestamp = date('Ymd_His');
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$backupFile = $backupDir . "/backup_{$timestamp}.sql";

file_put_contents($backupFile, "-- Backup created by scripts/reset_db.php on " . date('c') . "\n\n");

echo "Backing up tables to: $backupFile\n";

foreach ($tables as $table) {
    try {
        // Get CREATE TABLE
        $stmt = $connection->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['Create Table'])) {
            file_put_contents($backupFile, "DROP TABLE IF EXISTS `{$table}`;\n", FILE_APPEND);
            file_put_contents($backupFile, $row['Create Table'] . ";\n\n", FILE_APPEND);
        }

        // Dump rows as INSERTs
        $stmt = $connection->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            foreach ($rows as $r) {
                $cols = array_map(function($c){ return "`".str_replace('`','``',$c)."`"; }, array_keys($r));
                $vals = array_map(function($v) use ($connection){
                    if ($v === null) return 'NULL';
                    return $connection->quote($v);
                }, array_values($r));
                $sql = "INSERT INTO `{$table}` (".implode(',', $cols).") VALUES (".implode(',', $vals).");\n";
                file_put_contents($backupFile, $sql, FILE_APPEND);
            }
            file_put_contents($backupFile, "\n", FILE_APPEND);
        }
    } catch (PDOException $e) {
        // Table might not exist - skip
        file_put_contents($backupFile, "-- Skipped {$table}: {$e->getMessage()}\n\n", FILE_APPEND);
    }
}

echo "Backup finished. Now truncating selected tables...\n";

try {
    $connection->beginTransaction();
    $connection->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        try {
            $connection->exec("TRUNCATE TABLE `{$table}`");
            echo "Truncated: {$table}\n";
        } catch (PDOException $e) {
            echo "Could not truncate {$table}: {$e->getMessage()}\n";
        }
    }
    $connection->exec('SET FOREIGN_KEY_CHECKS=1');
    $connection->commit();
    echo "Reset complete. You can now add your actual products and categories.\n";
    echo "Backup file: $backupFile\n";
} catch (PDOException $e) {
    $connection->rollBack();
    echo "Error during reset: " . $e->getMessage() . "\n";
}

?>
