<?php
// Cashier guard - only cashiers can access this page
require_once '_guards.php';
Guard::cashierOnly();

// Get all products with categories for the POS
global $connection;
$stmt = $connection->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    INNER JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity > 0
    ORDER BY c.name, p.name
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filtering
$categories = Category::all();

// Get current cashier info
$currentCashier = User::getAuthenticatedUser(ROLE_CASHIER);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale System :: Cashier</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0a0f1a;
            color: #e2e8f0;
        }

        /* Main Layout */
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 0;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Left Panel - Products */
        .products-panel {
            display: flex;
            flex-direction: column;
            background: #0f172a;
            overflow: hidden;
        }

        /* Search and Controls Bar */
        .search-bar {
            padding: 20px;
            background: #1e293b;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .search-input-wrapper {
            position: relative;
            margin-bottom: 16px;
        }

        .search-input {
            width: 100%;
            padding: 14px 48px 14px 20px;
            background: #0f172a;
            border: 2px solid rgba(148, 163, 184, 0.3);
            border-radius: 12px;
            color: #e2e8f0;
            font-size: 16px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .barcode-input-wrapper {
            position: relative;
        }

        .barcode-input {
            width: 100%;
            padding: 12px 20px;
            background: #0f172a;
            border: 2px dashed rgba(148, 163, 184, 0.3);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 14px;
        }

        .barcode-input:focus {
            outline: none;
            border-color: #8b5cf6;
        }

        /* Category Filters */
        .category-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 16px 20px;
            background: #1e293b;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            overflow-x: auto;
        }

        .category-btn {
            padding: 10px 20px;
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #cbd5e1;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            white-space: nowrap;
        }

        .category-btn:hover {
            background: #1e293b;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .category-btn.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Products Grid */
        .products-section {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .product-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(59, 130, 246, 0.2);
        }

        .product-card:hover::before {
            transform: scaleX(1);
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #f1f5f9;
            font-size: 14px;
            line-height: 1.4;
        }

        .product-price {
            color: #4ade80;
            font-size: 18px;
            font-weight: 700;
            margin: 8px 0;
        }

        .product-stock {
            color: #94a3b8;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stock-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4ade80;
        }

        .product-size {
            color: #64748b;
            font-size: 11px;
            margin-top: 4px;
            font-style: italic;
        }

        .product-stock.low .stock-indicator {
            background: #fbbf24;
        }

        /* Right Panel - Cart */
        .cart-panel {
            background: #1e293b;
            border-left: 1px solid rgba(148, 163, 184, 0.2);
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            padding: 20px;
            background: linear-gradient(135deg, #1e293b, #334155);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .cart-header h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: #f1f5f9;
        }

        .cashier-info {
            font-size: 13px;
            color: #94a3b8;
        }

        /* Cart Items */
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .cart-empty {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .cart-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        .cart-item {
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }

        .cart-item:hover {
            border-color: #3b82f6;
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .cart-item-name {
            font-weight: 600;
            color: #f1f5f9;
            font-size: 14px;
            flex: 1;
        }

        .cart-item-remove {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
            padding: 0 4px;
            line-height: 1;
        }

        .cart-item-remove:hover {
            color: #dc2626;
        }

        .cart-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            background: #3b82f6;
            border: none;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .qty-btn:hover {
            background: #2563eb;
            transform: scale(1.1);
        }

        .qty-display {
            min-width: 30px;
            text-align: center;
            color: #e2e8f0;
            font-weight: 600;
        }

        .cart-item-subtotal {
            color: #4ade80;
            font-weight: 700;
            font-size: 14px;
        }

        /* Cart Summary */
        .cart-summary {
            padding: 20px;
            background: #0f172a;
            border-top: 2px solid rgba(148, 163, 184, 0.2);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-label {
            color: #94a3b8;
        }

        .summary-value {
            color: #e2e8f0;
            font-weight: 600;
        }

        .cart-total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid rgba(148, 163, 184, 0.2);
        }

        .total-label {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .total-amount {
            font-size: 32px;
            color: #4ade80;
            font-weight: 800;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
        }

        /* Action Buttons */
        .cart-actions {
            padding: 16px 20px;
            background: #1e293b;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-clear {
            background: #334155;
            color: #cbd5e1;
        }

        .btn-clear:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
        }

        .btn-checkout {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            flex: 2;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.4);
        }

        .btn-checkout:disabled {
            background: #374151;
            color: #6b7280;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Payment Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: #1e293b;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            color: #f1f5f9;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-weight: 600;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: #0f172a;
            border: 2px solid rgba(148, 163, 184, 0.3);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 16px;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .payment-method {
            padding: 16px 12px;
            background: #0f172a;
            border: 2px solid rgba(148, 163, 184, 0.3);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .payment-method:hover {
            border-color: #3b82f6;
        }

        .payment-method.active {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .payment-method-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .payment-method-label {
            font-size: 13px;
            color: #cbd5e1;
            font-weight: 600;
        }

        .payment-details {
            background: #0f172a;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .payment-row.total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid rgba(148, 163, 184, 0.2);
        }

        .payment-row.total .payment-label {
            font-size: 18px;
            font-weight: 700;
            color: #f1f5f9;
        }

        .payment-row.total .payment-value {
            font-size: 24px;
            font-weight: 800;
            color: #4ade80;
        }

        .payment-row.change .payment-value {
            color: #3b82f6;
            font-weight: 700;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            display: flex;
            gap: 12px;
        }

        .btn-modal {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-modal-cancel {
            background: #374151;
            color: #cbd5e1;
        }

        .btn-modal-cancel:hover {
            background: #4b5563;
        }

        .btn-modal-confirm {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .btn-modal-confirm:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(34, 197, 94, 0.4);
        }

        .btn-modal-confirm:disabled {
            background: #374151;
            color: #6b7280;
            cursor: not-allowed;
        }

        /* Notification Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 16px 24px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s;
        }

        .toast.active {
            display: flex;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0f172a;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .pos-container {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr auto;
            }

            .cart-panel {
                border-left: none;
                border-top: 1px solid rgba(148, 163, 184, 0.2);
                max-height: 50vh;
            }
        }

        /* Keyboard Shortcut Hints */
        .shortcut-hint {
            font-size: 11px;
            color: #64748b;
            margin-left: 8px;
            background: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php require 'templates/admin_header.php' ?>

    <div class="pos-container">
        <!-- Products Panel -->
        <div class="products-panel">
            <div class="search-bar">
                <div class="search-input-wrapper">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search products... (F2)"
                        autofocus>
                    <span class="search-icon">ðŸ”</span>
                </div>
                <div class="barcode-input-wrapper">
                    <input type="text" id="barcodeInput" class="barcode-input"
                        placeholder="Scan barcode or enter product code">
                </div>
            </div>

            <div class="category-filters">
                <button class="category-btn active" onclick="filterCategory('all')">All Products</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="category-btn"
                        onclick="filterCategory('<?= $cat->id ?>')"><?= htmlspecialchars($cat->name) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="products-section">
                <div class="products-grid" id="productsGrid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-id="<?= $product['id'] ?>"
                            data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>"
                            data-stock="<?= $product['quantity'] ?>" data-category="<?= $product['category_id'] ?>"
                            onclick="addToCart(this)">
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-price">â‚± <?= number_format($product['price'], 2) ?></div>
                            <div class="product-stock <?= $product['quantity'] < 10 ? 'low' : '' ?>">
                                <span class="stock-indicator"></span>
                                <?= $product['quantity'] ?> in stock
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Cart Panel -->
        <div class="cart-panel">
            <div class="cart-header">
                <h2>Current Order</h2>
                <div class="cashier-info">ðŸ‘¤ <?= htmlspecialchars($currentCashier->name) ?></div>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="cart-empty">
                    <div class="cart-empty-icon">ðŸ›’</div>
                    <div>Cart is empty</div>
                    <div style="font-size:12px; margin-top:8px;">Click on products to add</div>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span class="summary-label">Items:</span>
                    <span class="summary-value" id="itemCount">0</span>
                </div>
                <div class="cart-total">
                    <div class="total-label">Total Amount:</div>
                    <div class="total-amount" id="totalAmount">â‚± 0.00</div>
                </div>
            </div>

            <div class="cart-actions">
                <button class="btn btn-clear" onclick="clearCart()">Clear</button>
                <button class="btn btn-checkout" id="checkoutBtn" onclick="openPaymentModal()" disabled>
                    Checkout <span class="shortcut-hint">F8</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ðŸ’³ Payment</h3>
                <button class="modal-close" onclick="closePaymentModal()">Ã—</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method active" data-method="cash" onclick="selectPaymentMethod('cash')">
                            <div class="payment-method-icon">ðŸ’µ</div>
                            <div class="payment-method-label">Cash</div>
                        </div>
                        <div class="payment-method" data-method="card" onclick="selectPaymentMethod('card')">
                            <div class="payment-method-icon">ðŸ’³</div>
                            <div class="payment-method-label">Card</div>
                        </div>
                        <div class="payment-method" data-method="e-wallet" onclick="selectPaymentMethod('e-wallet')">
                            <div class="payment-method-icon">ðŸ“±</div>
                            <div class="payment-method-label">E-Wallet</div>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="cashPaymentSection">
                    <label class="form-label">Amount Received</label>
                    <input type="number" id="paymentAmount" class="form-input" placeholder="0.00" step="0.01"
                        oninput="calculateChange()">
                </div>

                <div class="form-group">
                    <label class="form-label">Customer Name (Optional)</label>
                    <input type="text" id="customerName" class="form-input" placeholder="Enter customer name">
                </div>

                <div class="payment-details">
                    <div class="payment-row">
                        <span class="payment-label">Subtotal:</span>
                        <span class="payment-value" id="modalSubtotal">â‚± 0.00</span>
                    </div>
                    <div class="payment-row total">
                        <span class="payment-label">Total:</span>
                        <span class="payment-value" id="modalTotal">â‚± 0.00</span>
                    </div>
                    <div class="payment-row change" id="changeRow" style="display:none;">
                        <span class="payment-label">Change:</span>
                        <span class="payment-value" id="changeAmount">â‚± 0.00</span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-modal btn-modal-cancel" onclick="closePaymentModal()">Cancel</button>
                <button class="btn-modal btn-modal-confirm" id="confirmPaymentBtn" onclick="processPayment()" disabled>
                    Complete Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script>
        let cart = [];
        let selectedPaymentMethod = 'cash';

        // Product filtering
        function filterCategory(categoryId) {
            const products = document.querySelectorAll('.product-card');
            const buttons = document.querySelectorAll('.category-btn');

            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            products.forEach(product => {
                if (categoryId === 'all' || product.dataset.category === categoryId) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Product search
        document.getElementById('searchInput').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const products = document.querySelectorAll('.product-card');

            products.forEach(product => {
                const name = product.dataset.name.toLowerCase();
                if (name.includes(searchTerm)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        });

        // Barcode input
        document.getElementById('barcodeInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const barcode = e.target.value.trim();
                if (barcode) {
                    // Try to find product by ID or name
                    const product = document.querySelector(
                        `.product-card[data-id="${barcode}"], .product-card[data-name="${barcode}"]`);
                    if (product) {
                        addToCart(product);
                        e.target.value = '';
                        showToast('Product added to cart', 'success');
                    } else {
                        showToast('Product not found', 'error');
                    }
                }
            }
        });

        // Add to cart
        function addToCart(productElement) {
            const product = {
                id: parseInt(productElement.dataset.id),
                name: productElement.dataset.name,
                price: parseFloat(productElement.dataset.price),
                stock: parseInt(productElement.dataset.stock)
            };

            const existingItem = cart.find(item => item.id === product.id);

            if (existingItem) {
                if (existingItem.quantity < product.stock) {
                    existingItem.quantity++;
                } else {
                    showToast('Not enough stock!', 'error');
                    return;
                }
            } else {
                cart.push({
                    ...product,
                    quantity: 1
                });
            }

            renderCart();
            showToast('Added to cart', 'success');
        }

        // Update quantity
        function updateQuantity(productId, change) {
            const item = cart.find(i => i.id === productId);
            if (!item) return;

            item.quantity += change;

            if (item.quantity <= 0) {
                removeFromCart(productId);
            } else if (item.quantity > item.stock) {
                item.quantity = item.stock;
                showToast('Maximum stock reached', 'error');
            }

            renderCart();
        }

        // Remove from cart
        function removeFromCart(productId) {
            cart = cart.filter(i => i.id !== productId);
            renderCart();
            showToast('Item removed', 'success');
        }

        // Render cart
        function renderCart() {
            const cartItemsEl = document.getElementById('cartItems');
            const totalEl = document.getElementById('totalAmount');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const itemCountEl = document.getElementById('itemCount');

            if (cart.length === 0) {
                cartItemsEl.innerHTML = `
                <div class="cart-empty">
                    <div class="cart-empty-icon">ðŸ›’</div>
                    <div>Cart is empty</div>
                    <div style="font-size:12px; margin-top:8px;">Click on products to add</div>
                </div>
            `;
                totalEl.textContent = 'â‚± 0.00';
                itemCountEl.textContent = '0';
                checkoutBtn.disabled = true;
                return;
            }

            let html = '';
            let total = 0;
            let itemCount = 0;

            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                total += subtotal;
                itemCount += item.quantity;

                html += `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <div class="cart-item-name">${item.name}</div>
                        <button class="cart-item-remove" onclick="removeFromCart(${item.id})">Ã—</button>
                    </div>
                    <div class="cart-item-footer">
                        <div class="cart-item-qty">
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">âˆ’</button>
                            <span class="qty-display">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>
                        <div class="cart-item-subtotal">â‚± ${subtotal.toFixed(2)}</div>
                    </div>
                </div>
            `;
            });

            cartItemsEl.innerHTML = html;
            totalEl.textContent = 'â‚± ' + total.toFixed(2);
            itemCountEl.textContent = itemCount;
            checkoutBtn.disabled = false;
        }

        // Clear cart
        function clearCart() {
            if (cart.length === 0) return;
            if (!confirm('Clear all items from cart?')) return;

            cart = [];
            renderCart();
            showToast('Cart cleared', 'success');
        }

        // Payment Modal
        function openPaymentModal() {
            if (cart.length === 0) return;

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            document.getElementById('modalSubtotal').textContent = 'â‚± ' + total.toFixed(2);
            document.getElementById('modalTotal').textContent = 'â‚± ' + total.toFixed(2);
            document.getElementById('paymentAmount').value = '';
            document.getElementById('customerName').value = '';
            document.getElementById('changeRow').style.display = 'none';

            selectPaymentMethod('cash');

            document.getElementById('paymentModal').classList.add('active');
            setTimeout(() => document.getElementById('paymentAmount').focus(), 100);
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;

            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('active');
                if (el.dataset.method === method) {
                    el.classList.add('active');
                }
            });

            // Show/hide cash payment section
            const cashSection = document.getElementById('cashPaymentSection');
            if (method === 'cash') {
                cashSection.style.display = 'block';
            } else {
                cashSection.style.display = 'none';
                // For card/e-wallet, enable button immediately
                document.getElementById('confirmPaymentBtn').disabled = false;
            }

            calculateChange();
        }

        function calculateChange() {
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const confirmBtn = document.getElementById('confirmPaymentBtn');

            if (selectedPaymentMethod === 'cash') {
                const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
                const change = payment - total;

                if (change >= 0) {
                    document.getElementById('changeRow').style.display = 'flex';
                    document.getElementById('changeAmount').textContent = 'â‚± ' + change.toFixed(2);
                    confirmBtn.disabled = false;
                } else {
                    document.getElementById('changeRow').style.display = 'none';
                    confirmBtn.disabled = true;
                }
            } else {
                document.getElementById('changeRow').style.display = 'none';
                confirmBtn.disabled = false;
            }
        }

        // Process payment
        async function processPayment() {
            if (cart.length === 0) return;

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const paymentAmount = selectedPaymentMethod === 'cash' ?
                parseFloat(document.getElementById('paymentAmount').value) || 0 :
                total;
            const change = selectedPaymentMethod === 'cash' ? paymentAmount - total : 0;
            const customerName = document.getElementById('customerName').value.trim();

            if (selectedPaymentMethod === 'cash' && change < 0) {
                showToast('Insufficient payment', 'error');
                return;
            }

            const confirmBtn = document.getElementById('confirmPaymentBtn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Processing...';

            try {
                const response = await fetch('api/cashier_controller.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        items: cart,
                        payment_method: selectedPaymentMethod,
                        payment_amount: paymentAmount,
                        change_amount: change,
                        customer_name: customerName || null
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Open receipt
                    window.open(`api/generate_receipt_pdf.php?order_id=${data.order_id}`, '_blank');

                    // Clear cart
                    cart = [];
                    renderCart();
                    closePaymentModal();

                    showToast('Transaction completed successfully!', 'success');
                } else {
                    showToast('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showToast('Error processing payment: ' + error.message, 'error');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Complete Payment';
            }
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');

            toastMessage.textContent = message;
            toast.className = 'toast active ' + type;

            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // F2 - Focus search
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }

            // F8 - Checkout
            if (e.key === 'F8') {
                e.preventDefault();
                if (cart.length > 0) {
                    openPaymentModal();
                }
            }

            // ESC - Close modal or clear cart
            if (e.key === 'Escape') {
                const modal = document.getElementById('paymentModal');
                if (modal.classList.contains('active')) {
                    closePaymentModal();
                }
            }
        });

        // Close modal on backdrop click
        document.getElementById('paymentModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    </script>
</body>

</html>
