# Testing & Deployment Guide

## ✅ Pre-Deployment Checklist

### Code Quality
- [ ] All PHP files have proper error handling
- [ ] SQL injection prevention implemented (PDO prepared statements)
- [ ] XSS prevention (htmlspecialchars on output)
- [ ] Session validation on all admin endpoints
- [ ] Headers properly set for file downloads

### Functionality Tests
- [ ] CSV export downloads correctly
- [ ] CSV file opens in Excel/Google Sheets
- [ ] PDF export downloads correctly
- [ ] PDF displays content properly
- [ ] Database backup creates valid SQL
- [ ] Database restore successfully imports SQL
- [ ] Error messages display properly
- [ ] Confirmation dialogs work

### Security Tests
- [ ] Non-admin users cannot access export/backup
- [ ] File upload validates file type
- [ ] Large files handled properly
- [ ] Session timeout protection works
- [ ] File permissions correct on server

### UI/UX Tests
- [ ] Buttons display correctly
- [ ] Icons render properly
- [ ] Responsive design on mobile
- [ ] Error messages clear and helpful
- [ ] Loading indicators work
- [ ] Navigation links functional

## 🧪 Manual Testing Procedures

### Test 1: CSV Export
```
1. Login as admin
2. Go to Expense & Cost Report
3. Set date range (past month)
4. Click "Generate Report"
5. Click "Export CSV"
6. Verify file downloads
7. Open in Excel/Sheets
8. Check data formatting
9. Verify all columns present
10. Verify calculations correct
```

### Test 2: PDF Export
```
1. Login as admin
2. Go to Expense & Cost Report
3. Set date range (past month)
4. Click "Generate Report"
5. Click "Export PDF"
6. Verify file downloads
7. Open PDF viewer
8. Check layout and formatting
9. Verify all metrics present
10. Try printing to confirm format
```

### Test 3: Database Backup
```
1. Login as admin
2. Go to Database Backup & Restore
3. Click "Download Backup Now"
4. Verify file downloads
5. Check file size (should be 1-10 MB)
6. Open with text editor
7. Verify SQL syntax correct
8. Check for all tables included
9. Verify data present
10. Move to safe location
```

### Test 4: Database Restore
```
1. Create test database with sample data
2. Go to Database Backup & Restore
3. Download backup file
4. Make changes to database
5. Verify changes visible
6. Go to Restore section
7. Upload backup file
8. Click Restore button
9. Confirm warning dialog
10. Wait for completion message
11. Verify data restored to backup state
```

### Test 5: Permission Testing
```
1. Logout or use incognito browser
2. Try to access /api/export_expenses_csv.php directly
3. Verify 403 Unauthorized returned
4. Try to access /api/backup_database.php directly
5. Verify 403 Unauthorized returned
6. Login as cashier (non-admin)
7. Try to access export buttons
8. Verify access denied (if restricted)
```

### Test 6: Edge Cases
```
Test 1: Empty date range
  → Export should return no data
  → Should show appropriate message

Test 2: Single day range
  → Should export only that day's data
  → Calculations should be accurate

Test 3: Large date range
  → Should handle efficiently
  → File size should be reasonable

Test 4: No data for range
  → Should return empty report
  → Should not error

Test 5: Invalid file upload
  → Should reject non-SQL files
  → Should show error message

Test 6: Corrupted backup file
  → Should fail gracefully
  → Should show clear error
```

## 🚀 Deployment Steps

### 1. Pre-Deployment
```bash
# Backup current system
mysqldump -u username -p database > backup_before_deploy.sql

# Test in development environment first
# Verify all tests pass
```

### 2. File Deployment
```bash
# Copy files to production
cp api/export_expenses_csv.php /production/api/
cp api/export_expenses_pdf.php /production/api/
cp api/backup_database.php /production/api/
cp admin_backup.php /production/
cp admin_expenses.php /production/  # Updated version

# Set proper permissions
chmod 644 api/export_expenses_csv.php
chmod 644 api/export_expenses_pdf.php
chmod 644 api/backup_database.php
chmod 644 admin_backup.php
chmod 644 admin_expenses.php
```

### 3. Database Preparation
```bash
# Verify database integrity
mysql -u username -p database -e "REPAIR TABLE;"

# Create backups directory (if using automated backups)
mkdir -p /backups
chmod 755 /backups
```

### 4. Permission Verification
```bash
# Verify web server can read files
ls -la api/export_expenses_csv.php
ls -la api/export_expenses_pdf.php
ls -la api/backup_database.php
ls -la admin_backup.php

# Verify temp directory writable
chmod 777 /tmp
```

### 5. Configuration Verification
```php
# Verify in _init.php
- DB_HOST set correctly
- DB_USERNAME set correctly
- DB_PASSWORD set correctly
- DB_DATABASE set correctly
- Session configuration correct
```

### 6. Post-Deployment Testing
```bash
# Test CSV export
curl -X POST http://yoursite.com/api/export_expenses_csv.php \
  -H "Content-Type: application/json" \
  -d '{"start_date":"2025-01-01","end_date":"2025-01-31","category_id":null}'

# Test backup
curl -X POST http://yoursite.com/api/backup_database.php

# Test webpage loads
curl http://yoursite.com/admin_backup.php
```

## 📋 Deployment Checklist

### Pre-Deployment
- [ ] Code review completed
- [ ] All tests passing
- [ ] Database backup created
- [ ] Rollback plan documented
- [ ] Team notified

### Deployment
- [ ] Files copied to production
- [ ] Permissions set correctly
- [ ] Configuration verified
- [ ] Database integrity checked
- [ ] Web server restarted

### Post-Deployment
- [ ] All features tested
- [ ] Error logs checked
- [ ] Performance verified
- [ ] Users notified
- [ ] Documentation updated

## 🔍 Monitoring & Maintenance

### Daily Checks
```
□ Error logs reviewed
□ Database backup completed
□ No failed exports reported
□ Performance acceptable
```

### Weekly Checks
```
□ Backup integrity verified
□ Test restore procedure
□ Error logs analyzed
□ Performance metrics reviewed
□ Security logs checked
```

### Monthly Checks
```
□ Full backup test
□ Documentation updated
□ Performance optimization review
□ Storage space verified
□ Security audit conducted
```

## 🚨 Troubleshooting Guide

### Issue: Export button not working
**Solution:**
1. Check browser console (F12)
2. Verify admin session active
3. Check PHP error logs
4. Verify date range selected
5. Clear browser cache

### Issue: PDF export returns HTML
**Solution:**
1. This is expected fallback behavior
2. Still can print to PDF from browser
3. Or install FPDF library for native PDF

### Issue: Backup file too large
**Solution:**
1. Normal for large databases
2. Consider removing old transactions
3. Or use command-line mysqldump

### Issue: Restore fails
**Solution:**
1. Verify SQL file not corrupted
2. Check database user permissions
3. Verify backup from same POS version
4. Check available disk space

### Issue: Permission denied on files
**Solution:**
1. Check web server ownership
2. Verify file permissions (644)
3. Check directory permissions (755)
4. Restart web server

### Issue: Session timeout during operation
**Solution:**
1. Increase session timeout in _init.php
2. For large backups, use command-line tools
3. Or upload files manually

## 📊 Performance Benchmarks

### Expected Times
```
CSV Export (1000 orders)    : 1-3 seconds
PDF Export (1000 orders)    : 3-7 seconds
Database Backup (10 MB)     : 15-30 seconds
Database Restore (10 MB)    : 20-40 seconds
```

### Resource Usage
```
Memory Peak                 : 50-100 MB
CPU Usage                   : 20-30%
Disk I/O                    : Moderate
Network Bandwidth           : 1-10 MB per export
```

## 🔐 Security Checklist

- [ ] All endpoints require admin authentication
- [ ] PDO prepared statements used
- [ ] Input validation implemented
- [ ] Output properly escaped
- [ ] File permissions restricted
- [ ] Error messages don't expose system info
- [ ] Session cookies secure
- [ ] CSRF tokens implemented (if applicable)
- [ ] Rate limiting considered
- [ ] Audit logging in place

## 📝 Documentation

### For Administrators
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
- [EXPORT_BACKUP_GUIDE.md](EXPORT_BACKUP_GUIDE.md)

### For Developers
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)
- This file

## 📞 Support & Maintenance

### Emergency Contact
- Database corruption? Restore from backup immediately
- Export failures? Check PHP error logs
- Permission issues? Verify web server ownership

### Regular Maintenance Tasks
1. Monthly: Test backup/restore procedure
2. Weekly: Verify backup file integrity
3. Daily: Check error logs
4. Quarterly: Review and archive old backups

## ✅ Sign-Off Checklist

- [ ] All tests completed successfully
- [ ] Security review passed
- [ ] Performance acceptable
- [ ] Documentation complete
- [ ] Team trained
- [ ] Deployment approved
- [ ] Monitoring set up
- [ ] Rollback plan ready

---

**Version**: 1.0
**Last Updated**: December 15, 2025
**Status**: Ready for Deployment ✅
