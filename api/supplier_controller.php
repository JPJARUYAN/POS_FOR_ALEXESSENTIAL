<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../_guards.php';

// Check authentication BEFORE setting headers
try {
    Guard::adminOnly();
} catch (Exception $e) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Unauthorized']]);
    exit;
}

// Ensure suppliers table exists before processing any requests
Supplier::ensureSuppliersTable();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = null;
if ($method === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : null;
}

try {
    if ($action === 'add' && $method === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Basic validation
        $errors = [];
        if (!$name) $errors[] = 'Name is required';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        try {
            $newId = Supplier::add($name, $contact, $phone, $email, $address);
            $sup = Supplier::find($newId);
            echo json_encode(['success' => true, 'id' => $newId, 'name' => $sup?->name ?? $name, 'message' => 'Supplier added']);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
            exit;
        }
    }

    if ($action === 'update' && $method === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        $supplier = Supplier::find($id);
        if (!$supplier) {
            http_response_code(404);
            echo json_encode(['success'=>false,'errors'=>['Supplier not found']]);
            exit;
        }

        $supplier->name = trim($_POST['name'] ?? $supplier->name);
        $supplier->contact_person = trim($_POST['contact_person'] ?? $supplier->contact_person);
        $supplier->phone = trim($_POST['phone'] ?? $supplier->phone);
        $supplier->email = trim($_POST['email'] ?? $supplier->email);
        $supplier->address = trim($_POST['address'] ?? $supplier->address);

        // Basic validation
        $errors = [];
        if (!$supplier->name) $errors[] = 'Name is required';
        if ($supplier->email && !filter_var($supplier->email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'errors'=>$errors]);
            exit;
        }

        try {
            $supplier->update();
            echo json_encode(['success'=>true,'id'=>$supplier->id,'name'=>$supplier->name,'message'=>'Supplier updated']);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'errors'=>[$e->getMessage()]]);
            exit;
        }
    }

    if ($action === 'delete' && ($method === 'POST' || $method === 'GET')) {
        $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success'=>false,'errors'=>['ID required']]);
            exit;
        }
        $supplier = Supplier::find($id);
        if (!$supplier) {
            http_response_code(404);
            echo json_encode(['success'=>false,'errors'=>['Supplier not found']]);
            exit;
        }
        try {
            $supplier->delete();
            echo json_encode(['success' => true,'message'=>'Supplier deleted']);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'errors'=>[$e->getMessage()]]);
            exit;
        }
    }

    if ($action === 'list') {
        try {
            $suppliers = Supplier::all();
            echo json_encode(['success'=>true, 'data'=>$suppliers]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'errors'=>[$e->getMessage()]]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid action or method']]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
