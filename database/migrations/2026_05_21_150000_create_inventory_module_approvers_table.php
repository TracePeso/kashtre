<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_module_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_module_config_id')
                ->constrained('inventory_module_configs')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('approval_order');
            $table->timestamps();

            $table->unique(['inventory_module_config_id', 'approval_order'], 'inv_mod_cfg_approver_order_unique');
            $table->unique(['inventory_module_config_id', 'user_id'], 'inv_mod_cfg_approver_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_module_approvers');
    }
};
