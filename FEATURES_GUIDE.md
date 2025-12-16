# Simple POS System - Detailed Features Guide

## Table of Contents
1. [Authentication & User Management](#authentication--user-management)
2. [Product Management](#product-management)
3. [Category Management](#category-management)
4. [Point of Sale (POS) / Cashier Operations](#point-of-sale--cashier-operations)
5. [Sales & Order Management](#sales--order-management)
6. [Dashboard & Analytics](#dashboard--analytics)
7. [Inventory Management](#inventory-management)
8. [Product Sizes & Stock Tracking](#product-sizes--stock-tracking)
9. [Receipt Generation](#receipt-generation)
10. [Payment Processing](#payment-processing)
11. [User Role-Based Access](#user-role-based-access)
12. [Theme & UI Features](#theme--ui-features)

---

## Authentication & User Management

### Overview
The system implements a secure role-based authentication mechanism with two user roles: **Admin** and **Cashier**. Authentication is session-based, allowing different users with different permissions to access appropriate sections of the application.

### Key Features

#### 1. **Login System**
- **File**: `login.php`
- **Model**: `models/User.php`
- **Purpose**: Authenticates users and establishes session-based access control
- **How it works**:
  - Users submit email and password via login form
  - System validates credentials against the `users` database table
  - Upon successful login, user ID is stored in `$_SESSION['user_id_admin']` or `$_SESSION['user_id_cashier']` depending on role
  - Session persists for 30 days (configurable)
  - User is redirected to their respective dashboard (Admin → `admin_home.php`, Cashier → `index.php`)

#### 2. **User Model (`models/User.php`)**
**Properties**:
- `id`: Unique user identifier
- `name`: User's full name
- `email`: User's email address
- `role`: User role (ROLE_ADMIN = 'ADMIN' or ROLE_CASHIER = 'CASHIER')
- `password`: User's password (stored as plaintext in sample; should be hashed in production)

**Key Methods**:
- `getAuthenticatedUser($role = null)`: Retrieves the currently logged-in user, optionally filtered by role
- `find($user_id)`: Fetches a user by ID from the database
- `getHomePage()`: Returns the appropriate dashboard URL based on user role
- `getAll($role = null)`: Retrieves all users, optionally filtered by role

#### 3. **User Role-Based Access**
- **Admin Users**: Can access product management, category management, inventory updates, and analytics dashboard
- **Cashier Users**: Can access the POS interface, process sales, and view sales history
- **Guards** (`_guards.php`): Protects pages using `Guard::adminOnly()` and `Guard::cashierOnly()`

#### 4. **Session Management**
- Sessions are stored in PHP's default session handler
- Session variables:
  - `user_id_admin`: ID of logged-in admin user
  - `user_id_cashier`: ID of logged-in cashier user
- Users can be logged out, which clears their session
- "Remember me" functionality can be implemented for longer persistence

---

## Product Management

### Overview
The product management system allows admins to create, read, update, and delete products with detailed attributes including pricing, cost, size information, SKU, barcode, and images.

### Key Features

#### 1. **Product Model (`models/Product.php`)**
**Properties**:
- `id`: Unique product identifier
- `name`: Product name
- `category_id`: Foreign key linking to categories table
- `quantity`: Total stock quantity (or combined from sizes if using product_sizes)
- `price`: Selling price per unit
- `cost`: Cost per unit (for profit calculation)
- `size`: Optional size designation
- `sku`: Stock Keeping Unit (unique identifier)
- `barcode`: Barcode for scanning
- `image`: Product image filename/path
- `created_at`: Timestamp of product creation
- `updated_at`: Timestamp of last update

**Key Methods**:
- `all()`: Retrieves all products from database
- `find($id)`: Retrieves a specific product by ID
- `add(...)`: Creates a new product with all attributes
- `update()`: Updates existing product details
- `delete()`: Removes a product from the system
- `getSizeStocks()`: Retrieves stock quantities for each size variant
- `getFormattedSizeStocks()`: Returns size stocks in human-readable format
- `addSizeStock($size, $quantity)`: Adds stock for a specific size
- `recalculateTotalQuantity()`: Recalculates total quantity from size variants
- `ensureProductSizesTable()`: Ensures product_sizes table exists (defensive programming)

#### 2. **Product Management Interface**
- **File**: `admin_add_item.php`
- **Features**:
  - Form to add new products with all attributes
  - Category dropdown selection
  - Price and cost input fields
  - Size specification (optional)
  - SKU and barcode entry
  - Product image upload
  - Form validation and error handling

#### 3. **Product Update Interface**
- **File**: `admin_update_item.php`
- **Features**:
  - Edit existing product details
  - Update pricing and cost
  - Modify inventory quantities
  - Change category assignment
  - Update product images
  - Real-time validation

#### 4. **Profit Calculation**
- **Formula**: Profit = (Price - Cost) × Quantity
- **Uses**: Product cost field for profit analysis
- **Visibility**: Shown in dashboard metrics and reports

---

## Category Management

### Overview
Categories organize products into logical groupings (e.g., Electronics, Beverages, Clothing). Admins can create, view, and manage categories.

### Key Features

#### 1. **Category Model (`models/Category.php`)**
**Properties**:
- `id`: Unique category identifier
- `name`: Category name

**Key Methods**:
- `all()`: Retrieves all categories with caching for performance
- `find($id)`: Retrieves specific category by ID
- `findByName($name)`: Searches category by name
- `add($name)`: Creates new category with duplicate checking
- `update()`: Updates category name with uniqueness validation
- `delete()`: Removes category (assumes no foreign key constraint or cascade)

**Features**:
- **Caching**: Categories are cached in memory to reduce database queries
- **Duplicate Prevention**: Prevents multiple categories with same name
- **Unique Validation**: Checks uniqueness when creating or updating

#### 2. **Category Management Interface**
- **File**: `admin_category.php`
- **Features**:
  - Add new categories via form
  - Display all existing categories in table
  - Edit category names
  - Delete categories
  - Real-time validation

---

## Point of Sale (POS) / Cashier Operations

### Overview
The core cashier interface where sales transactions are processed. This is the main interface for creating orders and processing payments.

### Key Features

#### 1. **POS Interface**
- **File**: `index.php`
- **Access**: Cashier role only
- **Layout**: Two-column design
  - **Left Column**: Product catalog with category filtering
  - **Right Column**: Shopping cart and payment processing

#### 2. **Product Selection & Cart Building**
- **Features**:
  - Browse products by category
  - Click products to add to cart
  - Quantity adjustment (increase/decrease)
  - Remove items from cart
  - Search/filter products
  - Display product sizes with available stock
  - Real-time cart total calculation

#### 3. **Shopping Cart Operations**
- **Data Structure**: JavaScript array storing:
  - Product ID
  - Product name
  - Price per unit
  - Quantity selected
  - Size (if applicable)
  - Line total (price × quantity)

- **Operations**:
  - Add item: Appends product to cart or increases quantity if exists
  - Remove item: Deletes item from cart
  - Update quantity: Changes item count in real-time
  - Clear cart: Empties all items (with confirmation)
  - Recalculate totals: Updates subtotal, tax, and final total

#### 4. **Cart Display Features**
- Shows all cart items in table format
- Displays unit price and line total for each item
- Running subtotal
- Optional tax calculation
- Final total amount due
- Payment method selection
- Change calculation

#### 5. **Stock Deduction**
- **Process**: When checkout occurs, system:
  - Deducts total quantity from `products.quantity`
  - Deducts quantity from `product_sizes` table (if using sizes)
  - Updates inventory in real-time
  - Prevents overselling (cart can only contain available stock)

---

## Sales & Order Management

### Overview
The system tracks all sales transactions, recording details about orders, items sold, payment methods, and customer information.

### Key Features

#### 1. **Order Model (`models/Order.php`)**
**Properties**:
- `id`: Unique order identifier
- `created_at`: Timestamp of order creation
- `customer_id`: Optional customer identifier for tracking
- `cashier_id`: ID of cashier who processed sale
- `payment_method`: Type of payment (cash, card, e-wallet)
- `payment_amount`: Amount received from customer
- `change_amount`: Change due to customer

**Key Methods**:
- `create(...)`: Creates new order record with all details
- `getLastRecord()`: Retrieves most recently created order

#### 2. **Order Item Tracking**
- **Table**: `order_items`
- **Relationship**: Links orders to individual products sold
- **Properties**:
  - `id`: Unique line item identifier
  - `order_id`: Foreign key to orders
  - `product_id`: Foreign key to products
  - `quantity`: Quantity of product in this order
  - `price`: Price at time of sale (important for historical accuracy)
  - `size`: Size variant (if applicable)

#### 3. **Sales History**
- **File**: `cashier_sales.php`
- **Features**:
  - Display all historical transactions
  - Filter by date range
  - Filter by payment method
  - Filter by cashier
  - Search by order ID
  - Display customer name (if available)
  - Show item count per order
  - Display payment method and amount
  - Reprint receipts from history

#### 4. **Order Creation Process**
1. Cashier adds items to cart
2. Customer selects payment method (Cash, Card, E-wallet)
3. Cashier enters payment amount
4. System calculates change
5. Upon confirmation:
   - Order record is created
   - Order items are recorded
   - Inventory is updated
   - Receipt is generated
   - Order is finalized

---

## Dashboard & Analytics

### Overview
The admin dashboard provides comprehensive business metrics and analytics for monitoring sales performance, profitability, and inventory status.

### Key Features

#### 1. **Main Dashboard**
- **File**: `admin_dashboard.php`
- **Access**: Admin role only
- **Purpose**: Central hub for business metrics and reporting

#### 2. **Key Performance Indicators (KPIs)**
Dashboard displays four primary metrics:

**a) Total Sales (Revenue)**
- **Calculation**: Sum of all `order_items.price × order_items.quantity`
- **Purpose**: Shows total revenue generated
- **Use Case**: Track sales performance over time

**b) Total Cost (Cost of Goods Sold)**
- **Calculation**: Sum of all `products.cost × quantity_sold`
- **Purpose**: Shows cost of inventory sold
- **Use Case**: Calculate gross profit

**c) Net Profit**
- **Calculation**: Total Sales - Total Cost
- **Purpose**: Shows actual profit earned
- **Use Case**: Measure business profitability
- **Color Coding**: Green if positive, red if negative

**d) Transactions Count**
- **Calculation**: Count of all `order_items` records
- **Purpose**: Shows transaction volume
- **Use Case**: Monitor sales frequency

**e) Low Stock Items**
- **Calculation**: Count of products with quantity < 10
- **Purpose**: Alert admin to inventory levels
- **Use Case**: Trigger restocking alerts

#### 3. **Sales Charts**
- **Chart Types Available**: Line, Bar, and Pie charts
- **Data Visualizations**:
  - Sales trend over time (daily/weekly/monthly)
  - Top selling products
  - Sales by category
  - Profit by category
  - Revenue comparison

#### 4. **Detailed Reports**
- **File**: `api/metric_details.php`
- **Available Reports**:

  **a) Profit Analysis**
  - Total revenue breakdown
  - Total cost breakdown
  - Net profit calculation
  - Profit margin percentage
  - Profit by category table
  - Category-wise revenue and cost

  **b) Top Products**
  - Best selling products
  - Product sales quantity
  - Product category
  - Revenue per product

  **c) Cost Analysis**
  - Cost breakdown by category
  - Cost per item sold
  - Total vs. category cost

  **d) Inventory Status**
  - Low stock items (< 10 units)
  - Stock level color coding (red for critical, orange for warning)
  - Product price and category
  - Critical inventory alerts

  **e) Sales History**
  - Recent orders listing
  - Order ID, date, and time
  - Customer information
  - Item count and total amount
  - Payment method

#### 5. **Cashier Filter**
- Filter metrics by individual cashier
- Shows performance by cashier
- Helps identify top performers and track individual productivity

---

## Inventory Management

### Overview
Complete inventory tracking system that monitors stock levels, prevents stockouts, and maintains accurate product quantities.

### Key Features

#### 1. **Inventory Interface**
- **File**: `admin_add_stock.php`
- **Purpose**: Update product quantities
- **Features**:
  - List all products with current stock
  - Add stock to products
  - Remove stock from products
  - Search products by name
  - Filter by category
  - Display product cost and selling price

#### 2. **Stock Level Alerts**
- **Low Stock Threshold**: 10 units
- **Alert Locations**:
  - Dashboard shows count of low stock items
  - Inventory page highlights low stock items
  - Color coding: Red for critical (≤5), Orange for warning (6-10)

#### 3. **Stock Deduction on Sale**
- Automatic stock reduction when:
  - Order is confirmed and completed
  - Both total quantity and size-specific quantities updated
  - Prevents overselling through validations

#### 4. **Stock Verification**
- **File**: `verify_cost_column.php`
- **Purpose**: Database verification and diagnostics
- **Checks**:
  - Ensures cost column exists in products table
  - Validates data consistency
  - Helps troubleshoot inventory issues

---

## Product Sizes & Stock Tracking

### Overview
Advanced feature allowing products to have multiple size variants, each with independent stock tracking. For example, a shirt can have XS, S, M, L, XL sizes, each with separate inventory.

### Key Features

#### 1. **Product Sizes Table**
- **Table**: `product_sizes`
- **Schema**:
  - `product_id`: Foreign key to products
  - `size`: Size identifier (e.g., "S", "M", "L", "XL")
  - `quantity`: Stock quantity for this size

#### 2. **Size Stock Management**
**Methods in Product Model**:
- `ensureProductSizesTable()`: Creates table if missing (defensive)
- `addSizeStock($size, $quantity)`: Adds stock for specific size
- `getSizeStocks()`: Retrieves all size variants and quantities
- `getFormattedSizeStocks()`: Returns formatted display of sizes
- `recalculateTotalQuantity()`: Sums quantities across all sizes

#### 3. **Size Selection in POS**
- Cashier can select size variant when adding product to cart
- System validates size has sufficient stock
- Stock is deducted from specific size (not total quantity)
- Receipt shows size information

#### 4. **Size Management in Admin**
- Manage sizes for each product
- Set initial stock for each size
- Adjust size stock levels
- Add new size variants
- Remove obsolete sizes

#### 5. **Migration & Setup**
- **File**: `migrations/add_product_sizes_table.sql`
- **Purpose**: Creates product_sizes table if not exists
- **File**: `migrations/migrate_existing_products_to_sizes.php`
- **Purpose**: Migrates existing product quantities to size table

---

## Receipt Generation

### Overview
Automated receipt generation system that creates both printed and PDF receipts for customer transactions.

### Key Features

#### 1. **Receipt Data Collection**
- **Includes**:
  - Order ID
  - Date and time of transaction
  - Cashier name
  - Customer information (if available)
  - Itemized list of products
    - Product name
    - Quantity
    - Unit price
    - Size (if applicable)
    - Line total
  - Subtotal
  - Tax (if applicable)
  - Grand total
  - Payment method
  - Amount paid
  - Change due
  - Payment confirmation

#### 2. **Receipt Formats**
- **HTML Receipt**: Displayed on screen, can be printed
- **PDF Receipt**: Generated via `api/generate_receipt_pdf.php`
- **Email**: Can be sent to customer (with email functionality)

#### 3. **Receipt Management**
- **File**: `api/save_receipt.php` (wrapper)
- **Implementation**: `condition/save_receipt.php`
- **Features**:
  - Saves receipt data to database
  - Generates PDF file
  - Stores receipt as file for record-keeping
  - Supports reprint functionality
  - Associates receipt with order

#### 4. **Receipt Reprint**
- Available in Sales History
- Click "Reprint" button to regenerate receipt
- Uses stored order data for accuracy
- Marks as reprint in output

#### 5. **Receipt Customization**
- Company name and branding
- Store location and contact info
- Custom message/footer
- Tax calculation (if applicable)
- Discount display (if applicable)

---

## Payment Processing

### Overview
Flexible payment processing system supporting multiple payment methods with proper accounting and change calculation.

### Key Features

#### 1. **Payment Methods**
Three payment methods supported:

**a) Cash**
- Customer pays in physical currency
- System calculates change
- Change amount recorded in order
- Best for in-store transactions

**b) Card**
- Customer pays via debit/credit card
- No change calculation needed (system typically assumes exact amount or stores don't calculate change)
- Payment method recorded for reconciliation

**c) E-Wallet**
- Digital payment method (online payment)
- No change calculation
- Payment method recorded for tracking

#### 2. **Payment Amount Handling**
- **Input**: Cashier enters payment amount received
- **Calculation**: Change = Payment Amount - Total Amount
- **Validation**: Prevents negative change (payment must be ≥ total)
- **Display**: Shows change due in green if customer gets refund

#### 3. **Payment Recording**
In Order model:
- `payment_method`: Stored for reporting
- `payment_amount`: Amount received from customer
- `change_amount`: Change given to customer
- Used for financial reconciliation and audit trails

#### 4. **Payment Reconciliation**
- Dashboard shows breakdown by payment method
- Reports can filter transactions by payment type
- Helps identify payment method preferences
- Supports financial audit trails

---

## User Role-Based Access

### Overview
Security feature that restricts access to features based on user role, ensuring data integrity and appropriate access control.

### Key Features

#### 1. **Guard System**
- **File**: `_guards.php`
- **Class**: Guard
- **Methods**:
  - `Guard::adminOnly()`: Redirects non-admin users away
  - `Guard::cashierOnly()`: Redirects non-cashier users away
  - `Guard::guestOnly()`: Allows only logged-out users (for login page)

#### 2. **Admin Access**
**Available Pages**:
- Dashboard (`admin_dashboard.php`)
- Add products (`admin_add_item.php`)
- Update products (`admin_update_item.php`)
- Manage categories (`admin_category.php`)
- Manage stock (`admin_add_stock.php`)
- View sales reports (`admin_sales.php`)
- User management (future feature)

**Capabilities**:
- View all analytics and reports
- Manage product inventory
- Set prices and costs
- Manage categories
- View cashier performance
- Access all historical data

#### 3. **Cashier Access**
**Available Pages**:
- POS Interface (`index.php`)
- Sales History (`cashier_sales.php`)

**Capabilities**:
- Process sales transactions
- View own sales history
- View sales by date range
- Access order history
- Reprint receipts

**Restrictions**:
- Cannot modify products
- Cannot change prices
- Cannot adjust inventory
- Cannot access admin analytics

#### 4. **Session-Based Access Control**
- Session variables track logged-in users:
  - `user_id_admin`: Admin user ID
  - `user_id_cashier`: Cashier user ID
- Missing session variable means no access
- Login redirects to appropriate dashboard

#### 5. **API Endpoint Protection**
- API endpoints in `api/` directory also check user role
- Prevents unauthorized API calls
- Maintains security on backend operations

---

## Theme & UI Features

### Overview
Modern, responsive user interface with dark/light mode support, professional design, and accessibility considerations.

### Key Features

#### 1. **Dark/Light Mode Toggle**
- **Implementation**: JavaScript theme manager (`js/theme.js`)
- **Storage**: LocalStorage saves user preference
- **Automatic**: Defaults to dark mode, remembers user choice
- **Toggle Button**: Available in header navigation
- **Persistence**: Theme preference survives page reloads

#### 2. **CSS Theme Variables**
**Dark Mode** (Default):
- Background: `#0b1020` (very dark blue)
- Text: `#cbd5e1` (light gray)
- Cards: `#071126` (dark blue)
- Accent: `#2563eb` (bright blue)

**Light Mode**:
- Background: `#f9fafb` (off-white)
- Text: `#111827` (dark gray/black)
- Cards: `#ffffff` (white)
- Accent: `#2563eb` (blue)

#### 3. **Responsive Design**
- Mobile-first approach
- Breakpoints:
  - Desktop: Full multi-column layouts
  - Tablet: Adjusted spacing and grid
  - Mobile: Single column, optimized touch targets
- POS layout adapts: Products on left, cart on right (desktop) or stacked (mobile)

#### 4. **Visual Components**
- **Cards**: Consistent styling with shadows and borders
- **Buttons**: Styled with hover effects and active states
- **Forms**: Professional input styling with focus states
- **Tables**: Striped rows, hover highlighting
- **Alerts**: Color-coded messages (green for success, red for error)
- **Modals**: Overlay dialogs for confirmations and details

#### 5. **Color Coding & Indicators**
- **Profit**: Green for positive, red for negative
- **Stock Levels**: 
  - Green: Well stocked (>10 units)
  - Orange: Warning (6-10 units)
  - Red: Critical (<5 units)
- **Payment Methods**: Different colors for cash/card/e-wallet

#### 6. **Typography**
- **Font Family**: Open Sans (fallback: system fonts)
- **Sizes**: 
  - Headers: 1.75em-2em
  - Body: 14px
  - Small: 12px
- **Weights**: Regular (400), Medium (500), Bold (600), Extra Bold (700)

#### 7. **Accessibility**
- Semantic HTML structure
- ARIA labels on interactive elements
- Color contrast ratios meet WCAG standards
- Keyboard navigation support
- Focus states clearly visible

#### 8. **Performance Optimizations**
- CSS minification in production
- JavaScript bundling
- Image optimization
- Lazy loading for charts
- Session caching (e.g., Category.php caches categories)

---

## Advanced Features & Technical Considerations

### Database Relationships
```
users (1) ──── (N) orders
           ──── (N) order_items
           
categories (1) ──── (N) products
                ──── (N) order_items

products (1) ──── (N) order_items
         ──── (N) product_sizes
```

### Security Features (Current)
- ✅ PDO prepared statements prevent SQL injection
- ✅ Role-based access control
- ✅ Session-based authentication
- ⚠️ Passwords stored in plaintext (should hash with `password_hash()`)
- ⚠️ No CSRF token protection
- ⚠️ No input validation on some endpoints

### Performance Considerations
- Category caching reduces database queries
- Product lookup is efficient (indexed by ID)
- Order creation is transactional
- Charts use Chart.js library (client-side rendering)

### Scalability Notes
- Current design suitable for small to medium businesses
- With high volume, consider:
  - Database indexing optimization
  - API response caching
  - Pagination for large data sets
  - Chart data aggregation on backend

---

This completes the detailed features guide for the Simple POS System. Each feature is designed to work together to create a complete point-of-sale solution for retail businesses.
