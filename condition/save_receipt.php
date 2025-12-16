<?php
require_once __DIR__ . '/../_init.php';

// Save uploaded receipt PDF to receipts/ folder
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['receipt'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

$upload = $_FILES['receipt'];
if ($upload['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload error']);
    exit;
}

$receiptsDir = __DIR__ . '/../receipts';
if (!is_dir($receiptsDir)) {
    if (!mkdir($receiptsDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create receipts directory']);
        exit;
    }
}

$filename = sprintf('receipt_%s.pdf', $order_id ?: time());
$target = $receiptsDir . DIRECTORY_SEPARATOR . $filename;

$moved = false;
if (is_uploaded_file($upload['tmp_name'])) {
    $moved = @move_uploaded_file($upload['tmp_name'], $target);
}

if (!$moved) {
    $moved = @copy($upload['tmp_name'], $target);
}

if (!$moved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file to server', 'details' => error_get_last()]);
    exit;
}

// Build absolute URL for client convenience
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
// scriptDir is typically '/POS_SYSTEM/api' because this file is included via api/save_receipt.php
// we want the application root (one level up) so use dirname(scriptDir)
$appRoot = dirname($scriptDir);
$baseUrl = rtrim($protocol . '://' . $host . $appRoot, '/');
$fileUrl = $baseUrl . '/receipts/' . $filename;

echo json_encode(['success' => true, 'path' => 'receipts/' . $filename, 'url' => $fileUrl]);
