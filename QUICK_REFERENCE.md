# Quick Reference: Export & Backup

## 🎯 Quick Start

### Export Expense Report
1. Go to: **Admin Dashboard → Expense & Cost Report**
2. Set dates and category filter
3. Click **Generate Report**
4. Choose export type:
   - **CSV**: For Excel/Sheets
   - **PDF**: For printing/sharing

### Backup Database
1. Go to: **Admin Dashboard → Database Backup & Restore**
2. Click **📥 Download Backup Now**
3. Save the file safely (use external drive/cloud)

### Restore from Backup
1. Go to: **Admin Dashboard → Database Backup & Restore**
2. Click **Choose File** and select backup
3. Click **🔄 Restore Database**
4. Confirm when prompted

---

## 📊 What Gets Exported

### CSV/PDF Reports
- Date range summary
- Order metrics
- Revenue & profit totals
- Product-by-product breakdown

### Database Backup
- All users & roles
- All products & categories
- All orders & transactions
- All settings & configuration

---

## ⚠️ Important Notes

**BACKUP SAFETY**:
- Keep at least 2 backup copies
- Store backups on external drive
- Test restore before relying on it
- Backup weekly minimum

**RESTORE WARNING**:
- Restoring replaces ALL current data
- Make a backup before restoring
- Restore cannot be undone easily

---

## 🔧 File Information

**Export CSV**
- Format: `.csv`
- Size: 5-50 KB
- Use: Excel, Google Sheets, data analysis

**Export PDF**
- Format: `.pdf`
- Size: 20-100 KB
- Use: Printing, professional reports

**Database Backup**
- Format: `.sql`
- Size: 1-10 MB
- Use: Complete system restore

---

## 📋 Checklist

### Monthly Maintenance
- [ ] Download database backup
- [ ] Export expense reports
- [ ] Store backups safely
- [ ] Test restore procedure

### When Needed
- [ ] Export report for analysis
- [ ] Export report for tax/audit
- [ ] Create backup before update
- [ ] Restore from backup if needed

---

## ❓ FAQ

**Q: How often should I backup?**
A: At least weekly, daily recommended

**Q: Can I restore to a specific date?**
A: Only full restores available; use exported CSVs for historical data

**Q: Where should I store backups?**
A: External drive, cloud storage, or network backup

**Q: How long do exports take?**
A: Usually 2-5 seconds for reports

**Q: How long do backups take?**
A: 10-60 seconds depending on database size

---

## 🎓 Best Practices

✅ **DO:**
- Backup every day
- Keep 4 weeks of backups
- Store off-site copies
- Test restores monthly
- Document backup schedule
- Export for important reports

❌ **DON'T:**
- Rely on single backup
- Delete old backups immediately
- Restore without backup first
- Store backups on same server only

---

**Last Updated**: December 15, 2025
