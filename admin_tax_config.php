<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

global $connection;

$categories = Category::all();
$products = Product::all();

// Handle category tax rate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_category_tax') {
        $category_id = intval($_POST['category_id']);
        $tax_rate = floatval($_POST['tax_rate']);

        if ($tax_rate < 0 || $tax_rate > 100) {
            flashMessage('tax_config', 'Tax rate must be between 0 and 100', 'error');
        } else {
            try {
                $stmt = $connection->prepare("UPDATE categories SET tax_rate = :tax_rate WHERE id = :id");
                $stmt->bindParam(':tax_rate', $tax_rate);
                $stmt->bindParam(':id', $category_id);
                $stmt->execute();
                flashMessage('tax_config', 'Category tax rate updated successfully', 'success');
                header('Location: admin_tax_config.php');
                exit;
            } catch (Exception $e) {
                flashMessage('tax_config', 'Error: ' . $e->getMessage(), 'error');
            }
        }
    } elseif ($_POST['action'] === 'update_product_tax') {
        $product_id = intval($_POST['product_id']);
        $tax_rate = isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '' ? floatval($_POST['tax_rate']) : null;
        $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;

        if ($tax_rate !== null && ($tax_rate < 0 || $tax_rate > 100)) {
            flashMessage('tax_config', 'Tax rate must be between 0 and 100', 'error');
        } else {
            try {
                $stmt = $connection->prepare("UPDATE products SET tax_rate = :tax_rate, is_taxable = :is_taxable WHERE id = :id");
                $stmt->bindParam(':tax_rate', $tax_rate);
                $stmt->bindParam(':is_taxable', $is_taxable);
                $stmt->bindParam(':id', $product_id);
                $stmt->execute();
                flashMessage('tax_config', 'Product tax settings updated successfully', 'success');
                header('Location: admin_tax_config.php');
                exit;
            } catch (Exception $e) {
                flashMessage('tax_config', 'Error: ' . $e->getMessage(), 'error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Tax Configuration</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">
    <link rel="stylesheet" type="text/css" href="./css/datatable.css">

    <style>
        .tax-config-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin: 16px;
        }

        .tax-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }

        .tax-section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary);
        }

        .tax-item {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 12px;
        }

        .tax-item-name {
            font-weight: 500;
            color: var(--text-body);
        }

        .tax-item-rate {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tax-item-rate input {
            width: 80px;
            padding: 6px 8px;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 4px;
            color: var(--input-text);
            text-align: center;
            font-weight: 600;
        }

        .tax-item-rate input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .tax-item-btn {
            display: flex;
            gap: 4px;
        }

        .btn-tax-save {
            padding: 6px 12px;
            background: var(--primary);
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
        }

        .btn-tax-save:hover {
            background: var(--primary-darker);
        }

        .tax-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }

        .tax-badge.category {
            background: #06b6d4;
        }

        .tax-badge.product {
            background: #10b981;
        }

        .tax-badge.none {
            background: #94a3b8;
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .product-table th {
            background: var(--header-bg);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-body);
        }

        .product-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-body);
        }

        .product-table tr:hover {
            background: var(--input-bg);
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--primary);
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 0.9em;
            color: var(--text-muted);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-label input {
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .tax-config-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">💰 Tax Configuration</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Set default tax rates for categories and override per product if needed</p>
                </div>

                <?php displayFlashMessage('tax_config') ?>

                <div class="info-box">
                    <strong>How it works:</strong> Each product inherits the tax rate from its category by default. You can override with a product-specific rate if needed. Products marked as non-taxable will not have taxes applied at checkout.
                </div>

                <div class="tax-config-container">

                    <!-- Categories Tax Rates -->
                    <div class="tax-section">
                        <div class="tax-section-title">📁 Category Tax Rates</div>
                        
                        <?php foreach ($categories as $cat): ?>
                            <form method="POST" class="tax-item">
                                <input type="hidden" name="action" value="update_category_tax">
                                <input type="hidden" name="category_id" value="<?= $cat->id ?>">
                                
                                <div class="tax-item-name"><?= htmlspecialchars($cat->name) ?></div>
                                
                                <div class="tax-item-rate">
                                    <input type="number" name="tax_rate" value="<?= number_format($cat->tax_rate, 2) ?>" 
                                        min="0" max="100" step="0.01" required>
                                    <span style="color: var(--text-muted); font-size: 0.9em;">%</span>
                                </div>
                                
                                <div class="tax-item-btn">
                                    <button type="submit" class="btn-tax-save">Update</button>
                                </div>
                            </form>
                        <?php endforeach; ?>

                        <?php if (empty($categories)): ?>
                            <div class="muted" style="text-align: center; padding: 20px;">
                                No categories yet. Create one in <a href="admin_home.php">Inventory</a> first.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Products Tax Rates -->
                    <div class="tax-section">
                        <div class="tax-section-title">📦 Product Tax Overrides</div>
                        
                        <div class="muted" style="font-size: 0.85em; margin-bottom: 12px;">
                            Leave tax rate empty to use the category default.
                        </div>

                        <div style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($products as $prod): 
                                $catName = $prod->category_id ? ($categories[array_search($prod->category_id, array_column($categories, 'id'))]->name ?? 'Unknown') : 'Unknown';
                                $effectiveTaxRate = $prod->getEffectiveTaxRate();
                                $isOverride = $prod->tax_rate !== null;
                            ?>
                                <form method="POST" class="tax-item">
                                    <input type="hidden" name="action" value="update_product_tax">
                                    <input type="hidden" name="product_id" value="<?= $prod->id ?>">
                                    
                                    <div>
                                        <div class="tax-item-name"><?= htmlspecialchars($prod->name) ?></div>
                                        <div class="muted" style="font-size: 0.8em; margin-top: 4px;">
                                            Category: <?= htmlspecialchars($catName) ?>
                                            <span class="tax-badge category" style="margin-left: 8px;">
                                                <?= number_format(Category::find($prod->category_id)->tax_rate ?? 12, 2) ?>%
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="tax-item-rate">
                                        <input type="number" name="tax_rate" 
                                            value="<?= $isOverride ? number_format($prod->tax_rate, 2) : '' ?>" 
                                            min="0" max="100" step="0.01" placeholder="Override">
                                        <span style="color: var(--text-muted); font-size: 0.9em;">%</span>
                                    </div>
                                    
                                    <div class="tax-item-btn">
                                        <label class="checkbox-label" title="Uncheck to make this product tax-exempt">
                                            <input type="checkbox" name="is_taxable" <?= $prod->is_taxable ? 'checked' : '' ?>>
                                            <span style="font-size: 0.8em;">Taxable</span>
                                        </label>
                                    </div>

                                    <div class="tax-item-btn">
                                        <button type="submit" class="btn-tax-save">Save</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>

                            <?php if (empty($products)): ?>
                                <div class="muted" style="text-align: center; padding: 20px;">
                                    No products yet. Add some in <a href="admin_home.php">Inventory</a> first.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Tax Summary Report -->
                <div class="card" style="margin: 24px 16px;">
                    <div class="card-header">📊 Tax Rate Summary</div>
                    <div class="card-body">
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Category Rate</th>
                                    <th>Product Override</th>
                                    <th>Effective Rate</th>
                                    <th>Taxable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $prod):
                                    $catName = $prod->category_id ? (Category::find($prod->category_id)->name ?? 'Unknown') : 'Unknown';
                                    $catRate = $prod->category_id ? (Category::find($prod->category_id)->tax_rate ?? 12) : 12;
                                    $effectiveRate = $prod->getEffectiveTaxRate();
                                    $isOverride = $prod->tax_rate !== null;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($prod->name) ?></strong></td>
                                        <td><?= htmlspecialchars($catName) ?></td>
                                        <td>
                                            <span class="tax-badge category">
                                                <?= number_format($catRate, 2) ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isOverride): ?>
                                                <span class="tax-badge product">
                                                    <?= number_format($prod->tax_rate, 2) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="tax-badge none">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary);">
                                                <?php if ($prod->is_taxable): ?>
                                                    <?= number_format($effectiveRate, 2) ?>%
                                                <?php else: ?>
                                                    Tax-Exempt
                                                <?php endif; ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($prod->is_taxable): ?>
                                                <span style="color: #10b981; font-weight: 600;">✓ Yes</span>
                                            <?php else: ?>
                                                <span style="color: #ef4444; font-weight: 600;">✗ No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>

</html>
