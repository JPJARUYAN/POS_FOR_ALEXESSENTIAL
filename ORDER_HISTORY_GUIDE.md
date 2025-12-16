# Order History & Receipt Reprint Feature Guide

## Overview
The cashier interface now includes a complete **Order History** system that allows cashiers to quickly access previous transactions, search for specific orders, and reprint receipts without requiring admin access.

## Features Implemented

### 1. **Order History Modal**
- Access via "📅 Order History" button in the cart header
- Keyboard shortcut: **Ctrl+H** (Cmd+H on Mac)
- Shows last 30 days of transactions
- Maximum 100 orders displayed

### 2. **Order Search & Filter**
- **Date Filter**: Search orders by specific date
- **Customer Search**: Filter by customer name (partial match)
- Real-time filtering as you type/change date
- Shows "No orders found" when filters have no results

### 3. **Order Information Display**
Each order card shows:
- **Order ID**: Unique identifier for tracking
- **Date & Time**: When the order was placed
- **Total Amount**: Order total in PHP ₱
- **Customer Name**: Or "Walk-in Customer" if anonymous
- **Item Count**: Number of items in the order
- **Payment Method**: Cash, Card, E-wallet, etc.
- **Reprint Button**: One-click receipt reprinting

### 4. **Receipt Reprinting**
- Click "🖨️ Reprint Receipt" on any order card
- Opens receipt PDF in a new tab/window
- Same format as original receipt
- No re-processing of payment required
- Useful for customers requesting duplicate receipts

## How to Use

### Accessing Order History
1. **Button Method**: Click the "📅 Order History" button in the cart header
2. **Keyboard Shortcut**: Press `Ctrl+H` (Windows/Linux) or `Cmd+H` (Mac)
3. Modal opens showing your recent orders

### Searching for Orders

#### By Date
1. Click the date input field in the modal
2. Select the date you want to view
3. Orders list updates automatically

#### By Customer Name
1. Type in the "Search customer name..." field
2. Results filter in real-time
3. Partial matches work (e.g., "Alex" finds "Alexander")

#### Combining Filters
- Use both date AND customer filters together
- Only orders matching ALL filters will display

### Reprinting a Receipt
1. Find the order in the history list
2. Click "🖨️ Reprint Receipt" button
3. Receipt PDF opens in new browser tab
4. Print or save as needed
5. No confirmation needed - safe to use multiple times

## Technical Details

### Database Query
Orders are retrieved from the current cashier's transactions from the past 30 days:
```sql
SELECT orders with items grouped by order_id
WHERE cashier_id = current_cashier
AND created_at >= 30 days ago
ORDER BY created_at DESC (most recent first)
LIMIT 100
```

### API Endpoint
- **URL**: `api/cashier_controller.php?action=get_orders`
- **Method**: GET
- **Authentication**: Requires active cashier session
- **Response**: JSON with orders array

### Data Included per Order
```json
{
  "order_id": 12345,
  "created_at": "2025-12-17T14:30:00",
  "total_amount": "2500.00",
  "payment_method": "cash",
  "customer_name": "John Doe",
  "items": [
    {
      "product_id": 1,
      "product_name": "T-Shirt XL",
      "quantity": 2,
      "price": "500.00"
    }
  ]
}
```

## Design Consistency

The Order History modal follows the same design system as the rest of the POS:
- **Gradient Theme**: Purple/Blue (#667eea to #764ba2)
- **Dark Mode**: Compatible with system dark/light theme
- **Responsive**: Works on screens 560px wide and up
- **Animations**: Smooth transitions and hover effects

## Keyboard Shortcuts Summary

| Shortcut | Action |
|----------|--------|
| **F2** | Focus product search |
| **F8** | Open checkout/payment |
| **Ctrl+H** | Open order history |
| **Escape** | Close any modal |

## Common Use Cases

### 1. Customer Requests Duplicate Receipt
1. Press Ctrl+H to open order history
2. Search by customer name or date
3. Click Reprint Receipt
4. Send/print the PDF

### 2. Verify Previous Transaction
1. Open order history (Ctrl+H)
2. Filter by date to find the time period
3. Review order amount and items
4. Close modal to continue selling

### 3. Track Daily Sales
1. Open order history each day
2. Filter by today's date
3. Review all transactions and totals
4. Use for reconciliation with manager

### 4. Find Customer's Regular Orders
1. Open order history (Ctrl+H)
2. Search by customer name in filter
3. See all their purchases
4. Helpful for personalized service

## Important Notes

⚠️ **30-Day Window**: Only orders from the last 30 days are shown
- Older orders can be viewed through the admin Sales page
- This limits data size for better performance

📋 **Order ID**: Use order ID for receipts and customer inquiries
- Printed on receipt for reference
- Appears in order history for lookup

🖨️ **Receipts**: Can be reprinted unlimited times
- No impact on inventory or payment
- Same format as original
- Customer gets same receipt details

## Troubleshooting

### "Loading orders..." stays visible
- Check internet connection
- Verify API is responding
- Clear browser cache and refresh

### Order not appearing in history
- Check date filter matches order date
- Verify you're the cashier who processed it (own orders only)
- Order may be older than 30 days

### Receipt won't print
- Check PDF is opening in new tab
- Browser popup blocker may be interfering
- Try right-click "Print" instead of browser print

## Feature Benefits

✅ **Quick Access**: No admin login needed
✅ **Fast Search**: Filter orders in seconds
✅ **Professional Service**: Reprint receipts instantly
✅ **Better Reconciliation**: View your transactions
✅ **Customer Satisfaction**: Duplicate receipts on demand
✅ **Improved Workflow**: Ctrl+H shortcut keeps hands on keyboard

## Related Documentation
- [Cashier Interface Guide](./FEATURES_GUIDE.md) - Overview of all cashier features
- [Keyboard Shortcuts Reference](./QUICK_REFERENCE.md) - All system shortcuts
- [POS System Architecture](./ARCHITECTURE.md) - Technical system overview
