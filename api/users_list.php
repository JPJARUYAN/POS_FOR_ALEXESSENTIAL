<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
Guard::adminOnly();

header('Content-Type: application/json');
global $connection;

$stmt = $connection->prepare("SELECT id, name FROM users WHERE role = 'CASHIER'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);

?>
