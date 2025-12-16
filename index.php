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

// Load size stocks for each product
require_once 'models/Product.php';
foreach ($products as &$product) {
    $productObj = Product::find($product['id']);
    if ($productObj) {
        $sizeStocks = $productObj->getSizeStocks();
        $product['size_stocks'] = [];
        foreach ($sizeStocks as $sizeStock) {
            $product['size_stocks'][$sizeStock['size']] = $sizeStock['quantity'];
        }
    }
}
unset($product); // Break reference

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

        /* POS theme tokens to support dark/light toggle */
        :root {
            --pos-surface-0: #0a0f1a;
            --pos-surface-1: #0f172a;
            --pos-surface-2: #1e293b;
            --pos-surface-3: #334155;
            --pos-card: #0f172a;
            --pos-text: #e2e8f0;
            --pos-text-muted: #94a3b8;
            --pos-border: rgba(148, 163, 184, 0.2);
            --pos-accent: #3b82f6;
            --pos-accent-2: #8b5cf6;
            --pos-success: #22c55e;
            --pos-warning: #fbbf24;
        }

        :root[data-theme="light"] {
            --pos-surface-0: #f5f7fb;
            --pos-surface-1: #ffffff;
            --pos-surface-2: #eef2f7;
            --pos-surface-3: #dbe4ef;
            --pos-card: #ffffff;
            --pos-text: #0f172a;
            --pos-text-muted: #4b5563;
            --pos-border: rgba(0, 0, 0, 0.08);
            --pos-accent: #2563eb;
            --pos-accent-2: #7c3aed;
            --pos-success: #16a34a;
            --pos-warning: #d97706;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--pos-surface-0);
            color: var(--pos-text);
        }

        /* Main Layout */
        .pos-container {
            display: grid;
            grid-template-columns: 1fr minmax(340px, 420px);
            gap: 0;
            height: calc(100vh - 60px);
            overflow: hidden;
            max-width: 100vw;
        }

        /* Left Panel - Products */
        .products-panel {
            display: flex;
            flex-direction: column;
            background: var(--pos-surface-1);
            overflow: hidden;
        }

        /* Search and Controls Bar */
        .search-bar {
            padding: 20px;
            background: var(--pos-surface-2);
            border-bottom: 1px solid var(--pos-border);
        }

        .search-input-wrapper {
            position: relative;
            margin-bottom: 16px;
        }

        .search-input {
            width: 100%;
            padding: 14px 48px 14px 20px;
            background: var(--pos-surface-1);
            border: 2px solid var(--pos-border);
            border-radius: 12px;
            color: var(--pos-text);
            font-size: 16px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--pos-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--pos-text-muted);
            pointer-events: none;
        }

        .barcode-input-wrapper {
            position: relative;
        }

        .barcode-input {
            width: 100%;
            padding: 12px 20px;
            background: var(--pos-surface-1);
            border: 2px dashed var(--pos-border);
            border-radius: 8px;
            color: var(--pos-text);
            font-size: 14px;
        }

        .barcode-input:focus {
            outline: none;
            border-color: var(--pos-accent-2);
        }

        /* Category Filters */
        .category-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 16px 20px;
            background: var(--pos-surface-2);
            border-bottom: 1px solid var(--pos-border);
            overflow-x: auto;
        }

        .category-btn {
            padding: 10px 20px;
            background: var(--pos-surface-1);
            border: 1px solid var(--pos-border);
            color: var(--pos-text);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            white-space: nowrap;
        }

        .category-btn:hover {
            background: var(--pos-surface-2);
            border-color: var(--pos-accent);
            color: var(--pos-accent);
        }

        .category-btn.active {
            background: linear-gradient(135deg, var(--pos-accent), var(--pos-accent-2));
            border-color: var(--pos-accent);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Products Grid */
        .products-section {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: var(--pos-surface-1);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: linear-gradient(135deg, var(--pos-surface-2), var(--pos-surface-1));
            border: 1px solid var(--pos-border);
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
            background: linear-gradient(90deg, var(--pos-accent), var(--pos-accent-2));
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .product-card:hover {
            border-color: var(--pos-accent);
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(59, 130, 246, 0.2);
        }

        .product-card:hover::before {
            transform: scaleX(1);
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--pos-text);
            font-size: 14px;
            line-height: 1.4;
        }

        .product-price {
            color: var(--pos-success);
            font-size: 18px;
            font-weight: 700;
            margin: 8px 0;
        }

        .product-stock {
            color: var(--pos-text-muted);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stock-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--pos-success);
        }

        .product-size {
            color: var(--pos-text-muted);
            font-size: 11px;
            margin-top: 4px;
            font-style: italic;
        }

        .product-stock.low .stock-indicator {
            background: var(--pos-warning);
        }

        /* Right Panel - Cart */
        .cart-panel {
            background: var(--pos-surface-2);
            border-left: 1px solid var(--pos-border);
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--pos-surface-2), var(--pos-surface-3));
            border-bottom: 1px solid var(--pos-border);
        }

        .cart-header h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: var(--pos-text);
        }

        .cashier-info {
            font-size: 13px;
            color: var(--pos-text-muted);
            margin-bottom: 12px;
        }

        .btn-history {
            width: 100%;
            padding: 10px 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-history:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-history:active {
            transform: translateY(0);
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
            color: var(--pos-text-muted);
        }

        .cart-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        .cart-item {
            background: var(--pos-card);
            border: 1px solid var(--pos-border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }

        .cart-item:hover {
            border-color: var(--pos-accent);
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--pos-text);
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
            background: var(--pos-accent);
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
            background: var(--pos-accent-2);
            transform: scale(1.1);
        }

        .qty-display {
            min-width: 30px;
            text-align: center;
            color: var(--pos-text);
            font-weight: 600;
        }

        .cart-item-subtotal {
            color: var(--pos-success);
            font-weight: 700;
            font-size: 14px;
        }

        /* Cart Summary */
        .cart-summary {
            padding: 20px;
            background: var(--pos-card);
            border-top: 2px solid var(--pos-border);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-label {
            color: var(--pos-text-muted);
        }

        .summary-value {
            color: var(--pos-text);
            font-weight: 600;
        }

        .cart-total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--pos-border);
        }

        .total-label {
            font-size: 16px;
            color: var(--pos-text-muted);
            margin-bottom: 8px;
        }

        .total-amount {
            font-size: 32px;
            color: var(--pos-success);
            font-weight: 800;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
        }

        /* Action Buttons */
        .cart-actions {
            padding: 16px 20px;
            background: var(--pos-surface-2);
            border-top: 1px solid var(--pos-border);
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
            background: var(--pos-surface-3);
            color: var(--pos-text);
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

        /* Order History Modal */
        .order-history-modal {
            max-width: 600px;
        }

        .order-history-body {
            max-height: 70vh !important;
            overflow-y: auto !important;
        }

        .order-history-search {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .order-history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .order-history-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--pos-text-muted);
        }

        .order-card {
            background: var(--pos-surface-2);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            padding: 16px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .order-card:hover {
            border-color: #667eea;
            background: var(--pos-surface-3);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .order-card-id {
            font-weight: 700;
            color: #667eea;
            font-size: 15px;
        }

        .order-card-time {
            font-size: 12px;
            color: var(--pos-text-muted);
        }

        .order-card-amount {
            font-size: 20px;
            font-weight: 800;
            color: #22c55e;
        }

        .order-card-details {
            font-size: 13px;
            color: var(--pos-text-muted);
            margin: 8px 0;
        }

        .order-card-customer {
            color: #cbd5e1;
            margin: 8px 0;
        }

        .order-card-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-reprint {
            flex: 1;
            padding: 10px 12px;
            background: #667eea;
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-reprint:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }

        .btn-reprint:active {
            transform: translateY(0);
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
        @media (max-width: 1280px) {
            .pos-container {
                grid-template-columns: 1fr 360px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        @media (max-width: 1024px) {
            .pos-container {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr auto;
                height: auto;
                min-height: calc(100vh - 60px);
            }

            .products-section {
                padding: 16px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }

            .cart-panel {
                border-left: none;
                border-top: 1px solid rgba(148, 163, 184, 0.2);
                max-height: unset;
                position: sticky;
                bottom: 0;
                z-index: 5;
            }

            .cart-actions {
                position: sticky;
                bottom: 0;
                background: #1e293b;
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 13px;
            }

            .search-bar {
                padding: 14px;
            }

            .search-input {
                padding: 12px 44px 12px 14px;
                font-size: 15px;
            }

            .category-filters {
                padding: 12px 14px;
                gap: 6px;
            }

            .products-section {
                padding: 14px;
            }

            .products-grid {
                gap: 12px;
                grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
            }

            .product-card {
                padding: 12px;
            }

            .cart-item-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .cart-actions {
                flex-direction: column;
            }

            .btn-checkout {
                width: 100%;
            }

            .payment-methods {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }

            .modal-content {
                width: 95%;
                max-height: 85vh;
            }
        }

        @media (max-width: 560px) {
            .pos-container {
                height: auto;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .cart-header h2 {
                font-size: 18px;
            }

            .total-amount {
                font-size: 26px;
            }

            .cart-item-subtotal {
                font-size: 13px;
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

        /* Size Selection Modal */
        .size-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .size-option {
            padding: 12px 20px;
            background: #0f172a;
            border: 2px solid rgba(148, 163, 184, 0.3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: #cbd5e1;
            font-weight: 600;
        }

        .size-option:hover {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .size-option.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: rgba(239, 68, 68, 0.5);
        }

        .size-option.out-of-stock:hover {
            border-color: rgba(239, 68, 68, 0.5);
            background: #0f172a;
        }

        .cart-item-size {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
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
                    <span class="search-icon">&#128269;</span>
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
                            data-size="<?= htmlspecialchars($product['size'] ?? '') ?>"
                            data-size-stocks="<?= htmlspecialchars(json_encode($product['size_stocks'] ?? []), ENT_QUOTES) ?>">
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-price">&#8369; <?= number_format($product['price'], 2) ?></div>
                            <?php if (!empty($product['size'])): ?>
                                <div class="product-size">&#128207; <?= htmlspecialchars($product['size']) ?></div>
                                <?php if (!empty($product['size_stocks'])): ?>
                                    <div class="product-size-stocks">
                                        <?php 
                                        // Sort sizes intelligently
                                        $sizeStocksArray = [];
                                        foreach ($product['size_stocks'] as $size => $qty) {
                                            // Only include sizes with available stock (qty > 0)
                                            if ($qty > 0) {
                                                $sizeStocksArray[] = ['size' => $size, 'quantity' => $qty];
                                            }
                                        }
                                        
                                        // Only display if there are available sizes
                                        if (!empty($sizeStocksArray)):
                                            usort($sizeStocksArray, function($a, $b) {
                                                $sizeA = trim($a['size']);
                                                $sizeB = trim($b['size']);
                                                
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
                                            
                                            $sizeStockDisplay = [];
                                            foreach ($sizeStocksArray as $item) {
                                                $sizeStockDisplay[] = $item['size'] . ': ' . $item['quantity'];
                                            }
                                            echo htmlspecialchars(implode(', ', $sizeStockDisplay));
                                        else:
                                            echo htmlspecialchars('Out of Stock');
                                        endif;
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
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
                <div class="cashier-info">&#128100; <?= htmlspecialchars($currentCashier->name) ?></div>
                <button class="btn-history" onclick="openOrderHistory()" title="View previous orders (Ctrl+H)">
                    &#128336; Order History
                </button>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="cart-empty">
                    <div class="cart-empty-icon">&#128722;</div>
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
                    <div class="total-amount" id="totalAmount">&#8369; 0.00</div>
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

    <!-- Order History Modal -->
    <div class="modal" id="orderHistoryModal">
        <div class="modal-content order-history-modal">
            <div class="modal-header">
                <h3>&#128336; Order History</h3>
                <button class="modal-close" onclick="closeOrderHistory()">&times;</button>
            </div>

            <div class="modal-body order-history-body">
                <div class="order-history-search">
                    <input type="date" id="searchDate" class="form-input" onchange="filterOrders()">
                    <input type="text" id="searchCustomer" class="form-input" placeholder="Search customer name..." 
                        oninput="filterOrders()" style="margin-top: 12px;">
                </div>

                <div class="order-history-list" id="orderList">
                    <div class="order-history-empty">
                        <div style="font-size: 48px; margin-bottom: 12px;">&#128722;</div>
                        <div>Loading orders...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>&#128179; Payment</h3>
                <button class="modal-close" onclick="closePaymentModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method active" data-method="cash" onclick="selectPaymentMethod('cash')">
                            <div class="payment-method-icon">&#128181;</div>
                            <div class="payment-method-label">Cash</div>
                        </div>
                    </div>
                </div>
                <div class="payment-method-icon">&#128181;</div>
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
                        <span class="payment-value" id="modalSubtotal">&#8369; 0.00</span>
                    </div>
                    <div class="payment-row total">
                        <span class="payment-label">Total:</span>
                        <span class="payment-value" id="modalTotal">&#8369; 0.00</span>
                    </div>
                    <div class="payment-row change" id="changeRow" style="display:none;">
                        <span class="payment-label">Change:</span>
                        <span class="payment-value" id="changeAmount">&#8369; 0.00</span>
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
        let allOrders = [];
        let filteredOrders = [];

        // Load orders from API
        async function loadOrders() {
            try {
                const response = await fetch('api/get_orders.php');
                if (!response.ok) {
                    const text = await response.text();
                    console.error('get_orders.php error', response.status, text);
                    showToast('Could not load order history', 'error');
                    return;
                }

                const data = await response.json();
                if (data.success && data.orders) {
                    allOrders = data.orders;
                    filteredOrders = data.orders;
                    renderOrderHistory();
                } else {
                    console.error('get_orders.php returned error payload', data);
                    showToast('Could not load order history', 'error');
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                showToast('Could not load order history', 'error');
            }
        }

        // Open order history modal
        function openOrderHistory() {
            document.getElementById('orderHistoryModal').classList.add('active');
            loadOrders();
        }

        // Close order history modal
        function closeOrderHistory() {
            document.getElementById('orderHistoryModal').classList.remove('active');
        }

        // Filter orders by date and customer
        function filterOrders() {
            const dateFilter = document.getElementById('searchDate').value;
            const customerFilter = document.getElementById('searchCustomer').value.toLowerCase();

            filteredOrders = allOrders.filter(order => {
                let matches = true;

                if (dateFilter) {
                    const orderDate = new Date(order.created_at).toISOString().split('T')[0];
                    matches = matches && orderDate === dateFilter;
                }

                if (customerFilter) {
                    const customerName = (order.customer_name || '').toLowerCase();
                    matches = matches && customerName.includes(customerFilter);
                }

                return matches;
            });

            renderOrderHistory();
        }

        // Render order history list
        function renderOrderHistory() {
            const orderList = document.getElementById('orderList');

            if (filteredOrders.length === 0) {
                orderList.innerHTML = `
                <div class="order-history-empty">
                    <div style="font-size: 48px; margin-bottom: 12px;">&#128722;</div>
                    <div>No orders found</div>
                </div>
            `;
                return;
            }

            let html = '';
            filteredOrders.forEach(order => {
                const orderDate = new Date(order.created_at);
                const dateStr = orderDate.toLocaleDateString();
                const timeStr = orderDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const itemCount = order.items ? order.items.length : 0;
                const customerDisplay = order.customer_name ? `👤 ${order.customer_name}` : '👤 Walk-in Customer';

                html += `
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <div class="order-card-id">Order #${order.order_id}</div>
                            <div class="order-card-time">${dateStr} ${timeStr}</div>
                        </div>
                        <div class="order-card-amount">₱ ${parseFloat(order.total_amount).toFixed(2)}</div>
                    </div>
                    <div class="order-card-customer">${customerDisplay}</div>
                    <div class="order-card-details">
                        ${itemCount} item${itemCount !== 1 ? 's' : ''} • ${order.payment_method || 'Cash'} payment
                    </div>
                    <div class="order-card-actions">
                        <button class="btn-reprint" onclick="reprintReceipt(${order.order_id})">
                            &#128424; Reprint Receipt
                        </button>
                    </div>
                </div>
            `;
            });

            orderList.innerHTML = html;
        }

        // Reprint receipt
        function reprintReceipt(orderId) {
            window.open(`api/generate_receipt_pdf.php?order_id=${orderId}`, '_blank');
            showToast('Receipt opening...', 'success');
        }

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
                    <div class="cart-empty-icon">&#128722;</div>
                    <div>Cart is empty</div>
                    <div style="font-size:12px; margin-top:8px;">Click on products to add</div>
                </div>
            `;
                totalEl.textContent = '\u20B1 0.00';
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
                        <button class="cart-item-remove" onclick="removeFromCart(${item.id})">&times;</button>
                    </div>
                    <div class="cart-item-footer">
                        <div class="cart-item-qty">
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">-</button>
                            <span class="qty-display">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>
                        <div class="cart-item-subtotal">\u20B1 ${subtotal.toFixed(2)}</div>
                    </div>
                </div>
            `;
            });

            cartItemsEl.innerHTML = html;
            totalEl.textContent = '\u20B1 ' + total.toFixed(2);
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

            document.getElementById('modalSubtotal').textContent = '\u20B1 ' + total.toFixed(2);
            document.getElementById('modalTotal').textContent = '\u20B1 ' + total.toFixed(2);
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
                    document.getElementById('changeAmount').textContent = '\u20B1 ' + change.toFixed(2);
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

            // Ctrl+H - Order History
            if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                e.preventDefault();
                openOrderHistory();
            }

            // ESC - Close modal or clear cart
            if (e.key === 'Escape') {
                const historyModal = document.getElementById('orderHistoryModal');
                const paymentModal = document.getElementById('paymentModal');
                if (historyModal.classList.contains('active')) {
                    closeOrderHistory();
                } else if (paymentModal.classList.contains('active')) {
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

        document.getElementById('orderHistoryModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeOrderHistory();
            }
        });
    </script>
    <script src="js/pos_size_support.js?v=<?= time() ?>"></script>
</body>

</html>