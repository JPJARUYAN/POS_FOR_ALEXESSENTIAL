<?php
// Start session first
session_start();

// Simulate admin session for testing
$_SESSION['user_id_admin'] = 1;

// Now include the expenses page
require_once 'admin_expenses.php';
