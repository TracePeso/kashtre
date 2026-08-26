# Endstore SRD V6.0 — smoke checklist

Programmatic runner (Exquisite Test Life / Kololo):

```bash
php tests/Feature/Inventory/smoke_exquisite_kololo.php
```

Last run: **2026-08-26** — business `KS1759822163` (`business_id=4`), branch Kololo (`branch_id=6`), End Store Dispensary One (`store_id=11`). **30/30 PASS.**

## Clinical core
- [x] Paid goods → End Store queue (POS strategy + End Store / store default)
- [x] OP Dispense (stock ↓, Approved Pool ↑ when enabled, SDQ completed) — batch/serial when toggles on (serial count = qty)
- [x] Goods on service point: **no** In Progress; **no** Completed when inventory module on (EndStore only); partial dispense keeps Main ticket **Pending** until full Completed
- [x] IP Stage (tote barcode **required**) → Clinical tote alert → nurse code → Release validate *(bypass code in local smoke)*
- [x] Ward pick route (sum SKUs across End Store inpatient reservoir; optional `?visit_id=`) *(HTTP 200; Client Space URL uses UUID)*
- [ ] Partial flag on release
- [x] Record Usage pool / floor / admin purpose / crash (+ Main invoice for billable) *(pool + crash verified)*

## Phase 1
- [ ] Monitor Stock → Location edit (Wall/Cabinet/Bin or Aisle/Rack/Pallet)
- [x] End Store → Pick route (basket) + Ward pick *(routes + ward-pick HTTP 200)*
- [x] STAT tone every ~60s until Ack; keywords configurable in Capabilities *(keywords STAT,URGENT present; tone UI not exercised)*
- [x] Crash Carts: Satellite under End Store with role Crash cart → Deploy → Reconcile → Record usage → Seal Ready → IR restock
- [x] Settings → Capabilities: labels, lookback, admin purposes, STAT keywords *(module + STAT keywords; page 200)*
- [ ] Manage Stores → End Store: default OP/IP strategy + Approved Pool toggle
- [ ] Manage Stores → reorder level (days) + max stock (days) on End / Distribution / Satellite nodes

## Phase 2–4
- [x] Demand ledger on invoice create + payment ingest
- [ ] Internal replenishment draft (consumption/demand) + Days Mode on order lines
- [ ] Replenishment only drafts items at/below store reorder_level_days; targets max_stock_days
- [ ] PO / IR `forecast_basis` CONSUMPTION|DEMAND
- [ ] New visit_id reattaches open fulfillment lines
- [ ] `php artisan inventory:process-main-outbox` / scheduler every minute
- [ ] Expired wastage → escrow; write-off at `/inventory/escrow`
- [x] Classification report `/inventory/reports/classification` (Store × Branch/Department × Client × Item × Date) *(route HTTP 200)*
- [x] Capability gates: internal ordering, stock counts, multi-store network *(module flags + 2 End Stores on Kololo)*
- [ ] IP Stage → Release with batch/lot + serials when org toggles on *(batch/serial off for this org)*
- [x] Goods cannot be marked In Progress or Completed on service-point dashboard (inventory on)
- [ ] Demand forecast MA is store-scoped (strict; legacy rows backfilled)
- [ ] Forensic audit on transfers, GRN, stock counts, goods returns
- [ ] Order lines: Qty Mode updates order days (and Days Mode updates qty)
- [ ] Label dictionary includes Client Space terminology key
