<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();

$categories = Category::all();
require_once 'models/Supplier.php';
$suppliers = Supplier::all();
// Handle inline category update state when editing categories from this page
$category = null;
if (get('cat_action') === 'update') {
    $category = Category::find(get('cat_id'));
}
// Ensure templates use the admin user when rendering this admin page
$currentUser = User::getAuthenticatedUser(ROLE_ADMIN);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Add Item</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">
</head>
<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main class="dark-mock">
            <div class="wrapper" style="max-width: 1200px;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">➕ Add New Product</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Create and manage product inventory with pricing and stock allocation.</p>
                </div>

                <div class="grid gap-24" style="grid-template-columns: 2fr 1.1fr;">
                    <!-- Left: Add product form -->
                    <div>
                        <div class="subtitle">Add New Product</div>
                        <hr/>

                        <div class="card mt-8">
                            <div class="card-body">
                                <?php displayFlashMessage('add_product') ?>

                                <form id="productForm" method="POST" action="api/product_controller.php?action=add">

                                    <!-- Basic info -->
                                    <div class="form-control">
                                        <label>Product Name</label>
                                        <input type="text" name="name" id="p_name" required placeholder="e.g. Cargo Pants" />
                                    </div>

                                    <div class="form-control mt-12">
                                        <label>Category</label>
                                        <select name="category_id" id="p_category" required>
                                            <option value=""> -- Select Category --</option>
                                            <?php foreach ($categories as $category) : ?>
                                                <option value="<?= $category->id ?>"><?= $category->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="catSizeSuggestion" style="display:none; margin-top:8px; font-size:0.95em;">
                                            Suggested sizes: <strong id="suggestedSizesText"></strong>
                                            <button id="applySuggestedSizes" type="button" class="btn" style="margin-left:8px;">Apply</button>
                                        </div>
                                    </div>

                                    <div class="form-control mt-12">
                                        <label>Supplier</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <select name="supplier_id" id="p_supplier" style="flex:1;">
                                                <option value=""> -- Select Supplier (optional) --</option>
                                                <?php foreach ($suppliers as $s) : ?>
                                                    <option value="<?= $s->id ?>"><?= htmlspecialchars($s->name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button id="p_add_supplier_btn" type="button" class="btn">Add</button>
                                        </div>
                                        <div id="p_add_supplier_area" style="display:none; margin-top:8px;">
                                            <div style="display:flex; gap:8px;">
                                                <input id="p_new_supplier_name" placeholder="Supplier name" style="flex:1; padding:8px;" />
                                                <button id="p_new_supplier_save" class="btn btn-primary">Save</button>
                                                <button id="p_new_supplier_cancel" class="btn">Cancel</button>
                                            </div>
                                            <div id="p_new_supplier_feedback" class="muted" style="margin-top:6px; font-size:0.9em;"></div>
                                        </div>
                                    </div>

                                    <!-- Stock & pricing -->
                                    <div class="grid grid-3 gap-16 mt-16">
                                        <div class="form-control">
                                            <label>Quantity</label>
                                            <input type="number" name="quantity" id="p_quantity" value="0" min="0" required />
                                        </div>

                                        <div class="form-control">
                                            <label>Cost</label>
                                            <input type="number" step="0.01" name="cost" id="p_cost" required placeholder="0.00" />
                                        </div>

                                        <div class="form-control">
                                            <label>Profit %</label>
                                            <input type="number" step="0.1" min="0" name="profit_percent" id="p_profit" required placeholder="e.g. 20" />
                                        </div>
                                    </div>

                                    <div class="grid grid-2 gap-16 mt-16">
                                        <div class="form-control">
                                            <label>Price (incl. VAT)</label>
                                            <input type="number" step="0.01" name="price" id="p_price" readonly placeholder="0.00" />
                                            <div class="muted" style="font-size:0.85em; margin-top:4px;">Selling price automatically includes VAT.</div>
                                        </div>
                                        <div class="form-control">
                                            <label>Sizes & Stock</label>
                                            <input type="text" name="size" id="p_size" placeholder="S,M,L or 28,30,32" required />
                                            <div class="muted" style="font-size:0.85em; margin-top:4px;">Separate multiple sizes with commas (e.g., Small, Medium, Large)</div>
                                        </div>
                                    </div>

                                    <!-- Size and Stock allocation -->
                                    <div class="form-control mt-16">
                                        <label>Allocate Stock Per Size</label>
                                        <div id="sizeStockContainer" style="display:none; border: 1px solid rgba(148,163,184,0.2); border-radius:8px; padding:12px; background:rgba(148,163,184,0.05); margin-top:8px;">
                                            <table style="width:100%; border-collapse:collapse;">
                                                <thead>
                                                    <tr style="background:rgba(148,163,184,0.1);">
                                                        <th style="text-align:left; padding:8px; font-weight:600;">Size</th>
                                                        <th style="text-align:left; padding:8px; font-weight:600;">Quantity</th>
                                                        <th style="text-align:center; padding:8px; font-weight:600;">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sizeStockTable">
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background:rgba(148,163,184,0.05); font-weight:600; border-top: 1px solid rgba(148,163,184,0.2);">
                                                        <td style="padding:8px;">Total Stock:</td>
                                                        <td style="padding:8px;"><span id="totalStockCount">0</span></td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <div id="noSizesMessage" class="muted" style="font-size:0.85em; margin-top:8px;">Enter sizes above to allocate stock per size.</div>
                                    </div>

                                    <div class="mt-20">
                                        <button type="submit" class="btn btn-primary w-full">Add Product</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Category management -->
                    <div>
                        <div class="subtitle">Categories</div>
                        <hr/>
                        <div class="card mt-8">
                            <div class="card-body">
                                <div id="categoryFeedback"></div>
                                <form id="categoryForm">
                                    <input type="hidden" id="cat_action" name="action" value="add" />
                                    <input type="hidden" id="cat_id" name="id" value="" />
                                    <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                                        <input id="cat_name" name="name" placeholder="New category name" style="flex:1; padding:8px;" />
                                        <button class="btn btn-primary" type="submit">Save</button>
                                    </div>
                                </form>

                                <table id="categoryTable" style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; padding:8px;">Name</th>
                                            <th style="text-align:left; padding:8px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($categories as $cat) : ?>
                                            <tr data-cat-id="<?= $cat->id ?>" data-cat-name="<?= htmlspecialchars($cat->name, ENT_QUOTES) ?>" style="cursor:pointer;">
                                                <td style="padding:8px;"><?= htmlspecialchars($cat->name) ?></td>
                                                <td style="white-space:nowrap; padding:8px;">
                                                    <button class="btn cat-update-btn" data-cat-id="<?= $cat->id ?>" data-cat-name="<?= htmlspecialchars($cat->name, ENT_QUOTES) ?>">Update</button>
                                                    <button class="btn cat-delete-btn" style="margin-left:8px; background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.3); color:#fca5a5;" data-cat-id="<?= $cat->id ?>">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
// AJAX category handling
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('categoryForm');
    const actionInput = document.getElementById('cat_action');
    const idInput = document.getElementById('cat_id');
    const nameInput = document.getElementById('cat_name');
    const feedback = document.getElementById('categoryFeedback');
    const table = document.querySelector('#categoryTable tbody');
    const productCategorySelect = document.querySelector('select[name="category_id"]');

    function showMessage(msg, kind='info'){
        feedback.innerHTML = `<div class="muted" style="color:${ kind==='error' ? '#fca5a5' : '#cbd5e1' }; margin-bottom:8px;">${msg}</div>`;
        setTimeout(()=> feedback.innerHTML = '', 3000);
    }

    // Make category rows clickable: select category in product form when row (not buttons) is clicked
    table.addEventListener('click', function(e){
        // ignore clicks on buttons (update/delete)
        if (e.target.closest('button')) return;
        const tr = e.target.closest('tr[data-cat-id]');
        if (!tr) return;
        const cid = tr.dataset.catId;
        productCategorySelect.value = cid;
        productCategorySelect.dispatchEvent(new Event('change'));
        productCategorySelect.focus();
    });

    form.addEventListener('submit', async function(e){
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', actionInput.value || 'add');
        fd.append('name', nameInput.value.trim());
        if (idInput.value) fd.append('id', idInput.value);

        try{
            const res = await fetch('api/category_controller.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || 'Failed');

            // Update UI: replace table row if update, or append if add
            if (actionInput.value === 'update'){
                const row = table.querySelector(`tr[data-cat-id="${json.id}"]`);
                if (row){
                    row.setAttribute('data-cat-name', json.name);
                    row.querySelector('td').textContent = json.name;
                    row.querySelector('.cat-update-btn').dataset.catName = json.name;
                }
                showMessage('Category updated', 'info');
            } else {
                // append new row
                const tr = document.createElement('tr');
                tr.setAttribute('data-cat-id', json.id);
                tr.setAttribute('data-cat-name', json.name);
                tr.style.cursor = 'pointer';
                tr.innerHTML = `<td>${json.name}</td><td style="white-space:nowrap;"><button class="btn cat-update-btn" data-cat-id="${json.id}" data-cat-name="${json.name}">Update</button><button class="btn cat-delete-btn" style="margin-left:8px; background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.3); color:#fca5a5;" data-cat-id="${json.id}">Delete</button></td>`;
                table.appendChild(tr);
                showMessage('Category added', 'info');
            }

            // update product category select
            updateCategorySelect();

            // reset form
            actionInput.value = 'add';
            idInput.value = '';
            nameInput.value = '';

        } catch (err){
            console.error(err);
            showMessage(err.message || 'Error', 'error');
        }
    });

    // Delegate update/delete buttons
    document.addEventListener('click', async function(e){
        const up = e.target.closest('.cat-update-btn');
        if (up){
            e.preventDefault();
            const cid = up.dataset.catId;
            const cname = up.dataset.catName;
            actionInput.value = 'update';
            idInput.value = cid;
            nameInput.value = cname;
            nameInput.focus();
            return;
        }

        const del = e.target.closest('.cat-delete-btn');
        if (del){
            e.preventDefault();
            if (!confirm('Delete this category?')) return;
            const cid = del.dataset.catId;
            try{
                const res = await fetch(`api/category_controller.php?action=delete&id=${cid}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json.error || 'Failed');
                // remove row
                const row = table.querySelector(`tr[data-cat-id="${cid}"]`);
                if (row) row.remove();
                updateCategorySelect();
                showMessage('Category deleted', 'info');
            } catch (err){
                console.error(err);
                showMessage(err.message || 'Error', 'error');
            }
        }
    });

    function updateCategorySelect(){
        // rebuild options from table rows
        const rows = Array.from(table.querySelectorAll('tr'));
        productCategorySelect.innerHTML = '<option value=""> -- Select Category --</option>';
        rows.forEach(r=>{
            const id = r.dataset.catId;
            const name = r.dataset.catName;
            const opt = document.createElement('option');
            opt.value = id; opt.textContent = name;
            productCategorySelect.appendChild(opt);
        });
    }

    // Auto-populate size and default profit based on category selection
    const sizeInput = document.querySelector('input[name="size"]');
    const sizeCountSpan = document.getElementById('p_size_count');
    const categoryDefaults = {
        'footwear': '6, 7, 8, 9, 10, 11, 12, 13',
        'pants': '28, 30, 32, 34, 36, 38, 40',
        'shorts': '28, 30, 32, 34, 36, 38, 40',
        'shirts': 'S, M, L, XL, XXL',
        'jackets': 'S, M, L, XL, XXL'
    };
    const categoryProfitDefaults = {
        'footwear': 35,
        'pants': 40,
        'shorts': 40,
        'shirts': 50,
        'jackets': 50
    };
    
    productCategorySelect.addEventListener('change', function(){
        const selectedCat = productCategorySelect.options[productCategorySelect.selectedIndex];
        const catName = selectedCat.text.toLowerCase().trim();
        
        for (const [key, defaultSize] of Object.entries(categoryDefaults)){
            if (catName.includes(key)){
                if (sizeInput) {
                    sizeInput.value = defaultSize;
                }
                const profitInput = document.getElementById('p_profit');
                const costInput = document.getElementById('p_cost');
                const priceInput = document.getElementById('p_price');
                if (profitInput && categoryProfitDefaults[key] !== undefined) {
                    profitInput.value = categoryProfitDefaults[key];
                    if (costInput && priceInput && costInput.value) {
                        const cost = parseFloat(costInput.value || 0);
                        const pct = parseFloat(categoryProfitDefaults[key]);
                        const newPrice = cost + (cost * (pct / 100));
                        priceInput.value = (Math.round(newPrice * 100) / 100).toFixed(2);
                    }
                }
                // update size count when category changes predefined sizes
                if (sizeInput && sizeCountSpan) {
                    const parts = sizeInput.value.split(',').map(s => s.trim()).filter(Boolean);
                    sizeCountSpan.textContent = parts.length;
                }
                return;
            }
        }
        // show suggestion UI (do not overwrite existing sizes unless applied)
        showCategorySuggestion(catName);
    });

    function showCategorySuggestion(catName) {
        const suggestion = document.getElementById('catSizeSuggestion');
        const suggestedText = document.getElementById('suggestedSizesText');
        const applyBtn = document.getElementById('applySuggestedSizes');
        if (!catName) { suggestion.style.display = 'none'; return; }
        for (const [key, defaultSize] of Object.entries(categoryDefaults)){
            if (catName.includes(key)){
                suggestedText.textContent = defaultSize;
                suggestion.style.display = 'block';
                applyBtn.onclick = function(){
                    if (sizeInput) {
                        sizeInput.value = defaultSize;
                        sizeInput.dispatchEvent(new Event('input'));
                    }
                };
                return;
            }
        }
        suggestion.style.display = 'none';
    }

    // show suggestion for initial selection (if any)
    if (productCategorySelect && productCategorySelect.value) {
        const initialText = productCategorySelect.options[productCategorySelect.selectedIndex].text.toLowerCase().trim();
        showCategorySuggestion(initialText);
    }

    const productForm = document.getElementById('productForm');
        // sync price <-> profit logic with VAT
        const costInput = document.getElementById('p_cost');
        const profitInput = document.getElementById('p_profit');
        const priceInput = document.getElementById('p_price');
        const TAX_RATE = 12; // percent

        function updatePriceFromProfit() {
            const cost = parseFloat(costInput.value || 0);
            const profit = parseFloat(profitInput.value || 0);
            if (!isNaN(cost) && !isNaN(profit)) {
                const base = cost + (cost * (profit / 100)); // cost + profit
                const priceWithVat = base * (1 + (TAX_RATE / 100));
                priceInput.value = (Math.round(priceWithVat * 100) / 100).toFixed(2);
            }
        }

        function updateProfitFromPrice() {
            const cost = parseFloat(costInput.value || 0);
            const price = parseFloat(priceInput.value || 0);
            if (!isNaN(cost) && cost > 0 && !isNaN(price)) {
                // remove VAT first to get base (cost + profit)
                const base = price / (1 + (TAX_RATE / 100));
                const pct = ((base - cost) / cost) * 100;
                profitInput.value = (Math.round(pct * 10) / 10).toFixed(1);
            }
        }

        if (costInput) costInput.addEventListener('input', updatePriceFromProfit);
        if (profitInput) profitInput.addEventListener('input', updatePriceFromProfit);
        if (priceInput) priceInput.addEventListener('input', updateProfitFromPrice);

        // live size count and allocation table
        const sizeStockContainer = document.getElementById('sizeStockContainer');
        const noSizesMessage = document.getElementById('noSizesMessage');
        const sizeStockTable = document.getElementById('sizeStockTable');
        const totalStockCount = document.getElementById('totalStockCount');
        
        if (sizeInput) {
            const updateSizeAllocation = () => {
                const parts = sizeInput.value.split(',').map(s => s.trim()).filter(Boolean);
                sizeCountSpan.textContent = parts.length;
                
                // Update allocation table
                if (parts.length === 0) {
                    sizeStockContainer.style.display = 'none';
                    noSizesMessage.style.display = 'block';
                    return;
                }
                
                sizeStockContainer.style.display = 'block';
                noSizesMessage.style.display = 'none';
                
                // Build table rows for each size
                sizeStockTable.innerHTML = '';
                parts.forEach((size, idx) => {
                    const tr = document.createElement('tr');
                    if (idx % 2 === 0) tr.style.background = 'rgba(148,163,184,0.02)';
                    
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.min = '0';
                    input.value = '0';
                    input.className = 'size-qty-input';
                    input.style.cssText = 'width:100%; padding:6px; border:1px solid rgba(148,163,184,0.2); border-radius:4px;';
                    
                    tr.innerHTML = `
                        <td style="padding:8px;">${size}</td>
                        <td style="padding:8px;"></td>
                        <td style="padding:8px; text-align:center;"></td>
                    `;
                    tr.querySelector('td:nth-child(2)').appendChild(input);
                    input.addEventListener('input', updateTotalStock);
                    sizeStockTable.appendChild(tr);
                });
                
                updateTotalStock();
            };
            
            const updateTotalStock = () => {
                const inputs = document.querySelectorAll('.size-qty-input');
                let total = 0;
                inputs.forEach(input => {
                    total += parseInt(input.value || 0);
                });
                totalStockCount.textContent = total;
                
                // Also update the main quantity field
                document.getElementById('p_quantity').value = total;
            };
            
            sizeInput.addEventListener('input', updateSizeAllocation);
            updateSizeAllocation();
        }

        productForm.addEventListener('submit', function(e){
            e.preventDefault();
            // client-side validation
            const name = document.getElementById('p_name').value.trim();
            const category = document.getElementById('p_category').value;
            const qty = document.getElementById('p_quantity').value;
            const costVal = document.getElementById('p_cost').value;
            const profitVal = document.getElementById('p_profit').value;
            const sizeVal = document.getElementById('p_size').value.trim();

            if (!name || !category || qty === '' || costVal === '' || profitVal === '' || !sizeVal) {
                alert('Please fill Product Name, Category, Quantity, Size, Cost and Profit %.');
                return false;
            }

            // ensure price is up-to-date
            updatePriceFromProfit();

            const fd = new FormData(productForm);
            
            // Collect size-based quantities
            const sizeQtyPairs = [];
            const parts = sizeVal.split(',').map(s => s.trim()).filter(Boolean);
            const qtyInputs = document.querySelectorAll('.size-qty-input');
            
            parts.forEach((size, idx) => {
                const qty = qtyInputs[idx] ? parseInt(qtyInputs[idx].value || 0) : 0;
                if (qty > 0) {
                    sizeQtyPairs.push({ size: size, quantity: qty });
                }
            });
            
            // Attach size quantities as JSON
            fd.append('size_quantities', JSON.stringify(sizeQtyPairs));
            
            fetch('api/product_controller.php?action=add', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(resp => {
                    if (resp && resp.success) {
                        alert('Product added');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (resp && resp.message ? resp.message : 'Unknown'));
                    }
                }).catch(err => {
                    console.error(err);
                    alert('Error adding product');
                });
            return false;
        });

        // Inline supplier creation logic
        const addSupplierBtn = document.getElementById('p_add_supplier_btn');
        const addSupplierArea = document.getElementById('p_add_supplier_area');
        const newSupplierName = document.getElementById('p_new_supplier_name');
        const newSupplierSave = document.getElementById('p_new_supplier_save');
        const newSupplierCancel = document.getElementById('p_new_supplier_cancel');
        const newSupplierFeedback = document.getElementById('p_new_supplier_feedback');
        const supplierSelect = document.getElementById('p_supplier');

        addSupplierBtn.addEventListener('click', function(){
            addSupplierArea.style.display = addSupplierArea.style.display === 'none' ? 'block' : 'none';
            newSupplierName.focus();
        });

        newSupplierCancel.addEventListener('click', function(){
            addSupplierArea.style.display = 'none';
            newSupplierName.value = '';
            newSupplierFeedback.textContent = '';
        });

        newSupplierSave.addEventListener('click', async function(){
            const name = newSupplierName.value.trim();
            if (!name) { newSupplierFeedback.textContent = 'Name required'; return; }

            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('name', name);

            try{
                const res = await fetch('api/supplier_controller.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                const text = await res.text();
                console.log('Supplier response:', res.status, text);
                
                let json = {};
                try { json = JSON.parse(text); } catch(e) { }
                
                if (!res.ok) {
                    const err = (json && json.errors && json.errors.length > 0) ? json.errors.join('; ') : (json.error || 'Failed to add supplier');
                    newSupplierFeedback.textContent = err;
                    return;
                }

                // Append to select and select it
                const id = json.id;
                const option = document.createElement('option');
                option.value = id;
                option.textContent = json.name || name;
                supplierSelect.appendChild(option);
                supplierSelect.value = id;

                newSupplierFeedback.textContent = 'Supplier added';
                setTimeout(()=>{ newSupplierFeedback.textContent = ''; addSupplierArea.style.display = 'none'; newSupplierName.value = ''; }, 800);
            } catch (err){
                console.error('Error adding supplier:', err);
                newSupplierFeedback.textContent = 'Error: ' + err.message;
            }
        });
});
</script>

</body>
</html>