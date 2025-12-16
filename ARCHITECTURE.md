# System Architecture: Export & Backup Features

## 📊 Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     ADMIN INTERFACE                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  admin_expenses.php                 admin_backup.php            │
│  ├─ Expense Reports                 ├─ Backup Management        │
│  ├─ Export CSV Button               ├─ Upload Restore Form      │
│  ├─ Export PDF Button               ├─ Download Backup Button   │
│  └─ Backup Database Button          └─ Database Statistics      │
│                                                                   │
└──────────────┬──────────────────────────────┬───────────────────┘
               │                              │
               ↓                              ↓
┌──────────────────────────────────────────────────────────────────┐
│                      API ENDPOINTS                                │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  api/export_expenses_csv.php        api/backup_database.php     │
│  ├─ Query expense data              ├─ Get all tables           │
│  ├─ Format as CSV                   ├─ Generate SQL dump        │
│  └─ Stream to browser               └─ Stream to browser        │
│                                                                   │
│  api/export_expenses_pdf.php                                    │
│  ├─ Query expense data                                          │
│  ├─ Format as PDF/HTML                                          │
│  └─ Stream to browser                                           │
│                                                                   │
└──────────────┬───────────────────────────┬──────────────────────┘
               │                           │
               ↓                           ↓
┌──────────────────────────────────────────────────────────────────┐
│                      DATABASE                                     │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │   Orders     │  │   Products   │  │   Users      │           │
│  ├──────────────┤  ├──────────────┤  ├──────────────┤           │
│  │ - id         │  │ - id         │  │ - id         │           │
│  │ - user_id    │  │ - name       │  │ - email      │           │
│  │ - created_at │  │ - cost       │  │ - role       │           │
│  │ - total      │  │ - price      │  │ - created_at │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │  Categories  │  │ Order Items  │  │  Customers   │           │
│  ├──────────────┤  ├──────────────┤  ├──────────────┤           │
│  │ - id         │  │ - id         │  │ - id         │           │
│  │ - name       │  │ - order_id   │  │ - name       │           │
│  │ - created_at │  │ - product_id │  │ - email      │           │
│  └──────────────┘  │ - quantity   │  │ - phone      │           │
│                    │ - price      │  └──────────────┘           │
│                    └──────────────┘                              │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
               ↑                           ↑
               └───────────────────────────┘
                   PDO Connection
```

## 🔄 Data Flow: CSV Export

```
User clicks "Export CSV"
        ↓
JavaScript sends POST request
        ↓
export_expenses_csv.php
        ├─ Verify admin session
        ├─ Parse request parameters
        ├─ Query database
        │  ├─ Metrics query
        │  └─ Product details query
        ├─ Format as CSV
        │  ├─ Add headers
        │  ├─ Add metrics section
        │  └─ Add product rows
        ├─ Set download headers
        └─ Stream to browser
        ↓
Browser downloads .csv file
        ↓
User opens in Excel/Sheets
```

## 🔄 Data Flow: Database Backup

```
User clicks "Download Backup"
        ↓
JavaScript sends POST request
        ↓
backup_database.php
        ├─ Verify admin session
        ├─ Get all table names
        ├─ For each table:
        │  ├─ Get CREATE TABLE statement
        │  ├─ Get all rows
        │  └─ Generate INSERT statements
        ├─ Build complete SQL file
        ├─ Set download headers
        └─ Stream to browser
        ↓
Browser downloads .sql file
        ↓
User stores safely
```

## 🔄 Data Flow: Database Restore

```
User selects backup file
        ↓
Form submitted to admin_backup.php
        ↓
PHP processes upload
        ├─ Verify admin session
        ├─ Validate file type
        ├─ Read SQL content
        ├─ Begin transaction
        ├─ Parse SQL statements
        ├─ Execute each statement
        ├─ Commit transaction
        └─ Display result message
        ↓
Database updated with backup data
        ↓
User sees success/error message
```

## 🔀 Request Flow: Export Features

```
BROWSER                     SERVER                    DATABASE
   │                           │                          │
   │──POST /api/export_*─────→ │                          │
   │                           │                          │
   │                           ├─Session Check           │
   │                           │                          │
   │                           ├─Query───────────────────→│
   │                           │                          │
   │                           │←─Rows─────────────────────
   │                           │                          │
   │                           ├─Format Data              │
   │                           │                          │
   │←─File Download───────────│                          │
   │                           │                          │
```

## 📦 File Structure

```
POS_SYSTEM/
├── api/
│   ├── export_expenses_csv.php      ← CSV export endpoint
│   ├── export_expenses_pdf.php      ← PDF export endpoint
│   ├── backup_database.php          ← Backup endpoint
│   └── [other endpoints...]
│
├── admin_expenses.php               ← Report page (modified)
├── admin_backup.php                 ← Backup/Restore page (new)
│
├── templates/
│   ├── admin_header.php
│   └── admin_navbar.php
│
├── css/
│   ├── admin.css
│   ├── main.css
│   └── [other styles...]
│
└── documentation/
    ├── EXPORT_BACKUP_GUIDE.md       ← Full guide
    ├── QUICK_REFERENCE.md           ← Quick start
    └── IMPLEMENTATION_SUMMARY.md    ← Technical details
```

## 🔐 Security Layers

```
REQUEST
   ↓
┌─ HTTP Headers ─────────────────────────┐
│ - Content-Type: application/json       │
│ - Content-Disposition: attachment      │
└─────────────────────────────────────────┘
   ↓
┌─ Session Validation ───────────────────┐
│ - Check $_SESSION['user_id_admin']      │
│ - Verify admin role                     │
└─────────────────────────────────────────┘
   ↓
┌─ Input Validation ─────────────────────┐
│ - Date format validation                │
│ - File type validation                  │
│ - Required field checks                 │
└─────────────────────────────────────────┘
   ↓
┌─ Database Access ──────────────────────┐
│ - PDO Prepared Statements               │
│ - SQL Injection Protection              │
│ - Transaction Safety                    │
└─────────────────────────────────────────┘
   ↓
RESPONSE (Secure File Download)
```

## 🎯 Use Cases

### 1. Regular Backup
```
Admin Schedule → Daily 2:00 AM
    ↓
backup_database.php executes
    ↓
SQL file generated
    ↓
Stored on backup server
    ↓
Rotated monthly (keep 4 weeks)
```

### 2. Monthly Report Export
```
Admin End of Month → Click Export CSV
    ↓
export_expenses_csv.php queries data
    ↓
Metrics calculated
    ↓
CSV formatted and downloaded
    ↓
Sent to accountant
```

### 3. Emergency Restore
```
System Failure → Corruption Detected
    ↓
Admin accesses admin_backup.php
    ↓
Selects latest backup file
    ↓
Confirms restore (critical dialog)
    ↓
restore_database() executes
    ↓
Database restored to backup point
```

## 📊 Data Transformation

### CSV Transformation
```
Database Records
    ↓
    ├─ Metrics Aggregation
    │  ├─ COUNT(DISTINCT orders)
    │  ├─ SUM(revenue)
    │  ├─ SUM(expenses)
    │  └─ CALCULATE(profit)
    ↓
CSV Format
    ├─ Headers
    ├─ Summary Section
    ├─ Metrics Table
    └─ Product Details Table
```

### SQL Dump Format
```
Database Tables
    ↓
    ├─ DROP TABLE IF EXISTS
    ├─ CREATE TABLE statements
    ├─ INSERT statements
    └─ Metadata comments
    ↓
SQL File (.sql)
```

## 🔗 Integration Points

### With Existing Systems
```
Export/Backup ← Admin Interface
                 ├─ Session Management
                 ├─ User Authentication
                 └─ Authorization (roles)

            ← Database
                 ├─ PDO Connection
                 ├─ Table Structure
                 └─ Data Storage

            ← File System
                 ├─ Temp Directory
                 └─ Download Stream
```

## ⚡ Performance Architecture

```
USER REQUEST
    ↓
    ├─ Fast (< 2s)
    │  └─ CSV Export
    │     └─ Direct streaming, minimal processing
    │
    ├─ Medium (2-5s)
    │  └─ PDF Export
    │     └─ HTML rendering + PDF conversion
    │
    └─ Slow (10-60s)
       └─ Database Backup
          └─ Full table iteration + dump generation
```

---

**Architecture Version**: 1.0
**Last Updated**: December 15, 2025
