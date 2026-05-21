#!/usr/bin/env bash
# Clears money-movement ledger rows, queues used for delivery/transfers, and zeros suspense/account mirrors.
# Safe for local/demo resets before retesting payments / insurance / maturation flows.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

php artisan tinker --execute "$(cat <<'PHP'
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$truncateTables = [
    'money_transfers',
    'business_balance_histories',
    'balance_histories',
    'contractor_balance_histories',
    'third_party_payer_balance_histories',
    'service_delivery_queues',
    'service_queues',
];

Schema::disableForeignKeyConstraints();
foreach ($truncateTables as $table) {
    if (! Schema::hasTable($table)) {
        echo "Skip missing table: {$table}\n";
        continue;
    }
    DB::table($table)->truncate();
    echo "Truncated: {$table}\n";
}
Schema::enableForeignKeyConstraints();

if (Schema::hasTable('money_accounts')) {
    DB::table('money_accounts')->update(['balance' => 0]);
    echo "Reset money_accounts.balance to 0\n";
}
if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'balance')) {
    DB::table('clients')->update(['balance' => 0]);
    echo "Reset clients.balance to 0\n";
}
if (Schema::hasTable('contractor_profiles') && Schema::hasColumn('contractor_profiles', 'account_balance')) {
    DB::table('contractor_profiles')->update(['account_balance' => 0]);
    echo "Reset contractor_profiles.account_balance to 0\n";
}
if (Schema::hasTable('third_party_payers') && Schema::hasColumn('third_party_payers', 'current_balance')) {
    DB::table('third_party_payers')->update(['current_balance' => 0]);
    echo "Reset third_party_payers.current_balance to 0\n";
}
if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'account_balance')) {
    DB::table('businesses')->update(['account_balance' => 0]);
    echo "Reset businesses.account_balance to 0\n";
}

echo "Done.\n";
PHP
)"
