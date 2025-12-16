<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_guards.php';
Guard::adminOnly();

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Get all cashiers
        $users = User::getAll('CASHIER');
        $response = [];
        foreach ($users as $user) {
            $response[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
        }
        echo json_encode(['success' => true, 'data' => $response]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['action']) && $data['action'] === 'update') {
            // Update existing user
            $userId = $data['id'] ?? null;
            $name = $data['name'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? null;

            if (!$userId) {
                throw new Exception("User ID is required");
            }

            $user = User::update($userId, $name, $email, $password);

            echo json_encode([
                'success' => true,
                'message' => 'Cashier updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);

        } else {
            // Create new user
            $name = $data['name'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            $user = User::create($name, $email, $password, 'CASHIER');

            echo json_encode([
                'success' => true,
                'message' => 'Cashier created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['id'] ?? null;

        if (!$userId) {
            throw new Exception("User ID is required");
        }

        User::delete($userId);

        echo json_encode([
            'success' => true,
            'message' => 'Cashier deleted successfully'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>