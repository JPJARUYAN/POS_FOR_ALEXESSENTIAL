# Simple POS — Project Documentation

This document summarizes the `simple-pos` point-of-sale application in this repository, how it works, how to set it up, and recommendations for next steps.

**Overview**

- **What it is:** A lightweight POS application built with vanilla PHP and MySQL.
- **Purpose:** Manage products, categories, users (admin/cashier), create orders, and view sales metrics.
- **Stack:** PHP (PDO), MySQL, HTML/CSS/JS (no frameworks).

**Project Layout (important files & folders)**

- **`_config.php`**: Database connection constants and global app constants (roles, flash keys).
- **`_init.php`**: Bootstrap file — loads config, helpers, models, session settings, and creates a PDO connection.
- **`_helper.php`**: Utility functions like `get()`, `post()`, `redirect()`, and flash messages.
- **`_guards.php`**: `Guard` class for role-based access helpers (`adminOnly`, `cashierOnly`, `guestOnly`).
- **`models/`**: Data-layer classes (`User.php`, `Product.php`, `Category.php`, `Order.php`, `OrderItem.php`, `Sales.php`).
- **`api/`**: Public API endpoints used by frontend JavaScript. Many API files are wrappers that `require_once` implementations in `condition/`.
- **`condition/`**: Implementation of the API logic (business logic) used by the `api/` wrappers.
- **`migrations/`**: SQL migration files (e.g., adding `cost`, `size`, and `product_sizes` table).
- **`simple-pos.sql`**: Full DB dump (schema + sample users) you can import to create the baseline database.
- **`js/`**, **`css/`**, **`templates/`**: Frontend assets and UI templates.

**Database Schema (key tables)**

- `users` — id, name, email, role (CASHIER/ADMIN), password (note: sample uses plaintext in the dump).
- `categories` — id, name.
- `products` — id, category_id, name, quantity, price, plus later-added columns: `cost`, `size`, `sku`, `barcode`, `image`, `created_at`, `updated_at`.
- `orders` — id, created_at.
- `order_items` — id, product_id, order_id, quantity, price.
- `product_sizes` — product_id, size, quantity (tracks stock per size) — added by migrations.

Referential integrity: foreign keys exist between `products` -> `categories` and `order_items` -> (`orders`, `products`).

**How the Main Flows Work**

- Authentication: `models/User.php` implements login and `User::getAuthenticatedUser()` is used by `_guards.php` to protect pages.
- Cashier flow: `cashier_sales.php` + `js/cashier.js` builds a cart; when checkout occurs frontend posts to `api/save_receipt.php` (wrapper for `condition/save_receipt.php`) which:
  - Creates an `orders` record and corresponding `order_items`.
  - Deducts product stock from `products.quantity` and from `product_sizes` when sizes are used.
  - Optionally triggers receipt generation (`api/generate_receipt_pdf.php`).
- Admin flows: admin pages (`admin_*.php`) allow managing products, categories, stock, and viewing sales reports via `api/dashboard_metrics.php`.

**Key Model Details**

- `models/Product.php` provides methods:
  - `all()`, `find($id)`, `add(...)`, `update()`, `delete()`.
  - Size/stock helpers: `ensureProductSizesTable()`, `addSizeStock()`, `recalculateTotalQuantity()`, `getSizeStocks()`, `getFormattedSizeStocks()`.
  - The model is defensive: it will create the `product_sizes` table at runtime if missing.

**Setup (Windows + XAMPP)**

1. Copy project to XAMPP `htdocs` (e.g., `C:\xampp\htdocs\POS_SYSTEM`).
2. Start Apache and MySQL in XAMPP.
3. Import the SQL dump. Example using PowerShell (adjust host/port/user/password):

```powershell
# Example: change -P port as necessary. If MySQL uses default 3306 set -P 3306 or omit.
mysql -u root -p -h 127.0.0.1 -P 3306 simple_pos < "C:\xampp\htdocs\POS_SYSTEM\simple-pos.sql"
```

4. Edit `\_config.php` to match your MySQL credentials and host/port. (Current repo uses `DB_HOST = '127.0.0.1;port=3307'` — verify your MySQL port and adjust accordingly.)
5. Visit `http://localhost/POS_SYSTEM/login.php` (or the folder name you placed under `htdocs`).

**Security Recommendations (must-do for production)**

- Replace plaintext passwords in the database with hashed passwords (use `password_hash()` / `password_verify()` in `models/User.php`).
- Add CSRF tokens to state-changing forms and AJAX requests.
- Validate and sanitize all user input server-side. Continue to use PDO prepared statements (already used in models) to avoid SQL injection.
- Limit session lifetime or add a secure "remember me" implementation rather than 30-day sessions if security is a priority.
- Validate/secure file uploads (product images) and store them safely.

**Developer Notes & Extensions**

- `api/` files are wrappers for `condition/` implementations. This pattern allows swapping implementations while keeping a stable API path for the frontend.
- To extend features (e.g., discounts, tax, multi-payment methods): add DB fields and API handlers in `condition/` and expose them via `api/` wrappers.
- For reporting: extend `api/dashboard_metrics.php` and `api/metric_details.php`.

**Files to Inspect for Deeper Understanding**

- `models/User.php` — authentication details.
- `condition/save_receipt.php` — sale processing (orders + order_items + stock adjustments).
- `js/cashier.js` — frontend cart and checkout actions.
- `migrations/` — history of DB changes (cost, sizes, etc.).
- `simple-pos.sql` — canonical schema and sample data.

**Potential Next Tasks (I can help with any)**

- Create a secure migration to hash existing plaintext user passwords and update login flow.
- Add CSRF tokens to forms and AJAX endpoints.
- Harden sessions and recommend production-ready settings (cookie flags, shorter lifetimes).
- Produce a short admin guide for common operations (add product, manage sizes, run reports).

---

If you want, I can now implement one of the next tasks (for example, convert sample passwords to hashed values and update `models/User.php`), or generate a short admin how-to. Which would you like me to do next?