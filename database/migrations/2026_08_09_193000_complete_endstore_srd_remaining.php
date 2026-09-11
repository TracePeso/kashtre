<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->string('location_layer_3')->nullable()->after('item_id');
            $table->string('location_layer_2')->nullable()->after('location_layer_3');
            $table->string('location_layer_1')->nullable()->after('location_layer_2');
            $table->string('stock_zone', 32)->nullable()->default('active')->after('location_layer_1')->index();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->json('location_layer_labels')->nullable()->after('description');
            $table->decimal('reorder_level_days', 8, 2)->nullable()->after('location_layer_labels');
            $table->decimal('max_stock_days', 8, 2)->nullable()->after('reorder_level_days');
        });

        Schema::table('inventory_handoff_tokens', function (Blueprint $table) {
            $table->string('tote_barcode')->nullable()->after('basket_key');
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->json('label_dictionary')->nullable()->after('enable_serial_number_tracking');
            $table->unsignedInteger('visit_reactivation_lookback_days')->default(30)->after('label_dictionary');
        });

        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->json('dispense_serials')->nullable()->after('metadata');
            $table->string('dispense_batch_lot')->nullable()->after('dispense_serials');
        });

        Schema::create('inventory_demand_ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('source', 64)->default('invoice');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'item_id', 'occurred_at']);
        });

        Schema::create('inventory_forensic_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context', 64);
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->decimal('old_qty', 14, 4)->nullable();
            $table->decimal('new_qty', 14, 4)->nullable();
            $table->json('meta')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('row_hash', 64);
            $table->timestamp('committed_at');
            $table->timestamps();

            $table->index(['business_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_forensic_audit_logs');
        Schema::dropIfExists('inventory_demand_ledgers');

        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->dropColumn(['dispense_serials', 'dispense_batch_lot']);
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn(['label_dictionary', 'visit_reactivation_lookback_days']);
        });

        Schema::table('inventory_handoff_tokens', function (Blueprint $table) {
            $table->dropColumn('tote_barcode');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['location_layer_labels', 'reorder_level_days', 'max_stock_days']);
        });

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropColumn(['location_layer_3', 'location_layer_2', 'location_layer_1', 'stock_zone']);
        });
    }
};
