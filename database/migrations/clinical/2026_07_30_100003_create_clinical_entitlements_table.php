<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD §6.3 clinical_entitlements — tracks prepaid package balances
        // (e.g. Antenatal Package = 9 consults). The package itself is
        // sold/managed in the Main Module; Clinical only consumes the
        // token here. No package-purchase UI is built in this chunk —
        // rows are created directly (service/test/future Main Module
        // integration), consumption is what's implemented.
        Schema::connection('clinical')->create('clinical_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('client_id')->index();
            $table->string('package_id', 64);
            $table->string('service_code', 64); // matches a medication order's drug_code or a generic service code
            $table->unsignedInteger('allocated_qty');
            $table->unsignedInteger('used_qty')->default(0);
            $table->unsignedInteger('remaining_qty')->storedAs('allocated_qty - used_qty');
            $table->timestamps();

            $table->index(['business_id', 'client_id', 'service_code'], 'idx_entitlement_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_entitlements');
    }
};
