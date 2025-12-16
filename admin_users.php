<?php
require_once '_init.php';

Guard::adminOnly();

// Set role context for navbar
$GLOBALS['CURRENT_ROLE_CONTEXT'] = ROLE_ADMIN;

$title = 'User Management';
$users = User::getAll();
$user = null;

if (get('action') === 'update') {
    $user = User::find(get('id'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/util.css">
    <link rel="stylesheet" href="css/datatable.css">
    <style>
        .user-layout {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            padding: 24px;
        }

        .user-form {
            flex: 0 0 350px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .user-table {
            flex: 1;
            min-width: 300px;
        }

        .form-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            margin-bottom: 16px;
        }

        .form-control label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control input,
        .form-control select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-body);
            background: var(--input-bg);
            transition: all 0.2s ease;
        }

        .form-control input:focus,
        .form-control select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-cancel {
            width: 100%;
            padding: 10px;
            background: var(--border-color);
            color: var(--text-body);
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        .btn-cancel:hover {
            background: var(--text-muted);
            color: white;
        }

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            border-bottom: 2px solid var(--border-color);
            background: var(--bg);
        }

        th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-body);
        }

        tbody tr:hover {
            background: var(--bg);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.admin {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .role-badge.cashier {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .action-btns {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-sm.edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .btn-sm.edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-sm.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .btn-sm.delete:hover {
            background: #ef4444;
            color: white;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .user-layout {
                flex-wrap: wrap;
                gap: 16px;
            }

            .user-form {
                flex: 0 0 100%;
                max-width: 400px;
            }
        }

        @media (max-width: 768px) {
            .user-layout {
                padding: 16px;
            }

            .user-form {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .action-btns {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <?php require 'templates/admin_header.php' ?>

    <div class="flex">
        <?php require 'templates/admin_navbar.php' ?>

        <main style="width: 100%; background: var(--bg);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); margin: 32px 24px 32px 24px;">
                <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">👥 User Management</h1>
                <p style="margin: 0; opacity: 0.9; font-size: 1em;">Create, update, and manage system user accounts and roles.</p>
            </div>

            <div class="user-layout">
                <!-- Form Section -->
                <div class="user-form">
                    <div class="form-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <?php if (get('action') === 'update') : ?>
                            Update User
                        <?php else : ?>
                            Add New User
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="api/users_controller.php" id="userForm">
                        <input type="hidden" name="action" value="<?= get('action') === 'update' ? 'update' : 'create' ?>" />
                        <input type="hidden" name="id" value="<?= $user?->id ?>" />

                        <div class="form-control">
                            <label for="name">Full Name *</label>
                            <input 
                                id="name"
                                type="text" 
                                name="name" 
                                value="<?= htmlspecialchars($user?->name ?? '') ?>"
                                placeholder="John Doe" 
                                required 
                            />
                        </div>

                        <div class="form-control">
                            <label for="email">Email Address *</label>
                            <input 
                                id="email"
                                type="email" 
                                name="email" 
                                value="<?= htmlspecialchars($user?->email ?? '') ?>"
                                placeholder="user@example.com" 
                                required 
                            />
                        </div>

                        <div class="form-control">
                            <label for="password">
                                Password <?php if (get('action') === 'update') : ?>(Leave blank to keep current)<?php endif; ?> *
                            </label>
                            <input 
                                id="password"
                                type="password" 
                                name="password" 
                                placeholder="Enter password" 
                                <?php if (get('action') !== 'update') : ?>required<?php endif; ?>
                            />
                        </div>

                        <div class="form-control">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="ADMIN" <?= $user?->role === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                                <option value="CASHIER" <?= $user?->role === 'CASHIER' ? 'selected' : '' ?>>Cashier</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">
                            <?php if (get('action') === 'update') : ?>
                                Update User
                            <?php else : ?>
                                Create User
                            <?php endif; ?>
                        </button>

                        <?php if (get('action') === 'update') : ?>
                            <a href="admin_users.php" class="btn-cancel" style="display: block; text-align: center; text-decoration: none;">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table Section -->
                <div class="user-table">
                    <div class="table-card">
                        <div class="table-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px; vertical-align: -4px;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Users List (<?= count($users) ?>)
                        </div>

                        <?php displayFlashMessage('create_user') ?>
                        <?php displayFlashMessage('update_user') ?>
                        <?php displayFlashMessage('delete_user') ?>

                        <?php if (empty($users)) : ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <p>No users found</p>
                            </div>
                        <?php else : ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th style="width: 160px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u) : ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($u->name) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($u->email) ?></td>
                                                <td>
                                                    <span class="role-badge <?= strtolower($u->role) ?>">
                                                        <?= $u->role ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <a href="?action=update&id=<?= $u->id ?>" class="btn-sm edit">Edit</a>
                                                        <?php if ($u->role !== 'ADMIN') : ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn-sm delete"
                                                                onclick="if(confirm('Are you sure you want to delete this user?')) { 
                                                                    window.location='api/users_controller.php?action=delete&id=<?= $u->id ?>'; 
                                                                }"
                                                            >
                                                                Delete
                                                            </button>
                                                        <?php else : ?>
                                                            <span style="font-size: 12px; color: var(--text-muted);">Cannot delete admin</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
