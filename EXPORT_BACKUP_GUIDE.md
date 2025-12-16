# Data Export & Backup Features

## Overview
Complete CSV/PDF export and database backup/restore functionality has been added to your POS System.

## Features Implemented

### 1. **CSV Export** 
- Export expense reports as CSV files
- Includes summary metrics and product performance data
- Compatible with Excel, Google Sheets, and other spreadsheet applications
- UTF-8 encoding with BOM for proper Excel display
- **Location**: `api/export_expenses_csv.php`
- **Access**: Admin-only, via Expense Report page

### 2. **PDF Export**
- Export reports as PDF documents
- Professional formatting with metrics and tables
- Fallback to HTML printable format if FPDF not available
- Includes date range, summary metrics, and product details
- **Location**: `api/export_expenses_pdf.php`
- **Access**: Admin-only, via Expense Report page

### 3. **Database Backup**
- Complete SQL database dump including all tables and data
- Download backup files to your computer
- Timestamped filenames for easy version management
- Includes restore instructions in the backup file
- **Location**: `api/backup_database.php`
- **Access**: Admin-only

### 4. **Database Restore**
- Upload and restore from previously downloaded backups
- Web-based restore interface with safety confirmations
- Database statistics dashboard
- Detailed information and warnings about restore operation
- **Location**: `admin_backup.php`
- **Access**: Admin-only

## File Locations

### New API Endpoints:
```
api/
├── export_expenses_csv.php      # CSV export handler
├── export_expenses_pdf.php      # PDF export handler
└── backup_database.php          # Database backup handler
```

### New Admin Pages:
```
admin_backup.php                 # Backup/Restore management page
```

### Modified Files:
```
admin_expenses.php               # Added export buttons and JavaScript functions
```

## Usage Instructions

### Exporting from Expense Report Page

1. Navigate to **Admin Dashboard → Expense & Cost Report**
2. Set your desired date range and category filters
3. Click **Generate Report** to see the data
4. Use one of the export buttons:
   - **📥 Export CSV** - Download as spreadsheet
   - **📄 Export PDF** - Download as PDF document
   - **💾 Backup Database** - Download complete database backup

### Backup & Restore

1. Navigate to **Admin Dashboard → Database Backup & Restore**
2. **To Create Backup**:
   - Click **📥 Download Backup Now**
   - File will download as `pos_backup_YYYY-MM-DD_timestamp.sql`
3. **To Restore**:
   - Click **Choose File** and select a previously downloaded SQL backup
   - Read the warning carefully
   - Click **🔄 Restore Database**
   - Wait for completion message

## API Details

### Export CSV Endpoint
**POST** `/api/export_expenses_csv.php`

Request Body:
```json
{
  "start_date": "2025-01-01",
  "end_date": "2025-01-31",
  "category_id": null
}
```

Returns: CSV file download

### Export PDF Endpoint
**POST** `/api/export_expenses_pdf.php`

Request Body:
```json
{
  "start_date": "2025-01-01",
  "end_date": "2025-01-31",
  "category_id": null
}
```

Returns: PDF file download (or HTML if FPDF not available)

### Backup Database Endpoint
**POST** `/api/backup_database.php`

Returns: SQL file download with complete database dump

## Security Features

✅ Admin-only access enforcement
✅ Session validation on all endpoints
✅ SQL injection protection (PDO prepared statements)
✅ File upload validation
✅ User confirmation dialogs before destructive operations
✅ Comprehensive error handling

## Data Included in Exports

### CSV/PDF Reports Include:
- Date range information
- Summary metrics:
  - Total orders
  - Total items sold
  - Total revenue
  - Total expenses
  - Total profit
  - Profit margin percentage
- Product performance table:
  - Product name
  - Category
  - Unit cost
  - Unit price
  - Quantity sold
  - Total cost
  - Total revenue
  - Total profit

### Database Backup Includes:
- All tables with current schema
- All data rows from each table
- CREATE TABLE statements
- Proper NULL handling
- Restore instructions

## File Size Expectations

- **CSV Reports**: 5-50 KB (varies by data volume)
- **PDF Reports**: 20-100 KB (varies by data volume)
- **Database Backups**: 1-10 MB (typical POS system)

## Browser Compatibility

- ✅ Chrome/Edge (recommended)
- ✅ Firefox
- ✅ Safari
- ✅ Mobile browsers

## Troubleshooting

### Export Button Not Working
1. Verify you're logged in as admin
2. Check browser console for errors (F12)
3. Ensure date range is selected

### PDF Shows as HTML
- FPDF library not installed
- PDF can still be printed from browser (Ctrl+P)
- Consider installing FPDF: `composer require setasign/fpdf`

### Backup File Too Large
- Large databases may take time to download
- Consider using command-line backup: `mysqldump -u user -p database > backup.sql`

### Restore Fails
- Verify the SQL file is valid
- File should end with appropriate SQL statements
- Check file isn't corrupted or truncated

## Database Restoration Notes

**Important**: 
- Restoring will **replace all current data**
- Always verify the backup file before restoring
- Keep multiple backup versions
- Test restore on non-production system first

## Performance Considerations

- Large backups may take 30+ seconds to generate
- Restoring large databases may take 1+ minutes
- CSV/PDF exports are fast (typically < 5 seconds)
- Consider scheduling backups during off-peak hours

## Future Enhancements

Possible additions:
- Automatic daily backups
- Cloud backup integration (AWS S3, Google Cloud Storage)
- Incremental backups
- Email backup delivery
- Scheduled backup exports
- Advanced filtering for exports

## Support

For issues or questions:
1. Check this documentation
2. Review browser console errors (F12)
3. Verify admin permissions
4. Check file permissions on server

---

**Version**: 1.0
**Last Updated**: December 15, 2025
**Status**: Production Ready ✅
