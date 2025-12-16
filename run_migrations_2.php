<?php
require_once '_config.php';
try {
    $connection = new PDO("mysql:host=127.0.0.1;port=3307;dbname=" . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $migrations = [
        'migrations/add_product_sku_image.sql'
    ];

    foreach ($migrations as $file) {
        if (!file_exists($file)) {
            echo "Migration not found: $file\n";
            continue;
        }
        $sql = file_get_contents($file);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($stmts as $stmt) {
            if (!$stmt) continue;
            try {
                $connection->exec($stmt);
                echo "Applied: " . substr($stmt,0,80) . "\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(),'1060')!==false) {
                    echo "Skipped (already exists): " . substr($stmt,0,80) . "\n";
                } else {
                    echo "Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "Done\n";
} catch (PDOException $e) {
    echo "DB connection error: " . $e->getMessage();
}
?>