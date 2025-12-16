<?php
require_once __DIR__ . '/../_init.php';
header('Content-Type: application/json');
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '') { echo json_encode(['role' => null]); exit; }
try {
    $user = User::findByEmail($email);
    echo json_encode(['role' => $user ? $user->role : null]);
} catch (Exception $e) {
    echo json_encode(['role' => null]);
}
