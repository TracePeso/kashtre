# Inventory orders — how quantities are calculated

When you **Make an order**, you choose one of **two methods** for suggested quantities. Both use the same stock picture (current stock, consumption rate, safety/buffer days). They differ in **how much** each item should receive.

Reference: `public/KashTre_new.xlsx` → **Inventory** sheet.  
Code: `InventoryStockAnalyticsService`, `InventoryOrderService`.

---

## Shared inputs (both methods need these)

Every calculation starts from the item’s position at the **receiving store**.

| What | Excel | Formula |
|------|-------|---------|
| **Current stock** | M | Physical count (AS) + purchases − sales + transfers since last stock count. If no count, on-hand ledger. |
| **Daily usage** | V or AA | 15-day moving average; if zero, fixed daily average from module config. |
| **Stock days** | N | Current stock ÷ daily usage |
| **Safety days** | AB | Days of safety stock (item → order → module config) |
| **Buffer days** | AD | Days of buffer stock (same precedence) |
| **Days left to order** | AM | Stock days − safety days − buffer days |

**Days left to order (AM)** is the key urgency signal:

- **Positive** — stock is above the reorder band; less urgent.
- **Zero or negative** — at or below safety+buffer; order soon.

You can verify M, N, and AM on **Monitor Stock** before ordering.

---

## Method 1 — By period (days)

**UI:** “By period (days)”  
**Excel:** Ordering by period/days (columns AF, BA6)

### What you enter

**Period of order (days)** — how many days of cover you want this order to add on top of what you already have, after safety and buffer.

### Formula (per item)

```
coverage = period_days + safety_days + buffer_days − stock_days_N
order_qty = max(0, coverage × graduated_MA)
```

Where:

- **stock_days_N** = current stock ÷ daily usage (column N).
- **graduated_MA** = consumption rate chosen from how many days of stock you already have:

| If stock days (N) is… | Use moving average |
|----------------------|-------------------|
| &lt; 15 | 15-day MA |
| &lt; 30 | 30-day MA |
| &lt; 90 | 90-day MA |
| &lt; 180 | 180-day MA |
| otherwise | 360-day MA |

### Plain-language example

- Period = **30 days**, safety = **10**, buffer = **5** → target band = **45 stock-days** of cover.
- Item has **5 stock-days** on hand (N = 5).
- Coverage gap = 45 − 5 = **40 days** of stock to order.
- Daily usage = **10 SUOM** → order qty ≈ **400 SUOM**.

If the item already has enough stock-days (N ≥ period + safety + buffer), **order qty = 0** and the line is skipped.

### When to use

- You think in **“order for the next X days”**.
- You want a straight cover calculation from **current stock** and **period**, without splitting a budget across items.

---

## Method 2 — By budget (UGX)

**UI:** “By budget”

Use when you already ran a **period order**, copied the **order total**, and want the **same line quantities** under that money cap.

### What you enter

**Budget (UGX)** — the order total from your period order (e.g. **44,675**).

The system finds the period (days) that best fits that budget, calculates quantities with the same period formula (AF) as Method 1, then scales so the **full budget is used**.

---

## Method comparison

| | **By period (days)** | **By budget (UGX)** |
|--|----------------------|---------------------|
| **Question it answers** | “Cover the next X days for each item.” | “Same as period, capped at UGX X.” |
| **You enter** | Period (days) | Budget (UGX) |
| **Qty matches period order?** | — | **Yes**, when budget = period total and settings match |
| **Best when** | First pass — see what you need | Re-order from a period total |

---

## Optional: peak period uplift

Both methods can apply a peak uplift **after** the base qty:

```
peak_impact % = peak_period % × consumption_increase % ÷ 100
final_qty = base_qty × (1 + peak_impact / 100)
```

---

## What you see on each order line

| Field | Used in |
|-------|---------|
| Current stock (M) | Both |
| Stock days (N) | Both |
| **Days left to order (AM)** | Both; **drives budget allocation** |
| Order days (AJ) | Budget only |
| Order qty | Final suggested quantity |

After changing logic or stock data, open a draft order and click **Refresh items**.

---

## Quick troubleshooting

| Problem | Check |
|---------|--------|
| No lines | Consumption or stock at store; Monitor Stock |
| All zeros (period) | N already ≥ period + safety + buffer |
| Budget looks flat/wrong | Days left on Monitor Stock; refresh draft order |

**Tests:** `./vendor/bin/phpunit tests/Unit/Inventory/InventoryStockAnalyticsServiceTest.php`
