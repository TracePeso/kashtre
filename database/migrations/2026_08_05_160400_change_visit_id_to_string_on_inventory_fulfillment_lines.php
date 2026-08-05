<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->string('visit_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->change();
        });
    }
};
