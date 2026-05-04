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
