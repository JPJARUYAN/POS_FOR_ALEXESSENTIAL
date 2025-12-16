# Simple POS — Admin Quick Guide

This short guide describes common admin tasks: adding products, managing sizes and stock, and running basic sales reports.

**Overview**

This guide assumes you are logged in as an Admin user (use the account created in the `simple-pos.sql` import or add a new admin user via the `users` table).

**Access the Admin Area**

- Open your app in a browser, e.g. `http://localhost/POS_SYSTEM/login.php`.
- Login as an Admin. After login, use the navigation to go to the Admin Dashboard (`admin_dashboard.php`) or product management pages (e.g., `admin_add_item.php`, `admin_category.php`).

**1) Add a New Product**

Purpose: create a product entry the cashier can sell.

Steps:
- Go to `admin_add_item.php` (link is available in the admin navbar).
- Fill required fields:
  - `Name` — product name
  - `Category` — select existing category, or create one via `admin_category.php`
  - `Price` — selling price
  - `Cost` — (optional) cost for margin tracking
  - `Size` — optional comma-separated sizes (e.g., `S, M, L, XL`) if this item has size variants
  - `Quantity` — total stock (if you will manage size-specific stock, you can leave this as 0 and use the stock interface below)
  - `SKU` / `Barcode` / `Image` — optional fields depending on your install
- Click `Save` / `Add Product`.

Notes:
- If you entered sizes in the `Size` field, the app stores that as the list of sizes. Use the Manage Stock page to set per-size quantities. The `Product` model provides helper methods that create the `product_sizes` table automatically if it does not exist.

**2) Manage Sizes & Stock**

Purpose: set or update quantities per size and keep `products.quantity` in sync.

Steps:
- Go to `admin_add_stock.php` or the Admin > Stock / Manage Products page.
- Select the product you want to update.
- If the product uses sizes, you'll see fields to enter quantity per size (e.g., `S: 10`, `M: 8`). Enter the quantity for each size and submit.
- For non-size products, update the `Quantity` field directly and save.

What happens on submit:
- For size entries the backend stores rows in `product_sizes` with `product_id`, `size`, and `quantity`.
- The product's total `products.quantity` is recalculated (sum of `product_sizes.quantity`) automatically by the Product model's `recalculateTotalQuantity()` method.

Manual SQL (if needed):
```sql
-- Add/update size stock (example)
INSERT INTO product_sizes (product_id, size, quantity)
VALUES (123, 'M', 10)
ON DUPLICATE KEY UPDATE quantity = 10;

-- Recalculate product total quantity
UPDATE products p
SET p.quantity = (SELECT COALESCE(SUM(ps.quantity),0) FROM product_sizes ps WHERE ps.product_id = p.id)
WHERE p.id = 123;
```

**3) Run Sales Reports & View Metrics**

Purpose: monitor sales and revenue.

Where:
- Use `admin_sales.php` to view sales list and `admin_dashboard.php` for metrics and summaries.
- Metrics are backed by `api/dashboard_metrics.php` and `api/metric_details.php`.

Common actions:
- Filter by date range on the dashboard or sales page to narrow results.
- Click into a sale to view order items and receipt information.
- Export or print receipts using the receipt generation link (`api/generate_receipt_pdf.php`) if available.

**4) Manage Categories**

- Go to `admin_category.php`.
- Add a category name and save.
- Categories are referenced by products; deleting a category may cascade (see DB FK behavior) — be careful with deletes if products are using a category.

**5) Useful Maintenance Commands**

From PowerShell or your MySQL client:
```powershell
# Import DB (once):
mysql -u root -p -h 127.0.0.1 -P 3306 simple_pos < "C:\xampp\htdocs\POS_SYSTEM\simple-pos.sql"
```

Direct SQL examples:
```sql
-- Create a new admin user (remember to hash passwords in production):
INSERT INTO users (name, email, role, password) VALUES ('Admin', 'admin@example.com', 'ADMIN', 'yourpassword');

-- Force recalc of all products from product_sizes
UPDATE products p
SET p.quantity = (
  SELECT COALESCE(SUM(ps.quantity),0)
  FROM product_sizes ps
  WHERE ps.product_id = p.id
);
```

**Tips & Troubleshooting**

- If per-size stock does not show up, ensure `product_sizes` table exists. `models/Product.php` will attempt to create it automatically, but if your DB user lacks `CREATE` privileges, run the migration `migrations/add_product_sizes_table.sql` manually.
- If images fail to upload, check your PHP `upload_max_filesize` and `post_max_size` settings in `php.ini` and confirm directory permissions.
- If the cashier cannot find a product by SKU, verify that the `sku`/`barcode` fields were populated when creating the product.
- After updating size stock manually in SQL, run the `UPDATE products` recalc command above to sync the product total quantity.

**Security Notes for Admins**

- Limit admin access to trusted users and use strong passwords. The SQL dump includes sample plaintext passwords — change them immediately.
- Consider enabling HTTPS for your local or production install.

---

If you want, I can:
- Add inline screenshots for each admin page (I can capture the UI if you run it locally and share images), or
- Implement a small admin form improvement (e.g., add a UI to initialize per-size quantities when creating a product), or
- Implement a password-hashing migration for existing users.

Which next step would you like me to take?