<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearOrderDataCommand extends Command
{
    /**
     * Tables that store order / invoice / visit-spine data only.
     * Children are listed before parents. Master data (items, clients, questions, payers) is not dropped.
     */
    protected $signature = 'testing:clear-order-data
        {--confirm : Required; prevents accidental runs}
        {--keep-balances : Do not zero client, money account, TPP, or business ledger balances}';

    protected $description = 'Delete only POS order / invoice–related rows (queues, ledgers, invoices). Does not touch master catalog, clients as records, or users.';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing to run without --confirm.');

            return 1;
        }

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
            'money_transfers',
            'quotations',
            'invoices',
        ];

        $this->warn('This will delete all rows in order/invoice chain tables:');
        $this->line('  '.implode(', ', $tables));

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

        if (! $this->option('keep-balances')) {
            $this->resetMoneyState();
        } else {
            $this->warn('Skipped balance reset; totals may not match an empty ledger.');
        }

        $this->info('Done.');

        return 0;
    }

    private function resetMoneyState(): void
    {
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
