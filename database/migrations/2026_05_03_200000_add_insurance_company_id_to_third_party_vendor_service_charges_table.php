<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_party_vendor_service_charges', function (Blueprint $table) {
            $table->foreignId('insurance_company_id')
                ->nullable()
                ->after('business_id')
                ->constrained('insurance_companies')
                ->cascadeOnDelete();

            $table->index(['business_id', 'insurance_company_id'], 'tp_vendor_chrgs_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('third_party_vendor_service_charges', function (Blueprint $table) {
            $table->dropIndex('tp_vendor_chrgs_scope_idx');
            $table->dropForeign(['insurance_company_id']);
        });
    }
};
