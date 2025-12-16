# Tax Configuration System

## Overview
The Tax Configuration system allows you to set different tax rates per category and product. This provides flexibility for handling different tax scenarios in your POS system.

## Features

### 1. **Category-Level Tax Rates**
- Set a default tax rate for each product category
- All products in a category inherit this rate by default
- Easy bulk management of taxes

### 2. **Product-Level Tax Overrides**
- Override the category tax rate for specific products
- Useful for tax-exempt items or special pricing scenarios
- Leave empty to use category default

### 3. **Tax-Exempt Products**
- Mark products as non-taxable
- Useful for essentials, medicines, or exempt items
- Checkbox in the product settings

### 4. **Tax Summary Report**
- View all products and their effective tax rates
- See which products use category defaults vs. overrides
- Visual indicators for easy identification

## How to Use

### Setting Up Tax Rates

1. **Go to Admin Dashboard** → Click "Tax Config" in the sidebar
2. **Configure Category Tax Rates:**
   - Each category is listed with a field for its tax rate
   - Enter the percentage (e.g., 12 for 12%)
   - Click "Update" to save
3. **Override Individual Products:**
   - In the "Product Tax Overrides" section
   - Enter a specific tax rate to override the category default
   - Leave empty to use the category rate
   - Uncheck "Taxable" to make a product tax-exempt
   - Click "Save"

### Tax Rate Hierarchy

Products use this priority:
1. **Product-Specific Tax Rate** (if set) ✓ Use this
2. **Category Tax Rate** (if product rate not set) ✓ Use this
3. **Default (12%)** (fallback) ✓ Use this

### Example Scenarios

**Scenario 1: Standard Taxable Products**
- Category: "Apparel" with 12% tax rate
- Product: "Blue Jeans"
- No override → Uses 12% from category

**Scenario 2: Tax-Exempt Item**
- Category: "Apparel" with 12% tax rate
- Product: "Organic Cotton Shirt"
- Mark as non-taxable → 0% tax applied

**Scenario 3: Different Tax Rate**
- Category: "Apparel" with 12% tax rate
- Product: "Luxury Designer Shirt"
- Override with 15% → Uses 15% instead of category 12%

## Database Schema

### categories table
```sql
ALTER TABLE categories ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 12.00;
```

### products table
```sql
ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT NULL;
ALTER TABLE products ADD COLUMN is_taxable BOOLEAN DEFAULT 1;
```

- `tax_rate` - Product-specific override (NULL means use category default)
- `is_taxable` - Whether this product should have tax applied (0 = tax exempt, 1 = taxable)

## Using Tax in POS System

### In Checkout/Sales
The system automatically:
1. Retrieves the product's effective tax rate via `getEffectiveTaxRate()`
2. Checks if product is taxable via `isTaxable()`
3. Calculates tax using: `calculateProductTax($product, $subtotal)`

### Code Examples

```php
// Get a product's effective tax rate
$product = Product::find(123);
$taxRate = $product->getEffectiveTaxRate(); // Returns 12.00

// Check if product is taxable
if ($product->isTaxable()) {
    $tax = calculateProductTax($product, 100.00); // $12.00
} else {
    $tax = 0;
}

// Get display text for UI
echo getTaxRateDisplay($product);
// Output: "12.00% (Category Default)" or "15.00% (Product Override)"
```

## Helper Functions

### `calculateProductTax(Product $product, float $subtotal): float`
Calculates the tax amount for a product based on its settings.
- Returns 0 if product is non-taxable
- Uses effective tax rate
- Returns calculated tax amount

### `getTaxRateDisplay(Product $product): string`
Returns a formatted string showing the tax rate and its source.
- Shows "Product Override" if product has specific rate
- Shows "Category Default" if using category rate

### `Product::getEffectiveTaxRate(): float`
Returns the tax rate to apply to this product.
- Priority: Product rate → Category rate → 12%

### `Product::isTaxable(): bool`
Returns whether this product should have tax applied.

## Tax Configuration Report

The Tax Config page includes a comprehensive table showing:
- Product Name
- Category
- Category Tax Rate (blue badge)
- Product Override (green badge if exists)
- Effective Rate (what will be charged)
- Taxable Status (Yes/No)

## Integration with Checkout

When calculating order totals at checkout:

```php
// For each item in the order
foreach ($orderItems as $item) {
    $product = Product::find($item->product_id);
    $subtotal = $item->price * $item->quantity;
    
    // Calculate tax using the product's effective rate
    $tax = calculateProductTax($product, $subtotal);
    
    // Add to total
    $total += $subtotal + $tax;
}
```

## Best Practices

1. **Set Category Defaults First** - Establish standard rates for each category
2. **Use Overrides Sparingly** - Only override when necessary to avoid confusion
3. **Document Special Cases** - Note why certain products have different rates
4. **Review Regularly** - Check the Tax Summary report to ensure consistency
5. **Test Before Going Live** - Verify tax calculations in a few transactions

## Common Issues & Solutions

**Issue:** Products showing wrong tax rate
- **Solution:** Check if product has an override set. Clear it to use category default.

**Issue:** Tax-exempt products still being taxed
- **Solution:** Ensure the "Taxable" checkbox is unchecked for those products.

**Issue:** Some products not showing in the override section
- **Solution:** Make sure all products exist. Products are loaded from the products table.

## Support

For issues or questions about tax configuration:
1. Check the Tax Configuration page's summary table
2. Verify category tax rates
3. Check product overrides and taxable status
4. Review the helper functions in `_helper.php`
