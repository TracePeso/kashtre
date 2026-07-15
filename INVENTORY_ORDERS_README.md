# Inventory orders — how quantities are calculated

Reference: `public/KashTre_new.xlsx` → **Inventory** sheet.  
Code: `InventoryStockAnalyticsService`, `InventoryOrderService`.

---

## Shared inputs (from stock — not typed on the form)

| Excel | Meaning |
|-------|---------|
| **M** | Current stock |
| **V / AA** | Daily usage (15-day MA, or fixed daily if V = 0) |
| **N** | Stock days = M ÷ (V or AA) |
| **AB / AD** | Safety / buffer days |
| **AM** | Days left to order = N − AB − AD |
| **F/J** | Purchase price per SUOM |

---

## Method 1 — By period (days)

**You enter:** period of order (**BA6** days) → system shows order **amount** (Σ AG)

```
AF = max(0, (BA6 + AB + AD − N) × graduated_MA(period))
AG = AF × (F/J)
```

---

## Method 2 — By budget (UGX → AH → AL)

**You enter:** budget **amount** (UGX). Excel **BA7** is UGX (“Order by Budget (UGX)”).

| Col | Excel meaning | Formula |
|-----|---------------|---------|
| **AH** | Test Amount | `15 × (V or AA if V = 0) × (F/J)` |
| **AI** | Gap to Average Days left to Order | `AM − (Σ AM ÷ count AM)` |
| **AJ** | Order Days | `(15 × BA7 ÷ Σ AH) − AI` |
| **AK** | Order Qty | `AJ × (V or AA if V = 0)` |
| **AL** | Order amount | `AK × (F/J)` |

If AJ would be negative, order qty is 0.  
Items with **stock days > 366** stay on the order when you selected them (qty 0); they are left out of AVERAGE(AM) / Σ AH so they do not skew urgent lines.  
If Σ AL still exceeds the entered UGX, quantities are scaled down to the cap.

---

## Peak uplift (both methods, after AF or AK)

```
peak_impact % = peak_period % × consumption_increase % ÷ 100
final_qty = base_qty × (1 + peak_impact / 100)
```

---

## Proof in the app

Open **View calculation** on an order.  
Help: `/inventory/orders/how-it-works`.

**Tests:** `./vendor/bin/phpunit tests/Unit/Inventory/InventoryStockAnalyticsServiceTest.php`

---

## Procurement workflow (external)

1. **Purchase request** — draft RFQ with items/budget; review quantities (**no supplier yet**).
2. **Digital approval** — configured approvers (whoever was selected; 1–2); Finance notified.
3. **RFQ** — price-hidden PDF; invite and email suppliers (supplier email required).
4. **Quotation analysis** — compare quotes; accept one or more suppliers.
5. **LPO issuance** — one LPO per accepted quotation; number uses business **entity code** when set (`{CODE}-LPO-YYYYMMDD-NNN`).
6. **Transmit** — issue LPO (emails finance/approvers + supplier).
7. **Delivery & QC** — GRN → **inspection must Pass** → final GRN approve posts stock.

## Procurement workflow (internal)

1. Internal order (source → receiving store) → submit → approve.
2. On full approval a **draft stock transfer** is prepared (manual create still available if needed).
3. Transfer approve = **issued in transit** (available ↓, in-transit ↑). Shown on Monitor Stock.
4. Destination confirm receive clears in-transit and credits destination stock.
5. Linked order becomes **partially fulfilled** or **fulfilled**; remaining quantities can start another transfer.

Set **entity code** on Businesses → Entity code action. Add supplier emails under Suppliers.
