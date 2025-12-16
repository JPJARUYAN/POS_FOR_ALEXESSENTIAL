# Staff Performance Reports Guide

## Overview
The Staff Performance Reports module provides comprehensive sales and activity metrics for each cashier in your POS system. Track individual cashier performance, compare results, and identify top performers.

## Features

### 1. **Performance Metrics**
For each cashier, the system tracks:
- **Total Sales** - Cumulative sales amount
- **Transaction Count** - Number of transactions completed
- **Average Transaction Value** - Average amount per transaction
- **Items Sold** - Total number of distinct items sold
- **Total Quantity** - Total units/quantity sold
- **Average Items Per Transaction** - Efficiency metric

### 2. **Date Range Filtering**
- Filter reports by custom date ranges
- Select specific cashier or view all
- Compare performance across different periods

### 3. **Performance Rankings**
- Automatic ranking by total sales
- Visual badges for top 3 performers (Gold, Silver, Bronze)
- Compare cashiers at a glance

### 4. **Payment Method Breakdown**
- See sales by payment method (Cash, Card, etc.)
- Transaction count per payment method
- Identify payment method preferences

### 5. **Daily Sales Trend**
- Visual line chart showing daily sales trends
- Identify peak sales days
- Track consistency over time

### 6. **Overall Statistics**
- Total system transactions in period
- Total system sales
- Number of active cashiers
- Average sales per cashier

## How to Use

### Accessing the Reports

1. **Login as Admin**
2. **Click "Staff Performance"** in the sidebar
3. **View all cashiers and their metrics**

### Filtering Reports

1. **Set Start Date** - First day of reporting period
2. **Set End Date** - Last day of reporting period
3. **Select Cashier (Optional)** - View single cashier or all
4. **Click Filter** - Generate report

### Understanding the Cards

Each cashier has a performance card showing:
- **Header** - Cashier name with ranking badge (if top 3)
- **Key Metrics** - Sales, transactions, averages
- **Payment Breakdown** - Sales by payment method
- **Daily Trend Chart** - Sales performance over time

### Interpreting Rankings

- **Gold Badge (🥇 #1)** - Highest total sales in period
- **Silver Badge (🥈 #2)** - Second highest sales
- **Bronze Badge (🥉 #3)** - Third highest sales
- No badge - Other cashiers ranked below top 3

## Metrics Explained

### Total Sales
The total monetary value of all transactions completed by the cashier in the period.
```
Formula: SUM(item_price × item_quantity) for all transactions
```

### Average Transaction Value
How much the cashier sells per transaction on average.
```
Formula: Total Sales ÷ Transaction Count
Higher = Better sales performance
```

### Items Sold
The number of distinct product types sold (not quantity).
```
Useful for measuring product variety per cashier
```

### Total Quantity
The total number of units/items sold by the cashier.
```
Formula: SUM(quantity) for all items
Useful for volume metrics
```

### Average Items Per Transaction
How many items the cashier sells per transaction.
```
Formula: Total Items ÷ Transaction Count
Higher = Better cross-selling or bundling
```

## Use Cases

### Performance Evaluation
- **Monthly Reviews** - Compare cashier performance monthly
- **Identify Top Performers** - Recognize and reward best sellers
- **Find Underperformers** - Coach or train cashiers with lower metrics

### Sales Analysis
- **Peak Days** - Identify busy days and staff accordingly
- **Payment Trends** - See which payment methods are preferred
- **Growth Tracking** - Monitor month-over-month growth

### Coaching & Development
- **Consistency** - Check if performance is stable over time
- **Benchmarking** - Compare cashier to team average
- **Best Practices** - Learn from top performers' patterns

### Scheduling
- **Peak Hours** - Schedule best performers during busy times
- **Training** - Pair new cashiers with high performers
- **Team Balance** - Ensure good mix during shifts

## Example Scenarios

### Scenario 1: Monthly Performance Review
**Period**: January 1 - January 31
**Report Shows**:
- John (🥇 #1): ₱50,000 total sales, 500 transactions
- Maria (🥈 #2): ₱45,000 total sales, 480 transactions
- Alex (🥉 #3): ₱40,000 total sales, 420 transactions

**Insights**:
- John is the top performer
- John's average transaction (₱100) is higher than Maria's (₱93.75)
- Consider giving John a bonus or promotion

### Scenario 2: Identifying Training Needs
**John's Metrics**:
- Total Sales: ₱50,000
- Transactions: 500
- Average per Transaction: ₱100
- Items per Transaction: 3.5

**Alex's Metrics**:
- Total Sales: ₱40,000
- Transactions: 530
- Average per Transaction: ₱75.47
- Items per Transaction: 2.1

**Insight**:
- Alex does more transactions but lower value
- Alex needs training on upselling/cross-selling
- Focus on increasing items per transaction for Alex

### Scenario 3: Payment Method Analysis
**Report Period**: Last 30 days

**John's Payment Breakdown**:
- Cash: ₱30,000 (60%)
- Card: ₱15,000 (30%)
- Check: ₱5,000 (10%)

**Insight**:
- Cash is dominant payment method
- Consider promoting card payments for faster processing
- Ensure adequate change available

## Database Queries

The system uses these queries:

### Get Cashier Sales Summary
```sql
SELECT 
    COUNT(DISTINCT o.id) as transaction_count,
    SUM(oi.price * oi.quantity) as total_sales,
    COUNT(DISTINCT oi.id) as item_count,
    SUM(oi.quantity) as total_quantity
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE o.cashier_id = :cashier_id 
    AND DATE(o.created_at) >= :start_date 
    AND DATE(o.created_at) <= :end_date
GROUP BY o.id
```

### Get Daily Trend
```sql
SELECT 
    DATE(o.created_at) as date,
    COUNT(o.id) as transaction_count,
    SUM(oi.price * oi.quantity) as sales_amount
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE o.cashier_id = :cashier_id 
    AND DATE(o.created_at) >= :start_date 
    AND DATE(o.created_at) <= :end_date
GROUP BY DATE(o.created_at)
ORDER BY date ASC
```

### Get Payment Method Breakdown
```sql
SELECT 
    o.payment_method,
    COUNT(o.id) as transaction_count,
    SUM(oi.price * oi.quantity) as sales_amount
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE o.cashier_id = :cashier_id 
    AND DATE(o.created_at) >= :start_date 
    AND DATE(o.created_at) <= :end_date
GROUP BY o.payment_method
```

## Tips for Better Analysis

1. **Compare Like Periods** - Compare same weekdays/dates for fairness
2. **Account for Shifts** - Consider if cashiers work different hours
3. **Look for Trends** - Don't judge on single day; look at patterns
4. **Factor in Team Effort** - Some sales are team efforts
5. **Celebrate Wins** - Recognize and reward top performers publicly

## Common Questions

**Q: Why does one cashier have more transactions?**
A: They may work longer hours, different shifts, or handle faster customers. Check average transaction value for true performance.

**Q: How do I motivate lower performers?**
A: Provide coaching, pair with top performers, set achievable goals, and recognize improvements.

**Q: Can I export these reports?**
A: Currently view-only. Contact admin for data export if needed.

**Q: What if a cashier is sick/absent?**
A: Filter by cashier or exclude from comparison if they worked fewer days.

**Q: Should cashiers see each other's metrics?**
A: It's a management decision. Can be motivating but also sensitive. Consider team vs. competitive culture.

## Best Practices

1. **Review Weekly** - Don't wait for monthly reviews
2. **Provide Feedback** - Share results with cashiers constructively
3. **Set Goals** - Use metrics to set improvement targets
4. **Celebrate Wins** - Publicly recognize top performers
5. **Continuous Training** - Use insights to improve team skills
6. **Fair Comparison** - Account for hours worked and circumstances
7. **Focus on Growth** - Highlight improvement, not just absolute numbers
