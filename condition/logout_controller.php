<?php

require_once __DIR__ . '/../_init.php';

// If a role is provided we only log out that role. Otherwise clear both.
$role = get('role');

if ($role) {
	$slot = 'user_id_'.strtolower($role);
	if (isset($_SESSION[$slot])) unset($_SESSION[$slot]);
} else {
	if (isset($_SESSION['user_id_admin'])) unset($_SESSION['user_id_admin']);
	if (isset($_SESSION['user_id_cashier'])) unset($_SESSION['user_id_cashier']);
}

redirect('../login.php');