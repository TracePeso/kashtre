<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maturation delays for service fee / service charge settlements per business entity and payment method.
     */
    public function up(): void
    {
        Schema::create('service_charge_maturation_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->enum('payment_method', ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash']);
            $table->unsignedSmallInteger('maturation_days');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['business_id', 'payment_method'], 'sc_mat_period_business_pay_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_charge_maturation_periods');
    }
};
