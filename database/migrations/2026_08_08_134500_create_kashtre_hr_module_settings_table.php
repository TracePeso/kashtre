<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kashtre_hr_module_settings')) {
            return;
        }

        Schema::create('kashtre_hr_module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('url')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kashtre_hr_module_settings');
    }
};
