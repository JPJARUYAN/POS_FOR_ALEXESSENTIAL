<?php
// This file should be included at the very start to ensure suppliers table exists
require_once __DIR__ . '/models/Supplier.php';

// Force table creation
Supplier::ensureSuppliersTable();
?>
