<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
require_once __DIR__ . '/../models/Product.php';

Guard::adminOnly();

$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_home.php');
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : null;
$size_quantities = isset($_POST['size_quantities']) ? $_POST['size_quantities'] : null;

if (!$product_id) {
    flashMessage('add_stock', 'Invalid product ID', 'error');
    header('Location: ../admin_home.php');
    exit;
}

// Find product
$product = Product::find($product_id);

if (!$product) {
    flashMessage('add_stock', 'Product not found', 'error');
    header('Location: ../admin_home.php');
    exit;
}

// Handle size-based stock addition
if ($size_quantities && is_array($size_quantities) && !empty($product->size)) {
    $addedSizes = [];
    $totalAdded = 0;
    
    foreach ($size_quantities as $size => $qty) {
        $qty = intval($qty);
        if ($qty > 0) {
            $product->addSizeStock($size, $qty);
            $addedSizes[] = $size . ': +' . $qty;
            $totalAdded += $qty;
        }
    }
    
    if ($totalAdded > 0) {
        $message = "Stock updated successfully! Added: " . implode(', ', $addedSizes) . " (Total: +" . $totalAdded . ")";
        flashMessage('add_stock', $message, 'success');
    } else {
        flashMessage('add_stock', 'Please enter at least one quantity to add', 'error');
    }
} else {
    // Fallback to old method (add to total quantity)
    if (!$quantity || $quantity <= 0) {
        flashMessage('add_stock', 'Invalid quantity', 'error');
        header('Location: ../admin_home.php');
        exit;
    }
    
    $product->quantity += $quantity;
    $product->update();
    flashMessage('add_stock', "Stock updated successfully! New quantity: " . $product->quantity, 'success');
}

header('Location: ../admin_home.php');
exit;
?>