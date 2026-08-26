# Endstore SRD V6.0 — smoke checklist

## Clinical core
- [ ] Paid goods → End Store queue (POS strategy + End Store / store default)
- [ ] OP Dispense (stock ↓, Approved Pool ↑ when enabled, SDQ completed) — batch/serial when toggles on (serial count = qty)
- [ ] Goods on service point: **no** In Progress; **no** Completed when inventory module on (EndStore only); partial dispense keeps Main ticket **Pending** until full Completed
- [ ] IP Stage (tote barcode **required**) → Clinical tote alert → nurse code → Release validate
- [ ] Ward pick route (sum SKUs across End Store inpatient reservoir; optional `?visit_id=`)
- [ ] Partial flag on release
- [ ] Record Usage pool / floor / admin purpose / crash (+ Main invoice for billable)

## Phase 1
- [ ] Monitor Stock → Location edit (Wall/Cabinet/Bin or Aisle/Rack/Pallet)
- [ ] End Store → Pick route (basket) + Ward pick
- [ ] STAT tone every ~60s until Ack; keywords configurable in Capabilities
- [ ] Crash Carts: Satellite under End Store with role Crash cart → Deploy → Reconcile → Record usage → Seal Ready → IR restock
- [ ] Settings → Capabilities: labels, lookback, admin purposes, STAT keywords
- [ ] Manage Stores → End Store: default OP/IP strategy + Approved Pool toggle
- [ ] Manage Stores → reorder level (days) + max stock (days) on End / Distribution / Satellite nodes

## Phase 2–4
- [ ] Demand ledger on invoice create + payment ingest
- [ ] Internal replenishment draft (consumption/demand) + Days Mode on order lines
- [ ] Replenishment only drafts items at/below store reorder_level_days; targets max_stock_days
- [ ] PO / IR `forecast_basis` CONSUMPTION|DEMAND
- [ ] New visit_id reattaches open fulfillment lines
- [ ] `php artisan inventory:process-main-outbox` / scheduler every minute
- [ ] Expired wastage → escrow; write-off at `/inventory/escrow`
- [ ] Classification report `/inventory/reports/classification` (Store × Branch/Department × Client × Item × Date)
- [ ] Capability gates: internal ordering, stock counts, multi-store network
- [ ] IP Stage → Release with batch/lot + serials when org toggles on
- [ ] Goods cannot be marked In Progress or Completed on service-point dashboard (inventory on)
- [ ] Demand forecast MA is store-scoped (strict; legacy rows backfilled)
- [ ] Forensic audit on transfers, GRN, stock counts, goods returns
- [ ] Order lines: Qty Mode updates order days (and Days Mode updates qty)
- [ ] Label dictionary includes Client Space terminology key
