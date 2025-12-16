# Simple POS System

A comprehensive Point of Sale system built with PHP and MySQL. Features include inventory management, cashier operations, expense tracking, tax configuration, and staff performance monitoring.

## Features

- **Admin Dashboard** - Overview of sales, inventory, and business metrics
- **Cashier Sales** - Process customer transactions and generate receipts
- **Inventory Management** - Add, update, and track product stock levels
- **Category Management** - Organize products by categories
- **User Management** - Manage admin and cashier accounts
- **Expense Tracking** - Record and monitor business expenses
- **Tax Configuration** - Set up and manage tax rates
- **Supplier Management** - Track suppliers and orders
- **Order History** - View complete transaction history
- **Staff Performance** - Monitor cashier and staff metrics
- **Backup & Export** - Database backups and report exports

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server (or similar)
- XAMPP (recommended for local development)

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/POS_SYSTEM.git
cd POS_SYSTEM
```

### 2. Set Up the Database

1. Open phpMyAdmin
2. Create a new database named `simple_pos`
3. Import the database file:
   - Go to Import tab
   - Select `simple-pos.sql`
   - Click Import

### 3. Configure Database Connection

1. Copy `_config.example.php` to `_config.php`:
   ```bash
   cp _config.example.php _config.php
   ```

2. Edit `_config.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'root');
   define('DB_PASSWORD', 'your_password');
   define('DB_DATABASE', 'simple_pos');
   ```

### 4. Access the Application

1. Place the project in your web root (e.g., `C:\xampp\htdocs\POS_SYSTEM`)
2. Start Apache and MySQL in XAMPP
3. Open your browser and go to:
   ```
   http://localhost/POS_SYSTEM
   ```

## Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Cashier Account:**
- Username: `cashier`
- Password: `cashier123`

⚠️ **IMPORTANT:** Change these credentials immediately after first login!

## Project Structure

```
POS_SYSTEM/
├── api/              # API endpoints for AJAX requests
├── css/              # Stylesheets
├── js/               # JavaScript files
├── models/           # Database models and queries
├── templates/        # Reusable HTML components
├── migrations/       # Database migrations
├── backups/          # Database backups
├── receipts/         # Generated receipt files
├── admin_*.php       # Admin panel pages
├── cashier_*.php     # Cashier interface pages
├── index.php         # Home/login page
└── _config.php       # Database configuration (create from example)
```

## Documentation

See the [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) for detailed guides:

- [Admin Guide](ADMIN_GUIDE.md) - Admin features and management
- [Features Guide](FEATURES_GUIDE.md) - Complete feature overview
- [Order History Guide](ORDER_HISTORY_GUIDE.md) - Transaction history
- [Staff Performance Guide](STAFF_PERFORMANCE_GUIDE.md) - Staff metrics
- [Tax Config Guide](TAX_CONFIG_GUIDE.md) - Tax setup

## Key Features Usage

### Processing Sales
1. Go to Cashier Sales
2. Select items and quantities
3. Apply discounts if needed
4. Process payment
5. Print receipt

### Managing Inventory
1. Go to Admin → Products
2. Add new items or update existing
3. Manage stock levels
4. Organize by categories

### Tracking Expenses
1. Go to Admin → Expenses
2. Add expense entries
3. Categorize and note details
4. View expense reports

## Security Notes

- Always use HTTPS in production
- Change default login credentials
- Store `_config.php` securely (not in version control)
- Regularly backup your database
- Keep PHP and MySQL updated

## Database Backup

The system includes automated backup functionality:
1. Go to Admin → Backup
2. Click "Create Backup"
3. Backups are stored in `/backups/`

## Troubleshooting

**Issue:** "Cannot connect to database"
- Check database credentials in `_config.php`
- Ensure MySQL is running
- Verify database name exists

**Issue:** "Page not found"
- Check Apache is running
- Verify file paths in URL
- Check `.htaccess` configuration

## License

This project is licensed under the MIT License. See LICENSE file for details.

## Support

For issues, questions, or contributions:
1. Check existing documentation
2. Review [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)
3. Create an issue on GitHub

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

---

**Last Updated:** December 2025
