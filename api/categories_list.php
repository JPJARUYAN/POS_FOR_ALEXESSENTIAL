<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../_guards.php';

// Only admins should access this in the admin UI
Guard::adminOnly();

header('Content-Type: application/json');

$cats = Category::all();
$out = array_map(function($c){ return ['id' => $c->id, 'name' => $c->name]; }, $cats);

echo json_encode(['success' => true, 'categories' => $out]);

?>
