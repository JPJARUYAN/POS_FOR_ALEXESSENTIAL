<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../_guards.php';

Guard::adminOnly();

$method = $_SERVER['REQUEST_METHOD'];
$action = null;
if ($method === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : null;
}

$isAjax = false;
$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
if (strpos($accept, 'application/json') !== false) $isAjax = true;
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') $isAjax = true;

try {
    if ($action === 'add' && $method === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) throw new Exception('Name required');
        Category::add($name);
        // return newly created category id
        $cat = Category::findByName($name);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $cat->id, 'name' => $cat->name]);
            exit;
        }
        flashMessage('add_category', 'Category added', FLASH_SUCCESS);
        header('Location: ../admin_add_item.php');
        exit;
    }

    if ($action === 'update' && $method === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) throw new Exception('ID and name required');
        $category = Category::find($id);
        if (!$category) throw new Exception('Category not found');
        $category->name = $name;
        $category->update();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $category->id, 'name' => $category->name]);
            exit;
        }
        flashMessage('update_category', 'Category updated', FLASH_SUCCESS);
        header('Location: ../admin_add_item.php');
        exit;
    }

    if ($action === 'delete' && $method === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) throw new Exception('ID required');
        $category = Category::find($id);
        if (!$category) throw new Exception('Category not found');
        $category->delete();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        flashMessage('delete_category', 'Category deleted', FLASH_SUCCESS);
        header('Location: ../admin_add_item.php');
        exit;
    }

    // default redirect
    header('Location: ../admin_add_item.php');
    exit;

} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    flashMessage('add_category', 'Error: ' . $e->getMessage(), FLASH_ERROR);
    header('Location: ../admin_add_item.php');
    exit;
}

?>
