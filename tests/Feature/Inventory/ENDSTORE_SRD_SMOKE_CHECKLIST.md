# Endstore SRD V6.0 — smoke checklist

## Clinical core
- [ ] Client Space → End Store routing
- [ ] Paid goods → End Store queue
- [ ] OP Dispense (stock ↓, Approved Pool ↑, SDQ completed) — batch/serial when toggles on
- [ ] Goods on service point: **no** In Progress (End Store dispense only)
- [ ] IP Stage (tote barcode **required**) → Clinical tote alert → nurse code → Release validate
- [ ] Ward pick route (sum SKUs across ward reservoir)
- [ ] Partial flag on release
- [ ] Record Usage pool / floor / admin purpose / crash (+ Main invoice for billable)

## Phase 1
- [ ] Monitor Stock → Location edit (Wall/Cabinet/Bin or Aisle/Rack/Pallet)
- [ ] End Store → Pick route (basket) + Ward pick
- [ ] STAT tone every ~60s until Ack; keywords configurable in Capabilities
- [ ] Crash Carts page: Deploy → Reconcile → Record usage → Seal Ready
- [ ] Settings → Capabilities: labels, lookback, admin purposes, STAT keywords

## Phase 2–4
- [ ] Demand ledger on invoice create + payment ingest
- [ ] Internal replenishment draft (consumption/demand) + Days Mode on order lines
- [ ] PO / IR `forecast_basis` CONSUMPTION|DEMAND
- [ ] New visit_id reattaches open fulfillment lines
- [ ] `php artisan inventory:process-main-outbox` / scheduler every minute
- [ ] Expired wastage → escrow; write-off at `/inventory/escrow`
- [ ] Classification report `/inventory/reports/classification`
- [ ] Capability gates: internal ordering, stock counts, multi-store network
- [ ] `php artisan inventory:verify-forensic-audit --all` (scheduled daily)
