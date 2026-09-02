<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->text('finance_notification_emails')->nullable()->after('financial_year_start_month');
            $table->boolean('lpo_email_copy_to_approvers')->default(true)->after('finance_notification_emails');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn(['finance_notification_emails', 'lpo_email_copy_to_approvers']);
        });
    }
};
