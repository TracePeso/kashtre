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

## Method 2 — By budget

**UI:** “By budget”  
**Excel:** Ordering by budget (columns AH–AK, BA7)

Budget ordering does **not** ignore days left. It **allocates** a stock-days budget across items using **days left to order (AM)** so urgent lines get more and over-stocked lines get less.

### Step 1 — Build inputs for each item

| Excel | Meaning |
|-------|---------|
| **AM** | Days left to order |
| **AH** | Test amount = 15 × daily usage × unit price |
| **Average AM** | Mean of days left across all eligible items |

Only items with daily usage &gt; 0, valid days left, and test amount &gt; 0 are included.

### Step 2 — Allocate order days (the professional part)

For each item:

```
AI = days_left − average(days_left)          ← gap vs other items
AJ = (15 × stock_days_budget ÷ Σ test amounts) − AI
AK = AJ × daily usage                         ← order qty
```

**How days left changes the result:**

| Item situation | AI | Effect on AJ / qty |
|----------------|-----|-------------------|
| Fewer days left than average (urgent) | Negative | **More** order days |
| More days left than average | Positive | **Fewer** order days |
| At the average | Zero | Baseline share |

So the budget is spread **professionally**: items running out sooner get a larger slice.

### Step 3 — Set the stock-days pool

**UI:** **By budget** → enter **Stock-days budget** (Excel **BA7**).

Quantities come directly from the AK formula above. There is no money cap on this path.

### Plain-language example (stock-days budget = 60)

Three items, average days left = **10**:

| Item | Days left (AM) | AI (gap) | Tends to get… |
|------|----------------|----------|----------------|
| A | 2 | −8 | **Most** order days (urgent) |
| B | 10 | 0 | Average share |
| C | 18 | +8 | **Least** order days (well stocked) |

### When to use

- You have a **fixed stock-days pool** or **fixed money** and want it **fairly split** using **days left to order**.
- Different items are at different risk of stock-out and should not all get the same period cover.

---

## Method comparison

| | **By period (days)** | **By budget** |
|--|----------------------|---------------|
| **Question it answers** | “Cover the next X days for each item.” | “Split my budget by urgency (days left).” |
| **Uses days left (AM)?** | Indirectly (via stock days N) | **Yes — core to allocation** |
| **Excel columns** | AF, BA6 | AH, AI, AJ, AK, BA7 |
| **Money cap** | No | No |
| **Best when** | Simple period-based replenishment | Portfolio balancing across many items |

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
