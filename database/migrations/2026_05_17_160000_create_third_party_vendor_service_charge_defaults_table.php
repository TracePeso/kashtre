<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_vendor_service_charge_defaults', function (Blueprint $table) {
            $table->id();
            $table->decimal('lower_bound', 15, 2)->default(0);
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('type', 20)->default('percentage');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $configTiers = config('third_party_vendor_service_charges.default_tiers', []);
        $now = now();
        foreach ($configTiers as $index => $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $upper = $tier['upper_bound'] ?? null;
            DB::table('third_party_vendor_service_charge_defaults')->insert([
                'lower_bound' => (float) ($tier['lower_bound'] ?? 0),
                'upper_bound' => ($upper === null || $upper === '') ? null : (float) $upper,
                'amount' => (float) ($tier['amount'] ?? 0),
                'type' => (string) ($tier['type'] ?? 'percentage'),
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_vendor_service_charge_defaults');
    }
};
