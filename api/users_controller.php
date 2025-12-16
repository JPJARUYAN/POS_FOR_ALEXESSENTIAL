<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';

Guard::adminOnly();

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = get('action') ?? post('action');

    if ($method === 'GET' && $action === 'delete') {
        // Delete user
        $userId = intval(get('id'));
        if (!$userId) {
            throw new Exception("User ID is required");
        }

        User::delete($userId);

        setFlash('delete_user', 'User deleted successfully');
        redirect('admin_users.php');

    } elseif ($method === 'POST') {
        if ($action === 'create') {
            // Create new user
            $name = post('name');
            $email = post('email');
            $password = post('password');
            $role = post('role') ?? 'CASHIER';

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception("Name, email, and password are required");
            }

            if (!in_array($role, ['ADMIN', 'CASHIER'])) {
                throw new Exception("Invalid role");
            }

            $user = User::create($name, $email, $password, $role);

            setFlash('create_user', 'User created successfully!');
            redirect('admin_users.php');

        } elseif ($action === 'update') {
            // Update existing user
            $userId = intval(post('id'));
            $name = post('name');
            $email = post('email');
            $password = post('password') ?? null;

            if (!$userId) {
                throw new Exception("User ID is required");
            }

            if (empty($name) || empty($email)) {
                throw new Exception("Name and email are required");
            }

            $user = User::update($userId, $name, $email, $password);

            setFlash('update_user', 'User updated successfully!');
            redirect('admin_users.php');

        } else {
            throw new Exception("Invalid action");
        }
    } else {
        throw new Exception("Invalid request method");
    }

} catch (Exception $e) {
    setFlash('error', $e->getMessage());
    redirect('admin_users.php');
}
?>
