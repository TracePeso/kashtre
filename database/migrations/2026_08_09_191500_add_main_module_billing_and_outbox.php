<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_usage_events', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('billed_main_module')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->string('main_billing_status', 32)->nullable()->after('invoice_id')->index();
            $table->text('main_billing_error')->nullable()->after('main_billing_status');
            $table->timestamp('main_billed_at')->nullable()->after('main_billing_error');
        });

        Schema::create('inventory_main_module_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_id')->unique();
            $table->string('event_type', 64)->index();
            $table->string('aggregate_type', 64)->nullable();
            $table->unsignedBigInteger('aggregate_id')->nullable()->index();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_main_module_outbox');

        Schema::table('inventory_usage_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn(['main_billing_status', 'main_billing_error', 'main_billed_at']);
        });
    }
};
