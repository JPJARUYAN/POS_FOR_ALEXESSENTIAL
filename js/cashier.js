document.addEventListener('alpine:init', function () {

    Alpine.data('products', function (products) {



        return {
            products,

            payment: 0,

            carts: [],

            init() {
                console.debug('cashier:init - products count=', (this.products || []).length)
                this.$watch('carts', () => {
                    console.debug('cashier: carts changed', this.carts)
                    this.$refs.change.innerText = '--'
                })
            },

            validate: function (e) {
                // kept for non-AJAX fallback, but primary submit uses submitOrder
                let change = this.calculateChange();
                if (change < 0 || this.carts.length == 0) e.preventDefault()
            },

            submitOrder: async function (e) {
                // e is prevented by @submit.prevent in template
                if (this.carts.length === 0) return alert('Cart is empty');

                const change = this.calculateChange();
                if (change < 0) return alert('Insufficient payment');

                // build FormData similar to regular form
                const fd = new FormData();
                fd.append('action', 'proccess_order');

                this.carts.forEach((cart, i) => {
                    fd.append(`cart_item[${i}][id]`, cart.product.id);
                    fd.append(`cart_item[${i}][quantity]`, cart.quantity);
                    if (cart.size) fd.append(`cart_item[${i}][size]`, cart.size);
                });

                try {
                    const res = await fetch('api/cashier_controller.php', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: fd
                    });

                    let json;
                    try {
                        json = await res.json();
                    } catch (parseErr) {
                        const text = await res.text();
                        console.error('Server returned non-JSON response:', text);
                        alert('Failed to process order: unexpected server response');
                        return;
                    }

                    if (!json || !json.success) {
                        const errMsg = (json && json.error) ? json.error : 'Failed to process order';
                        alert(errMsg);
                        return;
                    }

                    // Generate receipt and auto-download PDF
                    const orderInfo = { id: json.order_id, created_at: json.created_at };
                    await generateReceiptPdf(orderInfo, this.carts, this.payment, change);

                    // Clear cart and reset payment
                    this.carts = [];
                    this.payment = 0;
                    this.$refs.change.innerText = '--';

                    // show simple confirmation
                    alert('Transaction successful — receipt downloaded');

                } catch (err) {
                    console.error(err);
                    alert('Error processing order');
                }
            },

            calculateChange: function () {
                let change = parseFloat(this.payment || 0) - parseFloat(this.totalPrice || 0)

                // display to user (no intrusive alert here)
                if (change < 0) {
                    this.$refs.change.innerText = '-' + Math.abs(change).toFixed(2) + ' PHP'
                } else {
                    this.$refs.change.innerText = change.toFixed(2) + ' PHP'
                }

                return change;
            },

            appendDigit: function (digit) {
                // Append digits to the payment field to allow keypad input
                let val = String(this.payment || '');
                // if payment is 0 or empty, replace; otherwise append
                if (val === '0' || val === '') {
                    this.payment = isNaN(Number(digit)) ? 0 : Number(digit);
                } else {
                    // Append as string then parse to number
                    const newVal = (val + String(digit)).replace(/^0+(?=\d)/, '');
                    this.payment = Number(newVal);
                }
            },

            clearPayment: function () {
                this.payment = 0;
                this.$refs.change.innerText = '--';
            },

            get totalPrice() {
                return this.carts.reduce((acm, cart) => {
                    return acm + (cart.quantity * cart.product.price)
                }, 0)
            },

            subtractQuantity: function (cart) {
                cart.quantity--
                if (cart.quantity < 1) {
                    this.carts = this.carts.filter(_cart => !(_cart.product.id == cart.product.id && (_cart.size || '') === (cart.size || '')))
                }
            },

            addQuantity: function (cart) {
                if (cart.quantity < cart.product.quantity) {
                    cart.quantity++
                }
            },

            addToCart: function (id) {
                console.debug('addToCart called with id=', id)

                let product = products.find(product => product.id == id)
                console.debug('resolved product=', product)
                // Determine selected size first so we can detect existing cart entries
                let selectedSize = '';
                if (product.size && product.size.indexOf(',') !== -1) {
                    const options = product.size.split(',').map(s => s.trim()).filter(Boolean);
                    selectedSize = prompt('Select size:\n' + options.join('\n')) || '';
                    if (selectedSize && options.indexOf(selectedSize) === -1) {
                        alert('Invalid size selected');
                        return;
                    }
                } else if (product.size) {
                    selectedSize = product.size.trim();
                }

                let cart = this.carts.find(cart => cart.product.id == id && (cart.size || '') === (selectedSize || ''))

                if (product.quantity < 1) return alert('Out of stock');


                if (cart) {
                    if (cart.quantity < product.quantity) {
                        cart.quantity++
                    }
                } else {
                    this.carts.push({
                        product: products.find(product => product.id == id),
                        quantity: 1,
                        size: selectedSize
                    })
                    console.debug('cart after push', this.carts)
                }
            }
        }

        // helper: generate receipt PDF from server
        async function generateReceiptPdf(orderInfo, carts, payment, change) {
            try {
                // Request PDF from server
                const fd = new FormData();
                fd.append('order_id', orderInfo.id);

                const resp = await fetch('api/generate_receipt_pdf.php', {
                    method: 'POST',
                    body: fd
                });

                if (!resp.ok) {
                    const errText = await resp.text();
                    console.error('Server error:', resp.status, errText);
                    alert('Failed to generate receipt');
                    return;
                }

                const blob = await resp.blob();
                if (blob.size === 0) {
                    console.error('Received empty PDF blob');
                    alert('Receipt PDF is empty');
                    return;
                }

                // trigger client download
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `receipt_${orderInfo.id}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);

                console.debug('Receipt PDF generated and downloaded:', orderInfo.id);

            } catch (e) {
                console.error('Failed to generate receipt PDF:', e);
                alert('Error generating receipt: ' + e.message);
            }
        }

    })

})
