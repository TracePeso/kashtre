<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'client_space_id')) {
                $table->foreignId('client_space_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('client_spaces')
                    ->nullOnDelete();
            }
        });

        Schema::create('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('client_space_id')->nullable()->constrained('client_spaces')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('visit_id', 64)->nullable()->index();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('service_delivery_queue_id')->nullable()->constrained('service_delivery_queues')->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 12, 2);
            $table->decimal('quantity_fulfilled', 12, 2)->default(0);
            $table->string('fulfillment_strategy', 32)->default('DISCRETE_IMMEDIATE');
            $table->string('priority', 16)->default('normal'); // normal|urgent|stat
            $table->string('status', 32)->default('pending'); // pending|picking|staged|completed|cancelled|partial
            $table->string('basket_key')->nullable()->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'store_id', 'status']);
            $table->index(['business_id', 'priority', 'status']);
            $table->index(['invoice_id', 'item_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_fulfillment_lines');

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'client_space_id')) {
                $table->dropConstrainedForeignId('client_space_id');
            }
        });
    }
};
