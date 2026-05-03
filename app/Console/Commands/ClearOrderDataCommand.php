<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearOrderDataCommand extends Command
{
    /**
     * Default: only clear operational/workflow tables (queues) that are not the same as
     * "completed" financial history — so you can re-test without re-importing SQL or losing invoices/ledgers.
     * Use --full for the previous all-tables behaviour.
     */
    protected $signature = 'testing:clear-order-data
        {--confirm : Required; prevents accidental runs}
        {--full : Also remove invoices, sales, package tracking, all ledgers, and order history (destructive)}
        {--reset-balances : Only with --full: zero client, money account, TPP, and business balances}';

    protected $description = 'By default, clears only service queues (in-flight work). Use --full to wipe all order/invoice data; use --reset-balances only with --full to zero account balances.';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing to run without --confirm.');

            return 1;
        }

        if ($this->option('reset-balances') && ! $this->option('full')) {
            $this->error('--reset-balances is only valid with --full (to avoid partial inconsistent state).');

            return 1;
        }

        if (! $this->option('full')) {
            $this->lightReset();

            return 0;
        }

        $this->fullReset();

        if ($this->option('reset-balances')) {
            $this->resetMoneyState();
        } else {
            $this->line('Skipped balance reset (use --reset-balances with --full if you need zeros).');
        }

        $this->info('Done (full).');

        return 0;
    }

    /**
     * Queues and other workflow-only tables. After a fully completed visit/queue flow, these are
     * the main "stuck" places to clear for a retest without deleting invoices or money records.
     */
    private function lightReset(): void
    {
        $this->warn('Light reset: only workflow/queue tables (invoices, transactions, balances, users are unchanged).');

        $queueTables = [
            'service_delivery_queues',
            'service_queues',
        ];

        DB::transaction(function () use ($queueTables) {
            foreach ($queueTables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->warn("Skip missing table: {$table}");
                    continue;
                }
                $n = DB::table($table)->count();
                DB::table($table)->delete();
                $this->info("Cleared {$table} ({$n} row(s)).");
            }
        });

        $this->info('Done (light).');
    }

    private function fullReset(): void
    {
        $this->warn('Full reset: all order/invoice chain tables will be cleared.');

        $tables = [
            'credit_note_approvals',
            'credit_notes',
            'third_party_payer_balance_histories',
            'payment_method_account_transactions',
            'balance_histories',
            'business_balance_histories',
            'contractor_balance_histories',
            'accounts_receivable',
            'transactions',
            'sales',
            'package_sales',
            'package_tracking_items',
            'package_tracking',
            'package_usages',
            'service_delivery_queues',
            'service_queues',
            'money_transfers',
            'quotations',
            'invoices',
        ];

        $this->line('Tables: '.implode(', ', $tables));

        DB::transaction(function () use ($tables) {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->warn("Skip missing table: {$table}");
                    continue;
                }
                $n = DB::table($table)->count();
                DB::table($table)->delete();
                $this->info("Cleared {$table} ({$n} rows).");
            }
        });
    }

    private function resetMoneyState(): void
    {
        $this->warn('Resetting balances…');

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'balance')) {
            $u = DB::table('clients')->where('balance', '!=', 0)->update(['balance' => 0]);
            $this->info("Reset clients.balance on {$u} row(s).");
        }

        if (Schema::hasTable('money_accounts') && Schema::hasColumn('money_accounts', 'balance')) {
            $u = DB::table('money_accounts')->where('balance', '!=', 0)->update(['balance' => 0]);
            $this->info("Reset money_accounts.balance on {$u} row(s).");
        }

        if (Schema::hasTable('third_party_payers') && Schema::hasColumn('third_party_payers', 'current_balance')) {
            $u = DB::table('third_party_payers')->where('current_balance', '!=', 0)->update(['current_balance' => 0]);
            $this->info("Reset third_party_payers.current_balance on {$u} row(s).");
        }

        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'account_balance')) {
            $u = DB::table('businesses')->where('account_balance', '!=', 0)->update(['account_balance' => 0]);
            $this->info("Reset businesses.account_balance on {$u} row(s).");
        }
    }
}
