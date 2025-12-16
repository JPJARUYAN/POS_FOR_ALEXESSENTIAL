<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

$product = Guard::hasModel(Product::class);
$categories = Category::all();
// Ensure templates use the admin user when rendering this admin page
$currentUser = User::getAuthenticatedUser(ROLE_ADMIN);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Update Product</title>
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
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">✏️ Update Product</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Modify product details, pricing, and inventory information.</p>
                </div>

                <div class="w-40p">
                    <div class="subtitle">Update Product</div>
                    <hr/>

                    <div class="card">
                        <div class="card-content">
                            <form method="POST" action="api/product_controller.php?action=update">

                                <?php displayFlashMessage('edit_product') ?>

                                <input type="hidden" name="id" value="<?= $product->id ?>" />

                                <div class="form-control">
                                    <label>Name</label>
                                    <input 
                                        value="<?= $product->name ?>" 
                                        type="text" 
                                        name="name" 
                                        required="" 
                                    />
                                </div>

                                <div class="form-control mt-16">
                                    <label>Category</label>
                                    <select name="category_id" required="">
                                        <option value=""> -- Select Category --</option>
                                        <?php foreach ($categories as $category) : ?>
                                            <option 
                                                value="<?= $category->id ?>"
                                                <?= $category->id === $product->category_id ? 'selected' : '' ?>
                                                ><?= $category->name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-3 gap-16 mt-16">
                                    <div class="form-control">
                                        <label>Quantity</label>
                                        <input 
                                            value="<?= $product->quantity ?>" 
                                            required="" 
                                            type="number" 
                                            step="1" 
                                            min="0" 
                                            name="quantity" 
                                            id="up_quantity"
                                        />
                                    </div>
                                    <div class="form-control">
                                        <label>Price (incl. VAT)</label>
                                        <input 
                                            value="<?= $product->price ?>" 
                                            required="" 
                                            type="number" 
                                            step=".01" 
                                            name="price" 
                                            id="up_price"
                                            readonly
                                        />
                                        <div class="muted" style="font-size:0.85em; margin-top:4px;">Selling price automatically includes VAT.</div>
                                    </div>
                                    <div class="form-control">
                                        <label>Cost</label>
                                        <input 
                                            value="<?= $product->cost ?>" 
                                            type="number" 
                                            step=".25" 
                                            min="0" 
                                            name="cost" 
                                            id="up_cost"
                                        />
                                        <div style="display:flex; gap:8px; margin-top:8px; align-items:center;">
                                            <label style="font-size:0.85em; margin:0;">Profit %</label>
                                            <input type="number" step="0.1" min="0" name="profit_percent" id="up_profit" required placeholder="e.g. 25" style="width:100px;" />
                                            <div class="muted" style="font-size:0.85em;">Price = (Cost + Profit) + VAT</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-2 gap-16 mt-16">
                                    <div class="form-control">
                                        <label>Size</label>
                                        <input 
                                            value="<?= htmlspecialchars($product->size) ?>" 
                                            type="text" 
                                            name="size" 
                                            placeholder="e.g. S, M, L, XL" 
                                            id="up_size"
                                            required
                                        />
                                        <div class="muted" style="font-size:0.85em; margin-top:4px;">Separate multiple sizes with commas (e.g., Small, Medium, Large)</div>
                                    </div>
                                </div>

                                <div class="mt-16">
                                    <button class="btn btn-primary w-full" type="submit">Update Product</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

<script>
// Auto-populate size field based on category selection
document.addEventListener('DOMContentLoaded', function(){
    const sizeInput = document.querySelector('input[name="size"]');
    const categorySelect = document.querySelector('select[name="category_id"]');
    if (!sizeInput || !categorySelect) return;
    const categoryDefaults = {
        'footwear': '6, 7, 8, 9, 10, 11, 12, 13',
        'pants': '28, 30, 32, 34, 36, 38, 40',
        'shorts': '28, 30, 32, 34, 36, 38, 40',
        'shirts': 'S, M, L, XL, XXL',
        'jackets': 'S, M, L, XL, XXL'
    };
    // default profit percentage per category (example values)
    const categoryProfitDefaults = {
        'footwear': 35,
        'pants': 40,
        'shorts': 40,
        'shirts': 50,
        'jackets': 50
    };
    categorySelect.addEventListener('change', function(){
        const selectedCat = categorySelect.options[categorySelect.selectedIndex];
        const catName = selectedCat.text.toLowerCase().trim();
        for (const [key, defaultSize] of Object.entries(categoryDefaults)){
            if (catName.includes(key)){
                sizeInput.value = defaultSize;
                // apply default profit if available
                const profitInput = document.getElementById('up_profit');
                if (profitInput && categoryProfitDefaults[key] !== undefined) {
                    profitInput.value = categoryProfitDefaults[key];
                    // if cost exists, compute price
                    const costInput = document.getElementById('up_cost');
                    const priceInput = document.getElementById('up_price');
                    if (costInput && priceInput && costInput.value) {
                        const cost = parseFloat(costInput.value || 0);
                        const pct = parseFloat(categoryProfitDefaults[key]);
                        const newPrice = cost + (cost * (pct / 100));
                        priceInput.value = (Math.round(newPrice * 100) / 100).toFixed(2);
                    }
                }
                return;
            }
        }
    });

    // keep price and profit in sync on update page (with VAT)
    const upCost = document.getElementById('up_cost');
    const upProfit = document.getElementById('up_profit');
    const upPrice = document.getElementById('up_price');
    const TAX_RATE = 12; // percent
    if (upCost && upProfit && upPrice) {
        function updatePriceFromProfitUp(){
            const cost = parseFloat(upCost.value || 0);
            const profit = parseFloat(upProfit.value || 0);
            if (!isNaN(cost) && !isNaN(profit)) {
                const base = cost + (cost * (profit / 100)); // cost + profit
                const priceWithVat = base * (1 + (TAX_RATE / 100));
                upPrice.value = (Math.round(priceWithVat * 100) / 100).toFixed(2);
            }
        }
        function updateProfitFromPriceUp(){
            const cost = parseFloat(upCost.value || 0);
            const price = parseFloat(upPrice.value || 0);
            if (!isNaN(cost) && cost > 0 && !isNaN(price)) {
                const base = price / (1 + (TAX_RATE / 100)); // remove VAT
                const pct = ((base - cost) / cost) * 100;
                upProfit.value = (Math.round(pct * 10) / 10).toFixed(1);
            }
        }
        upCost.addEventListener('input', updatePriceFromProfitUp);
        upProfit.addEventListener('input', updatePriceFromProfitUp);
        upPrice.addEventListener('input', updateProfitFromPriceUp);
    }
});
</script>

</body>
</html>
