<?php
require_once __DIR__ . '/../_init.php';

// Simple JSON endpoint to return role by email
header('Content-Type: application/json');

$email = '';
if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
}

if (empty($email)) {
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
    echo json_encode(['role' => null]);
}
