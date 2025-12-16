<?php
//Guard
require_once '_guards.php';
Guard::adminOnly();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Point of Sale System :: Account Management</title>
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/admin_dashboard.css">

    <!-- Datatables Library -->
    <link rel="stylesheet" type="text/css" href="./css/datatable.css">
    <script src="./js/datatable.js"></script>
    <script src="./js/main.js"></script>

    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            background-color: #0f172a;
            margin: 5% auto;
            padding: 0;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #e2e8f0;
        }

        .close {
            color: #94a3b8;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover,
        .close:focus {
            color: #e2e8f0;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #cbd5e1;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            background: #020617;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 4px;
            color: #e2e8f0;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 8px 16px;
            background: transparent;
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #94a3b8;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: rgba(148, 163, 184, 0.1);
            border-color: #94a3b8;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
    </style>
</head>

<body>
    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>
        <main>
            <div class="wrapper" style="max-width: 100%; width:100%; padding:0 16px;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">🔐 Account Management</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Manage cashier accounts, permissions, and user access control.</p>
                </div>

                <div style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-bottom: 24px;">
                    <p style="margin: 0; color: #3b82f6; font-size: 14px;"><strong>ℹ️ Note:</strong> User creation, editing, and deletion is now managed from the <a href="admin_users.php" style="color: #3b82f6; text-decoration: underline;">Users page</a> in the admin sidebar.</p>
                </div>

                <!-- Alert placeholder -->
                <div id="alertContainer"></div>

                <!-- Cashiers table -->
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-body">
                        <div
                            style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div class="subtitle" style="margin:0;">Cashier Accounts</div>
                        </div>

                        <div class="table-responsive">
                            <table id="cashiersTable" class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cashiersTableBody">
                                    <!-- Data loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Cashier Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Cashier</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addCashierForm" onsubmit="addCashier(event)">
                    <div class="form-group">
                        <label for="add_name">Full Name *</label>
                        <input type="text" id="add_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_email">Email Address *</label>
                        <input type="email" id="add_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="add_password">Password *</label>
                        <input type="password" id="add_password" name="password" required minlength="6">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                        <button type="submit" class="btn">Add Cashier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Cashier Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Cashier</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editCashierForm" onsubmit="updateCashier(event)">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_name">Full Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email Address *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password" minlength="6">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn">Update Cashier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        let dataTable;

        // Load cashiers on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadCashiers();
        });

        function loadCashiers() {
            fetch('api/user_controller.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCashiers(data.data);
                    } else {
                        showAlert('Error loading cashiers: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error loading cashiers: ' + error.message, 'error');
                });
        }

        function displayCashiers(cashiers) {
            const tbody = document.getElementById('cashiersTableBody');
            tbody.innerHTML = '';

            cashiers.forEach(cashier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${cashier.id}</td>
                    <td>${escapeHtml(cashier.name)}</td>
                    <td>${escapeHtml(cashier.email)}</td>
                    <td>
                        <button onclick="openEditModal(${cashier.id}, '${escapeHtml(cashier.name)}', '${escapeHtml(cashier.email)}')" 
                                class="text-primary" style="background:none; border:none; cursor:pointer; color:#3b82f6; margin-right:12px;">
                            Edit
                        </button>
                        <button onclick="deleteCashier(${cashier.id}, '${escapeHtml(cashier.name)}')" 
                                class="text-red-500" style="background:none; border:none; cursor:pointer; color:#ef4444;">
                            Delete
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Initialize or refresh DataTable
            if (dataTable) {
                dataTable.destroy();
            }
            dataTable = new simpleDatatables.DataTable("#cashiersTable", {
                perPage: 10,
                perPageSelect: [10, 25, 50, 100]
            });
        }

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('addCashierForm').reset();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(id, name, email) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function addCashier(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password')
            };

            fetch('api/user_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        closeAddModal();
                        loadCashiers();
                    } else {
                        showAlert(data.error, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error: ' + error.message, 'error');
                });
        }

        function updateCashier(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const data = {
                action: 'update',
                id: formData.get('id'),
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password') || null
            };

            fetch('api/user_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        closeEditModal();
                        loadCashiers();
                    } else {
                        showAlert(data.error, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error: ' + error.message, 'error');
                });
        }

        function deleteCashier(id, name) {
            if (!confirm(`Are you sure you want to delete cashier "${name}"?`)) {
                return;
            }

            fetch('api/user_controller.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadCashiers();
                    } else {
                        showAlert(data.error, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error: ' + error.message, 'error');
                });
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.innerHTML = '';
            container.appendChild(alert);

            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>

</body>

</html>