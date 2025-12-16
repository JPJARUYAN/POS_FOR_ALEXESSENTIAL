<?php
require_once '../_init.php';

// Only allow admin access
if (!isset($_SESSION['user_id_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

global $connection;

try {
    // Create backup directory if it doesn't exist
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $dbHost = DB_HOST;
    $dbUser = DB_USERNAME;
    $dbPass = DB_PASSWORD;
    $dbName = DB_DATABASE;
    
    // Generate SQL dump
    $backupFile = tempnam(sys_get_temp_dir(), 'pos_backup_');
    
    // Get all tables
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "-- POS System Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . $dbName . "\n\n";
    
    // Add database creation and selection
    $sql .= "CREATE DATABASE IF NOT EXISTS `" . $dbName . "`;\n";
    $sql .= "USE `" . $dbName . "`;\n\n";
    
    // Backup each table
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $stmt = $connection->query("SHOW CREATE TABLE `" . $table . "`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql .= "\n-- Table: " . $table . "\n";
        $sql .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
        $sql .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $connection->query("SELECT * FROM `" . $table . "`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $sql .= "INSERT INTO `" . $table . "` (";
            $columns = array_keys($rows[0]);
            $sql .= "`" . implode("`, `", $columns) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $val) {
                    if (is_null($val)) {
                        $rowValues[] = "NULL";
                    } elseif (is_numeric($val)) {
                        $rowValues[] = $val;
                    } else {
                        $rowValues[] = "'" . $connection->quote($val) . "'";
                    }
                }
                $values[] = "(" . implode(", ", $rowValues) . ")";
            }
            
            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    // Add restore instructions
    $sql .= "\n-- Backup completed\n";
    $sql .= "-- To restore this backup, run: mysql -u " . $dbUser . " -p " . $dbName . " < backup_file.sql\n";
    
    // Send as download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="pos_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $sql;
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Backup failed',
        'message' => $e->getMessage()
    ]);
}
