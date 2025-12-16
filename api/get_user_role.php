<?php
header('Content-Type: application/json');

require_once '../_init.php';

$email = isset($_GET['email']) ? $_GET['email'] : '';

if (!$email) {
    echo json_encode(['role' => null]);
    exit;
}

try {
    $user = User::findByEmail($email);
    if ($user) {
        echo json_encode(['role' => $user->role]);
    } else {
        echo json_encode(['role' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
