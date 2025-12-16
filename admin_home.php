<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

global $connection;

$products = Product::all();
$categories = Category::all();
$suppliers = Supplier::all();

$totalProducts = count($products);
$totalStock = 0;
$lowStockCount = 0;
$totalInventoryValue = 0;

// Track products with low stock (total < 10 OR any size < 10)
$lowStockProductIds = [];

foreach ($products as $product) {
    $totalStock += $product->quantity;
    $totalInventoryValue += ($product->quantity * $product->cost);
    
    // Count if total quantity < 10
    if ($product->quantity < 10) {
        $lowStockProductIds[$product->id] = true;
    }
}

// Also count products with any size < 10
$sizeStmt = $connection->prepare("
    SELECT DISTINCT product_id FROM product_sizes WHERE quantity < 10
");
$sizeStmt->execute();
$lowStockSizes = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($lowStockSizes as $row) {
    $lowStockProductIds[$row['product_id']] = true;
}

$lowStockCount = count($lowStockProductIds);

$defaultTaxRate = 12;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Inventory</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">

    <!-- Datatables Library -->
    <link rel="stylesheet" type="text/css" href="./css/datatable.css">
    <script src="./js/datatable.js"></script>
    <script src="./js/main.js"></script>

    <style>
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 40px 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25em;
            font-weight: 700;
            color: var(--text-body);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 28px;
            line-height: 1;
            padding: 0;
        }

        .modal-close:hover {
            color: var(--text-body);
        }

        .modal-body {
            padding: 24px;
        }

        .form-section {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-section-title {
            font-size: 1em;
            font-weight: 600;
            color: var(--text-body);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row:last-child {
            margin-bottom: 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.85em;
            font-weight: 500;
            color: var(--text-body);
            margin-bottom: 6px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 6px;
            color: var(--input-text);
            font-size: 0.9em;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .form-group .helper {
            font-size: 0.75em;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .quick-sizes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .quick-size-btn {
            padding: 5px 10px;
            background: var(--btn-bg);
            border: 1px solid var(--btn-border);
            border-radius: 4px;
            color: var(--btn-text);
            cursor: pointer;
            font-size: 0.75em;
        }

        .quick-size-btn:hover {
            background: var(--btn-hover-bg);
            border-color: var(--primary);
        }

        .size-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .size-tag {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
        }

        .price-breakdown {
            background: var(--header-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.9em;
        }

        .price-row:not(:last-child) {
            border-bottom: 1px dashed var(--border-color);
        }

        .price-row.total {
            font-weight: 700;
            font-size: 1.1em;
            color: var(--primary);
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid var(--border-color);
        }

        .price-row .label {
            color: var(--text-muted);
        }


        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 12px 24px;
            background: var(--btn-bg);
            border: 1px solid var(--btn-border);
            border-radius: 8px;
            color: var(--btn-text);
            cursor: pointer;
            font-size: 0.95em;
        }

        .btn-submit {
            padding: 12px 32px;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
        }

        .btn-submit:hover {
            background: var(--primary-darker);
        }

        /* Metric Details Modal Styles */
        .detail-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .detail-summary-item {
            background: var(--input-bg, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 8px;
            padding: 16px;
        }

        .detail-summary-label {
            font-size: 0.85em;
            color: var(--text-muted, #94a3b8);
            margin-bottom: 8px;
        }

        .detail-summary-value {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--primary, #3b82f6);
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .detail-table th {
            background: var(--header-bg, #1e293b);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-body, #e2e8f0);
            border-bottom: 2px solid var(--border-color, #334155);
        }

        .detail-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color, #334155);
            color: var(--text-body, #e2e8f0);
        }

        .detail-table tr:hover {
            background: var(--input-bg, #1e293b);
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--text-muted, #94a3b8);
        }
    </style>
</head>

<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper" style="max-width: 100%; width:100%; padding:0;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div>
                            <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">📦 Inventory</h1>
                            <p style="margin: 0; opacity: 0.9; font-size: 1em;">Manage products, stock levels and categories in one place</p>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <button type="button" id="openCategoryModal" class="btn" aria-controls="categoryModal" onclick="openCategoryModalHandler()" style="padding: 12px 20px; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3); color: white; font-weight: 600; backdrop-filter: blur(4px);">📁 Categories</button>
                            <button type="button" id="openAddModal" class="btn btn-primary" style="padding: 12px 20px; background: white; color: #667eea; border: none; font-weight: 700;">+ Add New Product</button>
                        </div>
                    </div>
                </div>

                <!-- Overview metrics -->
                <div class="grid gap-16"
                    style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin:16px 0;">
                    <div class="card metric" onclick="showMetricDetails('total_products')" style="cursor: pointer; transition: all 0.3s ease;">
                        <div class="metric-title">Total Products</div>
                        <div class="metric-value"><?= number_format($totalProducts) ?></div>
                    </div>
                    <div class="card metric" onclick="showMetricDetails('total_stock')" style="cursor: pointer; transition: all 0.3s ease;">
                        <div class="metric-title">Total Stock On-hand</div>
                        <div class="metric-value"><?= number_format($totalStock) ?></div>
                    </div>
                    <div class="card metric" onclick="showMetricDetails('low_stock')" style="cursor: pointer; transition: all 0.3s ease;">
                        <div class="metric-title">Low Stock Items (&lt; 10)</div>
                        <div class="metric-value"><?= number_format($lowStockCount) ?></div>
                    </div>
                    <div class="card metric" onclick="showMetricDetails('inventory_value')" style="cursor: pointer; transition: all 0.3s ease;">
                        <div class="metric-title">Inventory Cost Value</div>
                        <div class="metric-value">₱ <?= number_format($totalInventoryValue, 2) ?></div>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-body">
                        <div
                            style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div class="subtitle" style="margin:0;">Product List</div>
                        </div>
                        <?php displayFlashMessage('delete_product') ?>
                        <?php displayFlashMessage('add_stock') ?>

                        <div class="table-responsive">
                            <table id="productsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Stocks</th>
                                        <th>Cost</th>
                                        <th>Price</th>
                                        <th>Size</th>
                                        <th>Unit Profit</th>
                                        <th>Total Profit</th>
                                        <th>Total Value</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($product->category->name, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= number_format($product->quantity) ?></td>
                                            <td>₱ <?= number_format($product->cost, 2) ?></td>
                                            <td>₱ <?= number_format($product->price, 2) ?></td>
                                            <td><?= $product->getFormattedSizeStocks() ?></td>
                                            <?php
                                                // Calculate profit metrics
                                                $unitProfit = $product->price - $product->cost;
                                                $totalProfit = $unitProfit * $product->quantity;
                                                $profitClass = $unitProfit < 0 ? 'color: #ef4444;' : 'color: #22c55e;';
                                                $totalProfitClass = $totalProfit < 0 ? 'color: #ef4444;' : 'color: #22c55e;';
                                            ?>
                                            <td style="<?= $profitClass ?>; font-weight: 500;">₱ <?= number_format($unitProfit, 2) ?></td>
                                            <td style="<?= $totalProfitClass ?>; font-weight: 500;">₱ <?= number_format($totalProfit, 2) ?></td>
                                            <td>₱ <?= number_format($product->quantity * $product->cost, 2) ?></td>
                                            <td>
                                                <a href="admin_add_stock.php?product_id=<?= $product->id ?>"
                                                    class="text-green-300">Add Stock</a>
                                                <a href="admin_update_item.php?id=<?= $product->id ?>"
                                                    class="text-primary ml-16">Update</a>
                                                <a href="api/product_controller.php?action=delete&id=<?= $product->id ?>"
                                                    class="text-red-500 ml-16"
                                                    onclick="return confirm('Delete this product?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Metric Details Modal -->
    <div class="modal-overlay" id="metricDetailsModal" onclick="if(event.target === this) this.classList.remove('active')">
        <div class="modal-content" style="max-width: 800px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <span class="modal-title" id="metricDetailsTitle">Metric Details</span>
                <button class="modal-close" onclick="document.getElementById('metricDetailsModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" id="metricDetailsBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal-overlay" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Add New Product</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="addProductForm">
                <div class="modal-body">
                    <!-- Basic Info Section -->
                    <div class="form-section">
                        <div class="form-section-title">📦 Basic Information</div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Product Name <span class="required">*</span></label>
                                <input type="text" name="name" id="m_name" required
                                    placeholder="e.g., Classic Blue Jeans">
                            </div>
                            <div class="form-group">
                                <label>Barcode / SKU</label>
                                <input type="text" name="sku" id="m_sku" placeholder="PRD-001">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Category <span class="required">*</span></label>
                                <select name="category_id" id="m_category" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category->id ?>"><?= htmlspecialchars($category->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="m_catSizeSuggestion" style="display:none; margin-top:8px; font-size:0.95em;">
                                    Suggested sizes: <strong id="m_suggestedSizesText"></strong>
                                    <button id="m_applySuggestedSizes" type="button" class="btn" style="margin-left:8px;">Apply</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Supplier</label>
                                <select name="supplier_id" id="m_supplier">
                                    <option value="">-- Select Supplier (optional) --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= $s->id ?>"><?= htmlspecialchars($s->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Available Sizes <span class="required">*</span></label>
                            <input type="text" name="size" id="m_size" placeholder="S, M, L, XL or 28, 30, 32" required>
                            <div class="quick-sizes">
                                <button type="button" class="quick-size-btn"
                                    onclick="setSize('S, M, L, XL, XXL')">Clothing</button>
                                <button type="button" class="quick-size-btn"
                                    onclick="setSize('28, 30, 32, 34, 36, 38')">Pants</button>
                                <button type="button" class="quick-size-btn"
                                    onclick="setSize('6, 7, 8, 9, 10, 11, 12')">Shoes</button>
                                <button type="button" class="quick-size-btn" onclick="setSize('One Size')">One
                                    Size</button>
                            </div>
                            <div class="size-tags" id="sizeTags"></div>
                        </div>

                        <!-- Stock Allocation Per Size -->
                        <div class="form-group" style="margin-top: 16px;">
                            <label style="display: block; margin-bottom: 8px;">📊 Allocate Stock Per Size</label>
                            <div id="sizeAllocationContainer" style="display:none; border: 1px solid rgba(148,163,184,0.2); border-radius:8px; padding:12px; background:rgba(148,163,184,0.05);">
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:rgba(148,163,184,0.1);">
                                            <th style="text-align:left; padding:8px; font-weight:600;">Size</th>
                                            <th style="text-align:left; padding:8px; font-weight:600;">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sizeAllocationTable">
                                    </tbody>
                                    <tfoot>
                                        <tr style="background:rgba(148,163,184,0.05); font-weight:600; border-top: 1px solid rgba(148,163,184,0.2);">
                                            <td style="padding:8px;">Total Stock:</td>
                                            <td style="padding:8px;"><span id="m_totalStockCount">0</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div id="noSizesMessageModal" class="muted" style="font-size:0.85em; margin-top:8px;">Enter sizes above to allocate stock per size.</div>
                        </div>
                    </div>

                    <!-- Pricing Section -->
                    <div class="form-section">
                        <div class="form-section-title">💰 Pricing & Tax</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Cost Price (₱) <span class="required">*</span></label>
                                <input type="number" step="0.01" min="0" name="cost" id="m_cost" required
                                    placeholder="0.00">
                                <span class="helper">Your purchase price</span>
                            </div>
                            <div class="form-group">
                                <label>Profit (₱) <span class="required">*</span></label>
                                <input type="number" step="0.01" min="0" name="profit_amount" id="m_profit" required
                                    placeholder="0.00">
                                <span class="helper">Profit amount</span>
                            </div>
                            <div class="form-group">
                                <label>Tax Rate (%)</label>
                                <input type="number" step="0.1" min="0" name="tax_rate" id="m_tax"
                                    value="<?= $defaultTaxRate ?>">
                                <span class="helper">VAT/Sales tax</span>
                            </div>
                        </div>

                        <div class="price-breakdown">
                            <div class="price-row">
                                <span class="label">Cost Price:</span>
                                <span id="calc_cost">₱ 0.00</span>
                            </div>
                            <div class="price-row">
                                <span class="label">+ Profit:</span>
                                <span id="calc_profit">₱ 0.00</span>
                            </div>
                            <div class="price-row">
                                <span class="label">+ Tax (<span id="calc_tax_pct">12</span>%):</span>
                                <span id="calc_tax">₱ 0.00</span>
                            </div>
                            <div class="price-row total">
                                <span class="label">Selling Price:</span>
                                <span id="calc_final">₱ 0.00</span>
                            </div>
                        </div>

                        <input type="hidden" name="price" id="m_price" value="0">
                        <input type="hidden" name="quantity" id="m_quantity" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category Management Modal -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span class="modal-title">📁 Manage Categories</span>
                <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Add/Edit Category Form -->
                <div class="form-section" style="margin-bottom: 20px;">
                    <form id="categoryForm">
                        <input type="hidden" id="cat_action" value="add">
                        <input type="hidden" id="cat_id" value="">
                        <div style="display: flex; gap: 12px; align-items: flex-end;">
                            <div class="form-group" style="flex: 1;">
                                <label for="cat_name" id="catFormLabel">Add New Category</label>
                                <input type="text" id="cat_name" placeholder="Enter category name" required>
                            </div>
                            <button type="submit" class="btn-submit" style="padding: 10px 20px;">Save</button>
                            <button type="button" class="btn-cancel" id="cancelEditBtn"
                                style="display: none; padding: 10px 16px;"
                                onclick="cancelCategoryEdit()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Categories List -->
                <div class="form-section">
                    <div class="form-section-title" style="margin-bottom: 12px;">Existing Categories</div>
                    <div id="categoryList" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($categories)): ?>
                            <div class="muted" style="text-align: center; padding: 20px;">No categories yet. Add one above!
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-item" data-id="<?= $cat->id ?>"
                                    data-name="<?= htmlspecialchars($cat->name, ENT_QUOTES) ?>"
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);">
                                    <span class="cat-name"><?= htmlspecialchars($cat->name) ?></span>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" class="btn edit-cat-btn"
                                            style="padding: 6px 12px; font-size: 0.85em;"
                                            onclick="editCategory(<?= $cat->id ?>, '<?= htmlspecialchars($cat->name, ENT_QUOTES) ?>')">Edit</button>
                                        <button type="button" class="btn delete-cat-btn"
                                            style="padding: 6px 12px; font-size: 0.85em; background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #f87171;"
                                            onclick="deleteCategory(<?= $cat->id ?>)">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeCategoryModal()">Close</button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Initialize datatable safely so failure here doesn't stop other scripts
        var dataTable = null;
        try {
            if (window.simpleDatatables && typeof simpleDatatables.DataTable === 'function') {
                dataTable = new simpleDatatables.DataTable("#productsTable");
            }
        } catch (err) {
            console.warn('DataTable initialization failed:', err);
        }

        document.addEventListener('DOMContentLoaded', function () {
            try {
            const modal = document.getElementById('addProductModal');
            const openBtn = document.getElementById('openAddModal');
            const form = document.getElementById('addProductForm');

            const costInput = document.getElementById('m_cost');
            const profitInput = document.getElementById('m_profit');
            const taxInput = document.getElementById('m_tax');
            const priceInput = document.getElementById('m_price');
            const sizeInput = document.getElementById('m_size');
            const sizeTags = document.getElementById('sizeTags');
                const categorySelect = document.getElementById('m_category');

            // Open modal
            openBtn.addEventListener('click', () => {
                modal.classList.add('active');
                document.getElementById('m_name').focus();
            });

            // Close on overlay click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeModal();
            });

            // Calculate prices
            function calculatePrices() {
                const cost = parseFloat(costInput.value) || 0;
                const profitAmount = parseFloat(profitInput.value) || 0;
                const taxPct = parseFloat(taxInput.value) || 0;

                const basePrice = cost + profitAmount;
                const tax = basePrice * (taxPct / 100);
                const finalPrice = basePrice + tax;

                document.getElementById('calc_cost').textContent = '₱ ' + cost.toFixed(2);
                document.getElementById('calc_profit').textContent = '₱ ' + profitAmount.toFixed(2);
                document.getElementById('calc_tax_pct').textContent = taxPct;
                document.getElementById('calc_tax').textContent = '₱ ' + tax.toFixed(2);
                document.getElementById('calc_final').textContent = '₱ ' + finalPrice.toFixed(2);

                priceInput.value = finalPrice.toFixed(2);
            }

            costInput.addEventListener('input', calculatePrices);
            profitInput.addEventListener('input', calculatePrices);
            taxInput.addEventListener('input', calculatePrices);

            // Update size tags
            function updateSizeTags() {
                const sizes = sizeInput.value.split(',').map(s => s.trim()).filter(Boolean);
                sizeTags.innerHTML = sizes.map(s => `<span class="size-tag">${s}</span>`).join('');
                updateSizeAllocation();
            }
            sizeInput.addEventListener('input', updateSizeTags);

            // Update size allocation table
            function updateSizeAllocation() {
                const sizes = sizeInput.value.split(',').map(s => s.trim()).filter(Boolean);
                const container = document.getElementById('sizeAllocationContainer');
                const table = document.getElementById('sizeAllocationTable');
                const noMsg = document.getElementById('noSizesMessageModal');
                const totalCount = document.getElementById('m_totalStockCount');

                if (sizes.length === 0) {
                    container.style.display = 'none';
                    noMsg.style.display = 'block';
                    return;
                }

                container.style.display = 'block';
                noMsg.style.display = 'none';
                table.innerHTML = '';

                sizes.forEach((size, idx) => {
                    const tr = document.createElement('tr');
                    if (idx % 2 === 0) tr.style.background = 'rgba(148,163,184,0.02)';

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.min = '0';
                    input.value = '0';
                    input.className = 'm_size_qty_input';
                    input.style.cssText = 'width:100%; padding:6px; border:1px solid rgba(148,163,184,0.2); border-radius:4px;';
                    input.addEventListener('input', updateModalTotalStock);

                    tr.innerHTML = `<td style="padding:8px;">${size}</td><td style="padding:8px;"></td>`;
                    tr.querySelector('td:nth-child(2)').appendChild(input);
                    table.appendChild(tr);
                });

                updateModalTotalStock();
            }

            function updateModalTotalStock() {
                const inputs = document.querySelectorAll('.m_size_qty_input');
                let total = 0;
                inputs.forEach(input => {
                    total += parseInt(input.value || 0);
                });
                document.getElementById('m_totalStockCount').textContent = total;
                document.getElementById('m_quantity').value = total;
            }

            // Category-based defaults (profit amounts, not percentages)
            const defaults = {
                'footwear': { sizes: '6, 7, 8, 9, 10, 11, 12', profit: 0 },
                'shoes': { sizes: '6, 7, 8, 9, 10, 11, 12', profit: 0 },
                'pants': { sizes: '28, 30, 32, 34, 36, 38', profit: 0 },
                'jeans': { sizes: '28, 30, 32, 34, 36, 38', profit: 0 },
                'shorts': { sizes: '28, 30, 32, 34, 36', profit: 0 },
                'shirts': { sizes: 'S, M, L, XL, XXL', profit: 0 },
                't-shirt': { sizes: 'S, M, L, XL, XXL', profit: 0 },
                'jackets': { sizes: 'S, M, L, XL, XXL', profit: 0 }
            };

            categorySelect.addEventListener('change', function () {
                const text = this.options[this.selectedIndex].text.toLowerCase();
                for (const [key, val] of Object.entries(defaults)) {
                    if (text.includes(key)) {
                        if (!sizeInput.value) {
                            sizeInput.value = val.sizes;
                            updateSizeTags();
                        }
                        if (!profitInput.value) {
                            profitInput.value = val.profit;
                            calculatePrices();
                        }
                        break;
                    }
                }
                // show suggestion UI for sizes
                showModalCategorySuggestion(text);
            });

            function showModalCategorySuggestion(catName) {
                const suggestion = document.getElementById('m_catSizeSuggestion');
                const suggestedText = document.getElementById('m_suggestedSizesText');
                const applyBtn = document.getElementById('m_applySuggestedSizes');
                if (!catName) { suggestion.style.display = 'none'; return; }
                for (const [key, val] of Object.entries(defaults)) {
                    if (catName.includes(key)) {
                        suggestedText.textContent = val.sizes;
                        suggestion.style.display = 'block';
                        applyBtn.onclick = function(){
                            if (sizeInput) {
                                sizeInput.value = val.sizes;
                                sizeInput.dispatchEvent(new Event('input'));
                                updateSizeTags();
                            }
                        };
                        return;
                    }
                }
                suggestion.style.display = 'none';
            }

            // initial suggestion if a category is pre-selected
            if (categorySelect && categorySelect.value) {
                const initText = categorySelect.options[categorySelect.selectedIndex].text.toLowerCase();
                showModalCategorySuggestion(initText);
            }

            // Form submit
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const name = document.getElementById('m_name').value.trim();
                const category = document.getElementById('m_category').value;
                const cost = document.getElementById('m_cost').value;
                const profit = document.getElementById('m_profit').value;
                const size = document.getElementById('m_size').value.trim();

                // First, update the total stock from size allocations
                updateModalTotalStock();
                const quantity = document.getElementById('m_quantity').value;

                if (!name || !category || !cost || profit === '' || !size) {
                    alert('Please fill in all required fields');
                    return;
                }

                if (!quantity || parseInt(quantity) === 0) {
                    alert('Please allocate stock for at least one size');
                    return;
                }

                calculatePrices();

                const formData = new FormData(form);

                // Collect size-based quantities
                const sizeQtyPairs = [];
                const sizes = size.split(',').map(s => s.trim()).filter(Boolean);
                const qtyInputs = document.querySelectorAll('.m_size_qty_input');
                
                sizes.forEach((sizeVal, idx) => {
                    const qty = qtyInputs[idx] ? parseInt(qtyInputs[idx].value || 0) : 0;
                    if (qty > 0) {
                        sizeQtyPairs.push({ size: sizeVal, quantity: qty });
                    }
                });
                
                formData.append('size_quantities', JSON.stringify(sizeQtyPairs));

                fetch('api/product_controller.php?action=add', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Product added successfully!');
                            window.location.reload();
                        } else {
                            alert('❌ Error: ' + (data.message || 'Failed to add product'));
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('❌ Error adding product');
                    });
            });

            calculatePrices();
            } catch (err) {
                console.error('Error in DOMContentLoaded handler:', err);
            }
        });

        function closeModal() {
            document.getElementById('addProductModal').classList.remove('active');
        }

        function setSize(sizes) {
            document.getElementById('m_size').value = sizes;
            document.getElementById('m_size').dispatchEvent(new Event('input'));
        }

        function generateBarcode() {
            const ts = Date.now().toString(36).toUpperCase();
            const rnd = Math.random().toString(36).substring(2, 6).toUpperCase();
            document.getElementById('m_sku').value = 'PRD-' + ts + rnd;
        }

        // Category Modal Functions
        const categoryModal = document.getElementById('categoryModal');
        const openCategoryBtn = document.getElementById('openCategoryModal');
        const categoryForm = document.getElementById('categoryForm');

        // Load categories from server and open modal. Use delegated listener as a fallback
        async function openCategoryModalHandler() {
            try { await loadCategories(); } catch (err) { console.error(err); }
            if (categoryModal) categoryModal.classList.add('active');
        }

        if (openCategoryBtn) {
            openCategoryBtn.addEventListener('click', openCategoryModalHandler);
        } else {
            // Fallback: delegate to document in case button is injected later
            document.addEventListener('click', function (e) {
                if (e.target && (e.target.id === 'openCategoryModal' || e.target.closest && e.target.closest('#openCategoryModal'))) {
                    openCategoryModalHandler();
                }
            });
        }

        // Fetch categories and populate list and product select
        async function loadCategories() {
            const list = document.getElementById('categoryList');
            const select = document.getElementById('m_category');
            // Clear current
            list.innerHTML = '';

            try {
                const res = await fetch('api/categories_list.php', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (res.ok && data && data.success && Array.isArray(data.categories) && data.categories.length > 0) {
                    // Clear select but keep placeholder option if present
                    if (select) {
                        const first = select.querySelector('option');
                        select.innerHTML = '';
                        if (first && first.value === '') select.appendChild(first);
                    }

                    data.categories.forEach(cat => {
                        const item = document.createElement('div');
                        item.className = 'category-item';
                        item.dataset.id = cat.id;
                        item.dataset.name = cat.name;
                        item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);';
                        item.innerHTML = `
                            <span class="cat-name">${cat.name}</span>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn edit-cat-btn" style="padding: 6px 12px; font-size: 0.85em;" onclick="editCategory(${cat.id}, '${cat.name.replace(/'/g, "\\'")}')">Edit</button>
                                <button type="button" class="btn delete-cat-btn" style="padding: 6px 12px; font-size: 0.85em; background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #f87171;" onclick="deleteCategory(${cat.id})">Delete</button>
                            </div>
                        `;
                        list.appendChild(item);

                        if (select) {
                            const opt = document.createElement('option');
                            opt.value = cat.id;
                            opt.textContent = cat.name;
                            select.appendChild(opt);
                        }
                    });
                    return; // done
                }

                // If fetch succeeded but returned no categories, fall through to fallback
            } catch (err) {
                console.error('Failed to load categories', err);
            }

            // Fallback: if the product modal select contains options (server-rendered), use those
            try {
                if (select) {
                    const opts = Array.from(select.querySelectorAll('option')).filter(o => o.value !== '');
                    if (opts.length > 0) {
                        opts.forEach(o => {
                            const item = document.createElement('div');
                            item.className = 'category-item';
                            item.dataset.id = o.value;
                            item.dataset.name = o.textContent;
                            item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);';
                            item.innerHTML = `
                                <span class="cat-name">${o.textContent}</span>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn edit-cat-btn" style="padding: 6px 12px; font-size: 0.85em;" onclick="editCategory(${o.value}, '${o.textContent.replace(/'/g, "\\'")}')">Edit</button>
                                    <button type="button" class="btn delete-cat-btn" style="padding: 6px 12px; font-size: 0.85em; background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #f87171;" onclick="deleteCategory(${o.value})">Delete</button>
                                </div>
                            `;
                            list.appendChild(item);
                        });
                        return;
                    }
                }
            } catch (err) {
                console.error('Fallback category rendering failed', err);
            }

            // Nothing found
            list.innerHTML = '<div class="muted" style="text-align: center; padding: 20px;">No categories yet. Add one above!</div>';
        }

        function closeCategoryModal() {
            categoryModal.classList.remove('active');
            cancelCategoryEdit();
        }

        categoryModal.addEventListener('click', (e) => {
            if (e.target === categoryModal) closeCategoryModal();
        });

        function editCategory(id, name) {
            document.getElementById('cat_action').value = 'update';
            document.getElementById('cat_id').value = id;
            document.getElementById('cat_name').value = name;
            document.getElementById('catFormLabel').textContent = 'Edit Category';
            document.getElementById('cancelEditBtn').style.display = 'block';
            document.getElementById('cat_name').focus();
        }

        function cancelCategoryEdit() {
            document.getElementById('cat_action').value = 'add';
            document.getElementById('cat_id').value = '';
            document.getElementById('cat_name').value = '';
            document.getElementById('catFormLabel').textContent = 'Add New Category';
            document.getElementById('cancelEditBtn').style.display = 'none';
        }

        async function deleteCategory(id) {
            if (!confirm('Delete this category? Products in this category will not be deleted.')) return;

            try {
                const res = await fetch(`api/category_controller.php?action=delete&id=${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (res.ok) {
                    // Remove from list
                    const item = document.querySelector(`.category-item[data-id="${id}"]`);
                    if (item) item.remove();

                    // Remove from product modal dropdown
                    const option = document.querySelector(`#m_category option[value="${id}"]`);
                    if (option) option.remove();

                    // Check if list is empty
                    const list = document.getElementById('categoryList');
                    if (!list.querySelector('.category-item')) {
                        list.innerHTML = '<div class="muted" style="text-align: center; padding: 20px;">No categories yet. Add one above!</div>';
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete'));
                }
            } catch (err) {
                console.error(err);
                alert('Error deleting category');
            }
        }

        categoryForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const action = document.getElementById('cat_action').value;
            const id = document.getElementById('cat_id').value;
            const name = document.getElementById('cat_name').value.trim();

            if (!name) {
                alert('Please enter a category name');
                return;
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('name', name);
            if (id) formData.append('id', id);

            try {
                const res = await fetch('api/category_controller.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (res.ok) {
                    const list = document.getElementById('categoryList');

                    if (action === 'update') {
                        // Update existing item
                        const item = document.querySelector(`.category-item[data-id="${data.id}"]`);
                        if (item) {
                            item.dataset.name = data.name;
                            item.querySelector('.cat-name').textContent = data.name;
                            item.querySelector('.edit-cat-btn').setAttribute('onclick', `editCategory(${data.id}, '${data.name.replace(/'/g, "\\'")}')`);
                        }

                        // Update product modal dropdown
                        const option = document.querySelector(`#m_category option[value="${data.id}"]`);
                        if (option) option.textContent = data.name;

                    } else {
                        // Add new item
                        const emptyMsg = list.querySelector('.muted');
                        if (emptyMsg) emptyMsg.remove();

                        const newItem = document.createElement('div');
                        newItem.className = 'category-item';
                        newItem.dataset.id = data.id;
                        newItem.dataset.name = data.name;
                        newItem.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);';
                        newItem.innerHTML = `
                    <span class="cat-name">${data.name}</span>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn edit-cat-btn" style="padding: 6px 12px; font-size: 0.85em;" onclick="editCategory(${data.id}, '${data.name.replace(/'/g, "\\'")}')">Edit</button>
                        <button type="button" class="btn delete-cat-btn" style="padding: 6px 12px; font-size: 0.85em; background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #f87171;" onclick="deleteCategory(${data.id})">Delete</button>
                    </div>
                `;
                        list.appendChild(newItem);

                        // Add to product modal dropdown
                        const select = document.getElementById('m_category');
                        const option = document.createElement('option');
                        option.value = data.id;
                        option.textContent = data.name;
                        select.appendChild(option);
                    }

                    cancelCategoryEdit();
                } else {
                    alert('Error: ' + (data.error || 'Failed to save'));
                }
            } catch (err) {
                console.error(err);
                alert('Error saving category');
            }
        });

        // Metric card click handlers
        function showMetricDetails(type) {
            const modal = document.getElementById('metricDetailsModal');
            if (!modal) {
                console.error('Modal not found');
                return;
            }
            
            const modalTitle = document.getElementById('metricDetailsTitle');
            const modalBody = document.getElementById('metricDetailsBody');
            
            modal.classList.add('active');
            modalBody.innerHTML = '<div class="loading-spinner">Loading...</div>';
            
            fetch(`api/metric_details.php?type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = data.title;
                        modalBody.innerHTML = data.html;
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<p style="color: var(--text-muted);">Error loading data.</p>';
                    console.error('Error:', error);
                });
        }

        function scrollToTable() {
            const table = document.getElementById('productsTable');
            if (table) {
                table.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function filterLowStock() {
            showMetricDetails('low_stock');
        }
    </script>

</body>

</html>