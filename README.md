# Testing environment reset (Kashtre)

Use only on databases you are allowed to wipe (local / demo).

## Run all at once (recommended)

Same sequence as the super-admin dashboard **Reset testing environment** button:

```bash
php artisan service-queues:reset --all --force && \
php artisan suspense:clear --force && \
php artisan reset:account-statements --confirm
```

### What this does

| Command | Queues | Account statements |
|---------|--------|-------------------|
| `service-queues:reset --all --force` | Cancels `pending` / `in_progress` / `partially_done` rows (does not delete them) | No |
| `suspense:clear --force` | No | No (clears suspense account balances / related data) |
| `reset:account-statements --confirm` | Resets queue finalization flags (`is_finalized`, etc.) | **Yes** — truncates client, business, contractor, and third-party payer statement tables; clears AR, money transfers; zeros client / payer / money account balances |

## Optional: delete queue rows and/or full order wipe

```bash
# Delete all queue rows only (invoices & statements unchanged)
php artisan testing:clear-order-data --confirm

# Full wipe: invoices, sales, all statement tables, queues, money_transfers, etc.
php artisan testing:clear-order-data --confirm --full

# Full wipe + zero client / money account / TPP / business balances
php artisan testing:clear-order-data --confirm --full --reset-balances
```

## Third-party (run separately in the third-party app)

```bash
cd /path/to/third-party

# Light: visit verification only
php artisan testing:clear-order-data --confirm

# Full: authorizations, visits, pre-auths, rejected items, etc.
php artisan testing:clear-order-data --confirm --full
```

Typical full local retest (Kashtre + third-party):

```bash
# Kashtre
php artisan service-queues:reset --all --force && \
php artisan suspense:clear --force && \
php artisan reset:account-statements --confirm && \
php artisan testing:clear-order-data --confirm --full --reset-balances

# Third-party
cd /path/to/third-party && php artisan testing:clear-order-data --confirm --full
```

---

# Payment Simulation Command

## Simulate Successful Payment Processing

Process pending transactions and complete the payment flow:

```bash
php artisan payments:simulate-success --limit=1
php artisan payments:simulate-success --limit=1
php artisan payments:simulate-success --transaction=123
```

### What This Command Does:
1. **Finds pending transactions** - Looks for all transactions with status "pending"
2. **Marks as completed** - Changes transaction status from "pending" to "completed"
3. **Creates money movements** - Moves funds to insurance company accounts
4. **Updates invoice status** - Changes invoice from "confirmed" to "paid"
5. **Queues items** - Adds items to service_delivery_queues for pharmacy/admission

### Options:
- `--limit=1` - Process only 1 transaction
- `--limit=10` - Process up to 10 transactions
- No limit flag - Process all pending transactions

### Example Usage:

```bash
# Process 1 transaction
php artisan payments:simulate-success --limit=1

# Process 10 transactions
php artisan payments:simulate-success --limit=10

# Process all pending transactions
php artisan payments:simulate-success
```

### Expected Output:
```
✅ Simulated successful payment for transaction 53 (Amount: 500 UGX)
🎉 Simulation completed! Processed 1 transactions as successful payments.
```

---

#ALTER TABLE `bot_configurations` CHANGE `connection_status` `connection_status` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected';


ALTER TABLE `bot_configurations` ADD `image` VARCHAR(200) NULL DEFAULT NULL AFTER `login`;


php artisan items:backfill-purchase-prices --overwrite