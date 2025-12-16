<?php
// Wrapper to expose the existing cashier controller at the expected API path
// so frontend code (`js/cashier.js`) can POST to `api/cashier_controller.php`.

// If the real controller lives in `condition/cashier_controller.php`, include it.
require_once __DIR__ . '/../condition/cashier_controller.php';

// The included file handles the request and will exit appropriately.
