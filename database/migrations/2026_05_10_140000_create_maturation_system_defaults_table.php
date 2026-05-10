<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maturation_system_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method', 64);
            $table->unsignedSmallInteger('entity_maturation_days');
            $table->unsignedSmallInteger('service_charge_maturation_days');
            $table->timestamps();

            $table->unique('payment_method', 'mat_sys_defaults_pay_method_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maturation_system_defaults');
    }
};
