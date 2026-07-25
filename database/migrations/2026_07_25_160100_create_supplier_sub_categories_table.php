<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_industry_id')->constrained('supplier_industries')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['supplier_industry_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_sub_categories');
    }
};
