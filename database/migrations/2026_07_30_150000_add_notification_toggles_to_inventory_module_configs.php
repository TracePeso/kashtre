<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->boolean('notify_approvers_on_order_submitted')->default(true)->after('lpo_email_copy_to_approvers');
            $table->boolean('notify_finance_on_order_submitted')->default(true)->after('notify_approvers_on_order_submitted');
            $table->boolean('notify_next_approver_on_approval')->default(true)->after('notify_finance_on_order_submitted');
            $table->boolean('notify_on_order_fully_approved')->default(true)->after('notify_next_approver_on_approval');
            $table->boolean('notify_suppliers_on_rfq_approved')->default(true)->after('notify_on_order_fully_approved');
            $table->boolean('notify_on_lpo_issued')->default(true)->after('notify_suppliers_on_rfq_approved');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn([
                'notify_approvers_on_order_submitted',
                'notify_finance_on_order_submitted',
                'notify_next_approver_on_approval',
                'notify_on_order_fully_approved',
                'notify_suppliers_on_rfq_approved',
                'notify_on_lpo_issued',
            ]);
        });
    }
};
