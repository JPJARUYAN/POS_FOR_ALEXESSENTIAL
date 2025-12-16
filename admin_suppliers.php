<?php
// Guard
require_once '_guards.php';
Guard::adminOnly();

require_once 'models/Supplier.php';

// Ensure suppliers table exists
Supplier::ensureSuppliersTable();

// Ensure supplier_id column exists in products table
function ensureSupplierIdColumn() {
    global $connection;
    try {
        // Check if column exists
        $stmt = $connection->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'supplier_id'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            // Column doesn't exist, create it
            $connection->exec("ALTER TABLE products ADD COLUMN supplier_id INT DEFAULT NULL AFTER cost");
            $connection->exec("ALTER TABLE products ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL");
        }
    } catch (Exception $e) {
        // Column likely exists already, continue
        error_log("Note: supplier_id column check/creation: " . $e->getMessage());
    }
}

ensureSupplierIdColumn();

$suppliers = Supplier::all();

$supplier = null;
if (get('action') === 'update') {
    $supplier = Supplier::find(get('id'));
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Suppliers</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <style>
        .supplier-layout {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .supplier-form {
            flex: 0 0 calc(35% - 8px);
            min-width: 300px;
        }

        .supplier-table {
            flex: 1;
            min-width: 400px;
        }

        @media (max-width: 900px) {
            .supplier-form,
            .supplier-table {
                width: 100% !important;
                flex: 1 !important;
            }
            .supplier-layout {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">🏢 Suppliers</h1>
                <p style="margin: 0; opacity: 0.9; font-size: 1em;">Manage your supplier relationships and sourcing</p>
            </div>
            <div class="supplier-layout">
                <!-- Form Section -->
                <div class="supplier-form">
                    <span class="subtitle">
                        <?php if (get('action') === 'update') : ?>
                            Update Supplier
                        <?php else : ?>
                            New Supplier
                        <?php endif; ?>
                    </span>
                    <hr/>

                    <div class="card">
                        <div class="card-content">
                            <form id="supplierForm" method="POST" action="api/supplier_controller.php">

                                <input type="hidden" id="s_action" name="action" value="add" />
                                <input type="hidden" id="s_id" name="id" value="" />

                                <div class="form-control">
                                    <label>Supplier Name</label>
                                    <input 
                                        id="s_name"
                                        type="text" 
                                        name="name" 
                                        placeholder="Enter supplier name here" 
                                        required="true" 
                                    />
                                </div>

                                <div class="form-control mt-12">
                                    <label>Contact Person</label>
                                    <input 
                                        id="s_contact"
                                        type="text" 
                                        name="contact_person" 
                                        placeholder="Contact person name" 
                                    />
                                </div>

                                <div class="grid grid-3 gap-16 mt-12">
                                    <div class="form-control">
                                        <label>Phone</label>
                                        <input 
                                            id="s_phone"
                                            type="text" 
                                            name="phone" 
                                            placeholder="+1 (555) 123-4567" 
                                        />
                                    </div>
                                    <div class="form-control">
                                        <label>Email</label>
                                        <input 
                                            id="s_email"
                                            type="email" 
                                            name="email" 
                                            placeholder="info@supplier.com" 
                                        />
                                    </div>
                                    <div class="form-control">
                                        <label>Address</label>
                                        <input 
                                            id="s_address"
                                            type="text" 
                                            name="address" 
                                            placeholder="Address" 
                                        />
                                    </div>
                                </div>

                                <div class="mt-16">
                                    <button id="s_submit" class="btn btn-primary w-full" type="submit">Add Supplier</button>
                                    <button id="s_cancel" class="btn w-full" type="button" style="margin-top: 8px; display: none;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- List Section -->
                <div class="supplier-table">
                    <span class="subtitle">Supplier List</span>
                    <hr/>

                    <?php displayFlashMessage('add_supplier') ?>
                    <?php displayFlashMessage('update_supplier') ?>
                    <?php displayFlashMessage('delete_supplier') ?>

                    <div class="table-responsive">
                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                            <input 
                                id="s_search" 
                                type="text"
                                placeholder="Search suppliers..." 
                                style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                            />
                            <button id="s_refresh" class="btn">Refresh</button>
                        </div>

                        <?php if (count($suppliers) === 0): ?>
                            <p style="text-align: center; color: #999; padding: 20px;">No suppliers found. Add one to get started.</p>
                        <?php else: ?>
                            <table id="suppliersTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Products</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="suppliersTbody">
                                    <?php foreach($suppliers as $s) : ?>
                                        <?php 
                                            // Get products for this supplier with error handling
                                            $productStr = 'None';
                                            try {
                                                $products = $connection->prepare("SELECT name FROM products WHERE supplier_id = :supplier_id ORDER BY name");
                                                $products->bindParam(':supplier_id', $s->id);
                                                $products->execute();
                                                $productList = $products->fetchAll(PDO::FETCH_ASSOC);
                                                $productNames = array_map(function($p) { return $p['name']; }, $productList);
                                                $productStr = count($productNames) > 0 ? implode(', ', $productNames) : 'None';
                                            } catch (PDOException $e) {
                                                $productStr = 'N/A';
                                            }
                                        ?>
                                    <tr data-id="<?= $s->id ?>" data-name="<?= htmlspecialchars($s->name, ENT_QUOTES) ?>" data-contact="<?= htmlspecialchars($s->contact_person ?? '', ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($s->phone ?? '', ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($s->email ?? '', ENT_QUOTES) ?>" data-address="<?= htmlspecialchars($s->address ?? '', ENT_QUOTES) ?>">>
                                        <td><?= htmlspecialchars($s->name) ?></td>
                                        <td><?= htmlspecialchars($s->contact_person ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s->phone ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s->email ?? '-') ?></td>
                                        <td style="font-size: 0.9em; color: #666;"><?= htmlspecialchars($productStr) ?></td>
                                        <td>
                                            <button class="btn s-edit" data-id="<?= $s->id ?>">Edit</button>
                                            <button class="btn s-delete" data-id="<?= $s->id ?>" style="margin-left: 4px;">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </main>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const form = document.getElementById('supplierForm');
        const actionInput = document.getElementById('s_action');
        const idInput = document.getElementById('s_id');
        const nameInput = document.getElementById('s_name');
        const contactInput = document.getElementById('s_contact');
        const phoneInput = document.getElementById('s_phone');
        const emailInput = document.getElementById('s_email');
        const addressInput = document.getElementById('s_address');
        const submitBtn = document.getElementById('s_submit');
        const cancelBtn = document.getElementById('s_cancel');
        const tbody = document.getElementById('suppliersTbody');
        const search = document.getElementById('s_search');
        const refresh = document.getElementById('s_refresh');

        function showMessage(msg, kind='info'){
            alert(msg);
        }

        form.addEventListener('submit', async function(e){
            e.preventDefault();
            const name = nameInput.value.trim();
            if (!name) { showMessage('Name is required','error'); return; }

            const fd = new FormData();
            fd.append('action', actionInput.value || 'add');
            if (idInput.value) fd.append('id', idInput.value);
            fd.append('name', name);
            fd.append('contact_person', contactInput.value.trim());
            fd.append('phone', phoneInput.value.trim());
            fd.append('email', emailInput.value.trim());
            fd.append('address', addressInput.value.trim());

            try{
                const res = await fetch('api/supplier_controller.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                const text = await res.text();
                console.log('Response:', res.status, text);
                
                let json = {};
                try { json = JSON.parse(text); } catch(e) { }
                
                if (!res.ok) {
                    const errMsg = (json && json.errors && Array.isArray(json.errors)) ? json.errors.join('; ') : (json.error || 'Failed');
                    throw new Error(errMsg);
                }

                if (actionInput.value === 'update'){
                    // update existing row
                    const tr = tbody.querySelector(`tr[data-id="${json.id}"]`);
                    if (tr){
                        tr.dataset.name = json.name;
                        tr.dataset.contact = contactInput.value.trim();
                        tr.dataset.phone = phoneInput.value.trim();
                        tr.dataset.email = emailInput.value.trim();
                        tr.dataset.address = addressInput.value.trim();
                        tr.querySelector('td:nth-child(1)').textContent = json.name;
                        tr.querySelector('td:nth-child(2)').textContent = contactInput.value.trim();
                        tr.querySelector('td:nth-child(3)').textContent = phoneInput.value.trim();
                        tr.querySelector('td:nth-child(4)').textContent = emailInput.value.trim();
                    }
                    showMessage('Supplier updated','info');
                } else {
                    // append new row
                    const id = json.id || json.id === 0 ? json.id : (new Date().getTime());
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-id', id);
                    tr.setAttribute('data-name', name);
                    tr.setAttribute('data-contact', contactInput.value.trim());
                    tr.setAttribute('data-phone', phoneInput.value.trim());
                    tr.setAttribute('data-email', emailInput.value.trim());
                    tr.setAttribute('data-address', addressInput.value.trim());
                    tr.innerHTML = `<td style="font-weight: 600; color: #1e3c72;">${name}</td><td>${contactInput.value.trim()}</td><td>${phoneInput.value.trim()}</td><td>${emailInput.value.trim()}</td><td><button class="btn-edit s-edit" data-id="${id}">Edit</button><button class="btn-delete s-delete" data-id="${id}">Delete</button></td>`;
                    tbody.appendChild(tr);
                    showMessage('Supplier added','info');
                }

                // reset form
                actionInput.value = 'add';
                idInput.value = '';
                nameInput.value = '';
                contactInput.value = '';
                phoneInput.value = '';
                emailInput.value = '';
                addressInput.value = '';
                submitBtn.textContent = 'Add Supplier';
                cancelBtn.style.display = 'none';

            } catch (err){
                console.error(err);
                showMessage(err.message || 'Error','error');
            }
        });

        // Delegate edit/delete
        document.addEventListener('click', async function(e){
            const edit = e.target.closest('.s-edit');
            if (edit){
                const id = edit.dataset.id;
                const tr = tbody.querySelector(`tr[data-id="${id}"]`);
                if (!tr) return;
                actionInput.value = 'update';
                idInput.value = id;
                nameInput.value = tr.dataset.name || tr.children[0].textContent;
                contactInput.value = tr.dataset.contact || tr.children[1].textContent;
                phoneInput.value = tr.dataset.phone || tr.children[2].textContent;
                emailInput.value = tr.dataset.email || tr.children[3].textContent;
                addressInput.value = tr.dataset.address || '';
                submitBtn.textContent = 'Update Supplier';
                cancelBtn.style.display = 'inline-block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            const del = e.target.closest('.s-delete');
            if (del){
                const id = del.dataset.id;
                if (!confirm('Delete this supplier?')) return;
                try{
                    const res = await fetch(`api/supplier_controller.php?action=delete&id=${id}`, { headers: { 'Accept': 'application/json' } });
                    const text = await res.text();
                    console.log('Delete response:', res.status, text);
                    
                    let json = {};
                    try { json = JSON.parse(text); } catch(e) { }
                    
                    if (!res.ok) {
                        const errMsg = (json && json.errors && Array.isArray(json.errors)) ? json.errors.join('; ') : (json.error || 'Failed');
                        throw new Error(errMsg);
                    }
                    const tr = tbody.querySelector(`tr[data-id="${id}"]`);
                    if (tr) tr.remove();
                    showMessage('Supplier deleted','info');
                } catch (err){
                    console.error(err);
                    showMessage(err.message || 'Error deleting','error');
                }
                return;
            }
        });

        cancelBtn.addEventListener('click', function(){
            actionInput.value = 'add';
            idInput.value = '';
            nameInput.value = '';
            contactInput.value = '';
            phoneInput.value = '';
            emailInput.value = '';
            addressInput.value = '';
            submitBtn.textContent = 'Add Supplier';
            cancelBtn.style.display = 'none';
        });

        search.addEventListener('input', function(){
            const q = this.value.toLowerCase().trim();
            Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
                const name = (tr.dataset.name||'').toLowerCase();
                const contact = (tr.dataset.contact||'').toLowerCase();
                const phone = (tr.dataset.phone||'').toLowerCase();
                const email = (tr.dataset.email||'').toLowerCase();
                const visible = name.includes(q) || contact.includes(q) || phone.includes(q) || email.includes(q);
                tr.style.display = visible ? '' : 'none';
            });
        });

        refresh.addEventListener('click', function(){
            window.location.reload();
        });
    });
    </script>
    </div>
</body>
</html>
