# Endstore SRD V6.0 — smoke checklist

## Clinical core (already live)
- [ ] Client Space → End Store routing
- [ ] Paid goods → End Store queue
- [ ] OP Dispense (stock ↓, Approved Pool ↑, SDQ completed)
- [ ] IP Stage → Clinical tote alert → nurse code → Release validate
- [ ] Partial flag on release
- [ ] Record Usage pool / floor / crash (+ Main invoice for billable)

## Phase 1
- [ ] Monitor Stock → Location edit (Wall/Cabinet/Bin or Aisle/Rack/Pallet)
- [ ] End Store → Pick route (print)
- [ ] Stage with tote barcode
- [ ] STAT tone every ~60s until Ack
- [ ] Crash Carts page + Internal Replenishment in sidebar
- [ ] Settings → Capabilities: label dictionary + lookback days

## Phase 2–4
- [ ] Demand ledger rows created on invoice ingest (even zero stock)
- [ ] Internal replenishment draft order (consumption/demand)
- [ ] New visit_id reattaches open fulfillment lines
- [ ] `php artisan inventory:process-main-outbox` / scheduler every minute
- [ ] Expired wastage → escrow (`expired_quantity_suom`); write-off path available
- [ ] Dispense requires batch/serial when capability toggles on
- [ ] `php artisan inventory:verify-forensic-audit {business_id}`
