<?php

function dd($data) {
    header('Content-type: application/json');
    echo json_encode($data);
    die();
}

function get($key) {
    if (isset($_GET[$key])) return trim($_GET[$key]);
    return "";
}

function post($key) {
    if (isset($_POST[$key])) {
        return trim($_POST[$key]);
    }
    return "";
}

function redirect($location) {
    header("location: $location");
    die();
}

function flashMessage($name, $message, $type) {

    // remove existing message with the name
    if (isset($_SESSION[FLASH][$name])) {
        unset($_SESSION[FLASH][$name]);
    }

    $_SESSION[FLASH][$name] = ['message' => $message, 'type' => $type];
}

function formattedFlashMessage($flashMessage) {
    return sprintf("<div class='alert alert-%s'>%s</div>",
        $flashMessage['type'],
        $flashMessage['message']
    );
}

function displayFlashMessage($name) {

    if (!isset($_SESSION[FLASH][$name])) return;

    $flashMessage = $_SESSION[FLASH][$name];

    unset($_SESSION[FLASH][$name]);

    echo formattedFlashMessage($flashMessage);
}

/**
 * Calculate the tax for a product at checkout
 * Takes into account the product's tax settings (rate and taxable status)
 * 
 * @param Product $product - The product object
 * @param float $subtotal - The subtotal amount before tax
 * @return float - The calculated tax amount
 */
function calculateProductTax(Product $product, float $subtotal): float {
    // If product is not taxable, no tax
    if (!$product->isTaxable()) {
        return 0;
    }

    // Get the effective tax rate
    $taxRate = $product->getEffectiveTaxRate();
    
    // Calculate tax
    return $subtotal * ($taxRate / 100);
}

/**
 * Get the tax rate display text for a product
 * Shows whether it's using category default or product override
 */
function getTaxRateDisplay(Product $product): string {
    if ($product->tax_rate !== null) {
        return number_format($product->tax_rate, 2) . '% (Product Override)';
    }
    $effectiveRate = $product->getEffectiveTaxRate();
    return number_format($effectiveRate, 2) . '% (Category Default)';
}
?>