<?php
// Guard
require_once '_guards.php';
Guard::adminOnly();

// Get all products for dropdown
$products = Product::all();
require_once 'models/Supplier.php';
// Basic stock add page (uses dark mock)
$currentUser = User::getAuthenticatedUser(ROLE_ADMIN);

// Get the product if product_id is provided
$product = null;
if (isset($_GET['product_id'])) {
    $product = Product::find(intval($_GET['product_id']));
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Add Stock</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
</head>

<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">📦 Add Stock</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Adjust product stock levels and manage inventory quantities.</p>
                </div>

                <div class="card">
                    <div class="card-content">
                        <?php if ($product): ?>
                            <div style="margin-bottom: 20px;">
                                <h3 style="margin-bottom: 8px;"><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="muted">Category: <?= htmlspecialchars($product->category->name, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($product->supplier_id)): ?>
                                    <?php $sup = Supplier::find($product->supplier_id); ?>
                                    <p class="muted">Supplier: <?= htmlspecialchars($sup?->name ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <p class="muted">Current Total Stock: <?= number_format($product->quantity) ?></p>
                            </div>
                        <?php else: ?>
                            <p>Use this page to adjust product stock levels.</p>
                        <?php endif; ?>

                        <?php displayFlashMessage('add_stock') ?>

                        <?php if ($product && !empty($product->size)): ?>
                            <?php
                            // Get available sizes
                            $sizes = array_map('trim', explode(',', $product->size));
                            $sizeStocks = $product->getSizeStocks();
                            $sizeStockMap = [];
                            foreach ($sizeStocks as $item) {
                                $sizeStockMap[$item['size']] = $item['quantity'];
                            }
                            
                            // Sort sizes intelligently
                            usort($sizes, function($a, $b) {
                                $sizeA = trim($a);
                                $sizeB = trim($b);
                                
                                $isNumericA = is_numeric($sizeA);
                                $isNumericB = is_numeric($sizeB);
                                
                                if ($isNumericA && $isNumericB) {
                                    return (int)$sizeA - (int)$sizeB;
                                } elseif ($isNumericA) {
                                    return -1;
                                } elseif ($isNumericB) {
                                    return 1;
                                } else {
                                    $sizeOrder = [
                                        'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 
                                        'XL' => 5, 'XXL' => 6, 'XXXL' => 7,
                                        'XXS' => 0.5
                                    ];
                                    $orderA = $sizeOrder[strtoupper($sizeA)] ?? 999;
                                    $orderB = $sizeOrder[strtoupper($sizeB)] ?? 999;
                                    if ($orderA !== 999 || $orderB !== 999) {
                                        return $orderA <=> $orderB;
                                    }
                                    return strcasecmp($sizeA, $sizeB);
                                }
                            });
                            ?>
                            <form method="POST" action="api/stock_controller.php?action=add">
                                <input type="hidden" name="product_id" value="<?= $product->id ?>" />

                                <div class="form-control">
                                    <label style="margin-bottom: 12px; font-weight: 600;">Add Stock per Size</label>
                                    
                                    <?php foreach ($sizes as $size): ?>
                                        <?php if (!empty($size)): ?>
                                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 12px; background: var(--input-bg, #1e293b); border-radius: 6px;">
                                                <label style="min-width: 60px; font-weight: 500;"><?= htmlspecialchars($size) ?>:</label>
                                                <span class="muted" style="min-width: 80px;">Current: <?= number_format($sizeStockMap[$size] ?? 0) ?></span>
                                                <input type="number" 
                                                       name="size_quantities[<?= htmlspecialchars($size, ENT_QUOTES) ?>]" 
                                                       step="1" 
                                                       min="0" 
                                                       value="0" 
                                                       placeholder="Add quantity"
                                                       style="flex: 1; padding: 8px 12px; background: var(--card-bg, #0f172a); border: 1px solid var(--border-color, #334155); border-radius: 4px; color: var(--text-body, #e2e8f0);" />
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-16">
                                    <button class="btn btn-primary w-full" type="submit">Add Stock</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="api/stock_controller.php?action=add">
                                <input type="hidden" name="product_id"
                                    value="<?= htmlspecialchars($_GET['product_id'] ?? '') ?>" />

                                <div class="form-control">
                                    <label>Quantity to Add</label>
                                    <input type="number" name="quantity" step="1" min="1" required autofocus />
                                </div>

                                <div class="mt-16">
                                    <button class="btn btn-primary w-full" type="submit">Add Stock</button>
                                </div>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </main>
    </div>

</body>

</html>