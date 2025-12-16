<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
require_once __DIR__ . '/../models/Product.php';

Guard::adminOnly();

$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../admin_add_item.php');
        exit;
    }

    $name = post('name');
    $category_id = post('category_id');
    $quantity = post('quantity');
    $cost = post('cost') ?: 0;
    $profit_amount = post('profit_amount');
    $size = post('size') ?: '';
    $sku = post('sku') ?: null;
    $tax_rate = post('tax_rate') ?: 12;
    $price = post('price'); // Price already calculated with tax from frontend
    $supplier_id = post('supplier_id') ?: null;

    // validate required fields: name, category, quantity, size, cost, profit_amount
    if (!$name || !$category_id || $quantity === '' || $size === '' || $cost === '' || $profit_amount === null || $profit_amount === '') {
        $jsonAccept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }
        flashMessage('add_product', 'All required fields must be filled (including size, cost and profit amount)', 'error');
        header('Location: ../admin_add_item.php');
        exit;
    }

    // compute price server-side if not provided: (cost + profit) + tax
    if (!is_numeric($cost) || !is_numeric($profit_amount)) {
        $jsonAccept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cost and Profit amount must be numeric']);
            exit;
        }
        flashMessage('add_product', 'Cost and Profit amount must be numeric', 'error');
        header('Location: ../admin_add_item.php');
        exit;
    }

    // Recalculate price server-side for security
    $taxRate = floatval($tax_rate);
    $base = floatval($cost) + floatval($profit_amount);
    $finalPrice = $base * (1 + ($taxRate / 100.0));

    $jsonAccept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    try {
        $productId = Product::add($name, intval($category_id), intval($quantity), floatval($finalPrice), floatval($cost), $size, $sku, null, $supplier_id ? intval($supplier_id) : null);
        
        // Initialize size stocks for the newly created product
        $newProduct = Product::find($productId);
        if ($newProduct && !empty($size)) {
            // Check if size_quantities JSON is provided (per-size allocation)
            $sizeQuantitiesJson = post('size_quantities');
            if ($sizeQuantitiesJson) {
                try {
                    $sizeQuantities = json_decode($sizeQuantitiesJson, true);
                    if (is_array($sizeQuantities) && count($sizeQuantities) > 0) {
                        // Use per-size quantities provided by user
                        foreach ($sizeQuantities as $item) {
                            if (isset($item['size']) && isset($item['quantity'])) {
                                $newProduct->addSizeStock($item['size'], intval($item['quantity']));
                            }
                        }
                    } else {
                        // Fallback to even distribution
                        $newProduct->initializeSizeStocks(intval($quantity));
                    }
                } catch (Exception $e) {
                    // If JSON parsing fails, fallback to even distribution
                    $newProduct->initializeSizeStocks(intval($quantity));
                }
            } else {
                // No per-size allocation provided, distribute evenly
                $newProduct->initializeSizeStocks(intval($quantity));
            }
        }
        
        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);
            exit;
        }
        flashMessage('add_product', 'Product added successfully!', 'success');
    } catch (Exception $e) {
        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        flashMessage('add_product', 'Error adding product: ' . $e->getMessage(), 'error');
    }
    header('Location: ../admin_add_item.php');
    exit;
}

if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../admin_home.php');
        exit;
    }

    $id = post('id');
    $name = post('name');
    $category_id = post('category_id');
    $quantity = post('quantity');
    $cost = post('cost') ?: 0;
    $size = post('size') ?: '';
    $profit_percent = post('profit_percent');

    if (!$id || !$name || !$category_id || !$quantity || $size === '' || $cost === '' || $profit_percent === null || $profit_percent === '') {
        flashMessage('edit_product', 'All required fields must be filled (including size, cost and profit %)', 'error');
        header('Location: ../admin_update_item.php?id=' . $id);
        exit;
    }

    if (!is_numeric($cost) || !is_numeric($profit_percent)) {
        flashMessage('edit_product', 'Cost and Profit % must be numeric', 'error');
        header('Location: ../admin_update_item.php?id=' . $id);
        exit;
    }

    // compute price server-side (cost + profit) + VAT
    $TAX_RATE = 12; // percent
    $base = floatval($cost) + (floatval($cost) * floatval($profit_percent) / 100.0);
    $price = $base * (1 + ($TAX_RATE / 100.0));

    $jsonAccept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    try {
        $product = Product::find(intval($id));
        if (!$product) {
            if ($jsonAccept) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            flashMessage('edit_product', 'Product not found', 'error');
            header('Location: ../admin_home.php');
            exit;
        }

        $product->name = $name;
        $product->category_id = intval($category_id);
        $product->quantity = intval($quantity);
        $product->price = floatval($price);
        $product->cost = floatval($cost);
        $product->size = $size;
        $product->supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : $product->supplier_id;
        // note: image and sku are no longer managed via add-form; keep existing values unless admin UI reinstates them
        $product->update();

        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product updated']);
            exit;
        }

        flashMessage('edit_product', 'Product updated successfully!', 'success');
    } catch (Exception $e) {
        if ($jsonAccept) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        flashMessage('edit_product', 'Error updating product: ' . $e->getMessage(), 'error');
    }
    header('Location: ../admin_update_item.php?id=' . $id);
    exit;
}

if ($action === 'delete') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$id) {
        flashMessage('delete_product', 'Product ID required', 'error');
        header('Location: ../admin_home.php');
        exit;
    }

    try {
        $product = Product::find($id);
        if (!$product) {
            flashMessage('delete_product', 'Product not found', 'error');
            header('Location: ../admin_home.php');
            exit;
        }

        $product->delete();
        flashMessage('delete_product', 'Product deleted successfully!', 'success');
    } catch (Exception $e) {
        flashMessage('delete_product', 'Error deleting product: ' . $e->getMessage(), 'error');
    }
    header('Location: ../admin_home.php');
    exit;
}

header('Location: ../admin_home.php');
exit;
?>