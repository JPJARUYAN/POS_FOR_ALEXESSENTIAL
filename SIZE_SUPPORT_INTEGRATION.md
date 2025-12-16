# Size Support Integration Instructions

The size support system has been created but needs to be manually integrated into `index.php` due to file encoding issues.

## Files Created
- ✅ `js/pos_size_support.js` - Complete size handling JavaScript (already created)

## Manual Changes Needed in `index.php`

### 1. Add Size Data to Product Cards (around line 812-823)

**Find this section:**
```php
<div class="product-card" data-id="<?= $product['id'] ?>"
    data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>"
    data-stock="<?= $product['quantity'] ?>" data-category="<?= $product['category_id'] ?>"
    onclick="addToCart(this)">
```

**Add** `data-size` attribute:
```php
<div class="product-card" data-id="<?= $product['id'] ?>"
    data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>"
    data-stock="<?= $product['quantity'] ?>" data-category="<?= $product['category_id'] ?>"
    data-size="<?= htmlspecialchars($product['size'] ?? '') ?>"
    onclick="addToCart(this)">
```

### 2. Show Size on Product Cards (around line 817-818)

**Find:**
```php
<div class="product-price">₱ <?= number_format($product['price'], 2) ?></div>
<div class="product-stock <?= $product['quantity'] < 10 ? 'low' : '' ?>">
```

**Add size display between them:**
```php
<div class="product-price">₱ <?= number_format($product['price'], 2) ?></div>
<?php if (!empty($product['size'])): ?>
    <div class="product-size">📏 <?= htmlspecialchars($product['size']) ?></div>
<?php endif; ?>
<div class="product-stock <?= $product['quantity'] < 10 ? 'low' : '' ?>">
```

### 3. Add CSS Styles (around line 775-780, before closing `</style>`)

**Add this CSS before `</style>`:**
```css
/* Product Size Display */
.product-size {
    color: #64748b;
    font-size: 11px;
    margin-top: 4px;
    font-style: italic;
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

.cart-item-size {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}
```

### 4. Include JavaScript File (right after closing `</style>`)

**Add:**
```html
</style>
<script src="js/pos_size_support.js"></script>
</head>
```

## How It Works

Once integrated:
1. Products with sizes will show a 📏 icon with the size options
2. Clicking a product with multiple sizes (e.g., "S, M, L") opens a modal to select size
3. The selected size is shown in the cart
4. Each size variant is treated as a separate cart item

## Testing

1. Add a product with a size field (e.g., "Small, Medium, Large")
2. Click the product in the cashier interface
3. You should see a size selection modal
4. After selecting, the size will appear in the cart

Would you like me to create a complete replacement `index.php` file with all changes integrated?
