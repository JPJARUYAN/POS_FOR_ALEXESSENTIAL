/**
 * Enhanced POS Functions with Size Support
 * This file overrides the cart functions to add product sizing support.
 * It relies on the existing global `cart` array that is defined in `index.php`.
 */
let sizeModalResolve = null;

// Enhanced addToCart with size support
async function addToCart(productElement) {
    const product = {
        id: parseInt(productElement.dataset.id),
        name: productElement.dataset.name,
        price: parseFloat(productElement.dataset.price),
        stock: parseInt(productElement.dataset.stock),
        sizeOptions: productElement.dataset.size || '',
        sizeStocks: {}
    };

    // Parse size stocks if available
    try {
        const sizeStocksJson = productElement.dataset.sizeStocks || '{}';
        product.sizeStocks = JSON.parse(sizeStocksJson);
    } catch (e) {
        product.sizeStocks = {};
    }

    // Handle size selection if product has sizes
    let selectedSize = '';
    if (product.sizeOptions && product.sizeOptions.trim()) {
        const sizes = product.sizeOptions.split(',').map(s => s.trim()).filter(Boolean);

        if (sizes.length > 1) {
            // Multiple sizes - show selection modal with stock info
            selectedSize = await promptForSize(sizes, product.name, product.sizeStocks);
            if (!selectedSize) return; // User cancelled
        } else if (sizes.length === 1) {
            // Single size
            selectedSize = sizes[0];
        }
    }

    // Get stock for selected size (or total stock if no size-specific data)
    let availableStock = product.stock;
    if (selectedSize && product.sizeStocks[selectedSize] !== undefined) {
        availableStock = product.sizeStocks[selectedSize] || 0;
    }

    // Find existing item with same id AND size
    const existingItem = cart.find(item =>
        item.id === product.id && (item.size || '') === (selectedSize || '')
    );

    if (existingItem) {
        // Check stock per size if available
        let itemStock = existingItem.stock;
        if (selectedSize && product.sizeStocks[selectedSize] !== undefined) {
            itemStock = product.sizeStocks[selectedSize] || 0;
        }
        
        if (existingItem.quantity < itemStock) {
            existingItem.quantity++;
        } else {
            showToast('Not enough stock for size ' + selectedSize + '!', 'error');
            return;
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            stock: availableStock, // Store size-specific stock
            quantity: 1,
            size: selectedSize
        });
    }

    renderCart();
    showToast('Added to cart', 'success');
}

// Helper function to sort sizes intelligently
function sortSizes(sizes) {
    return sizes.slice().sort((a, b) => {
        const sizeA = a.trim();
        const sizeB = b.trim();
        
        const isNumericA = !isNaN(sizeA) && !isNaN(parseFloat(sizeA));
        const isNumericB = !isNaN(sizeB) && !isNaN(parseFloat(sizeB));
        
        if (isNumericA && isNumericB) {
            return parseFloat(sizeA) - parseFloat(sizeB);
        } else if (isNumericA) {
            return -1;
        } else if (isNumericB) {
            return 1;
        } else {
            const sizeOrder = {
                'XS': 1, 'S': 2, 'M': 3, 'L': 4, 
                'XL': 5, 'XXL': 6, 'XXXL': 7,
                'XXS': 0.5
            };
            const orderA = sizeOrder[sizeA.toUpperCase()] ?? 999;
            const orderB = sizeOrder[sizeB.toUpperCase()] ?? 999;
            if (orderA !== 999 || orderB !== 999) {
                return orderA - orderB;
            }
            return sizeA.localeCompare(sizeB);
        }
    });
}

// Prompt for size selection with stock information
function promptForSize(sizes, productName, sizeStocks = {}) {
    return new Promise((resolve) => {
        // Sort sizes before displaying
        const sortedSizes = sortSizes(sizes);
        
        // Filter out sizes with 0 stock
        const availableSizes = sortedSizes.filter(size => {
            const stock = sizeStocks[size] !== undefined ? sizeStocks[size] : null;
            // Include size if stock info is unavailable OR if stock > 0
            return stock === null || stock > 0;
        });
        
        // If no sizes available, cancel the selection
        if (availableSizes.length === 0) {
            showToast('All sizes are out of stock', 'error');
            resolve(null);
            return;
        }
        
        const sizeOptions = availableSizes.map(size => {
            const stock = sizeStocks[size] !== undefined ? sizeStocks[size] : null;
            const stockDisplay = stock !== null ? ` (${stock} available)` : '';
            return `<div class="size-option" onclick="selectSizeOption('${size.replace(/'/g, "\\'")}')">
                <div style="font-weight: 600;">${size}</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">${stock !== null ? stock + ' available' : 'Stock info unavailable'}</div>
            </div>`;
        }).join('');

        const modalHtml = `
            <div class="modal active" id="sizeModal" onclick="if(event.target === this) closeSizeModal(false)">
                <div class="modal-content" style="max-width: 450px;">
                    <div class="modal-header">
                        <h3>&#128204; Select Size</h3>
                        <button class="modal-close" onclick="closeSizeModal(false)">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom: 16px; color: #cbd5e1;">Choose size for <strong>${productName}</strong>:</p>
                        <div class="size-options">${sizeOptions}</div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Store resolve function
        sizeModalResolve = resolve;
    });
}

function selectSizeOption(size) {
    if (sizeModalResolve) {
        sizeModalResolve(size);
        sizeModalResolve = null;
    }
    closeSizeModal(true);
}

function closeSizeModal(wasSelected) {
    const modal = document.getElementById('sizeModal');
    if (modal) {
        modal.remove();
    }
    if (!wasSelected && sizeModalResolve) {
        sizeModalResolve(null);
        sizeModalResolve = null;
    }
}

// Enhanced updateQuantity with size support
function updateQuantity(productId, change, size = '') {
    const item = cart.find(i => i.id === productId && (i.size || '') === (size || ''));
    if (!item) return;

    item.quantity += change;

    if (item.quantity <= 0) {
        removeFromCart(productId, size);
    } else if (item.quantity > item.stock) {
        item.quantity = item.stock;
        const sizeText = size ? ' for size ' + size : '';
        showToast('Maximum stock reached' + sizeText, 'error');
    }

    renderCart();
}

// Enhanced removeFromCart with size support  
function removeFromCart(productId, size = '') {
    cart = cart.filter(i => !(i.id === productId && (i.size || '') === (size || '')));
    renderCart();
    showToast('Item removed', 'success');
}

// Enhanced renderCart with size display
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
        totalEl.innerHTML = '&#8369; 0.00';
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

        const sizeDisplay = item.size ? `<div class="cart-item-size">&#128207; ${item.size}</div>` : '';

        html += `
            <div class="cart-item">
                <div class="cart-item-header">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        ${sizeDisplay}
                    </div>
                    <button class="cart-item-remove" onclick="removeFromCart(${item.id}, '${item.size || ''}')">&times;</button>
                </div>
                <div class="cart-item-footer">
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, -1, '${item.size || ''}')">&minus;</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, 1, '${item.size || ''}')">+</button>
                    </div>
                    <div class="cart-item-subtotal">&#8369; ${subtotal.toFixed(2)}</div>
                </div>
            </div>
        `;
    });

    cartItemsEl.innerHTML = html;
    totalEl.innerHTML = '&#8369; ' + total.toFixed(2);
    itemCountEl.textContent = itemCount;
    checkoutBtn.disabled = false;
}

// Enhanced processPayment to include sizes in cart items
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
        // Map cart items to include size
        const items = cart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            size: item.size || '' // Include size in the order
        }));

        const response = await fetch('api/cashier_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                items: items,
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

// Event delegation for product cards - handle async addToCart properly
document.addEventListener('DOMContentLoaded', function() {
    const productsGrid = document.getElementById('productsGrid');
    if (productsGrid) {
        productsGrid.addEventListener('click', function(event) {
            const productCard = event.target.closest('.product-card');
            if (productCard) {
                event.preventDefault();
                event.stopPropagation();
                addToCart(productCard);
            }
        });
    }
});

console.log('POS size support loaded successfully');
