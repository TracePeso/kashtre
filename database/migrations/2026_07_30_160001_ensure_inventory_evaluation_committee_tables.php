<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_evaluation_committee_members')) {
            Schema::create('inventory_evaluation_committee_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_module_config_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 32)->default('member');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['inventory_module_config_id', 'user_id'], 'inv_eval_committee_config_user_unique');
                $table->foreign('inventory_module_config_id', 'inv_eval_committee_config_fk')
                    ->references('id')->on('inventory_module_configs')->cascadeOnDelete();
                $table->foreign('user_id', 'inv_eval_committee_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('inventory_order_committee_members')) {
            Schema::create('inventory_order_committee_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_order_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 32)->default('member');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedBigInteger('appointed_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['inventory_order_id', 'user_id'], 'inv_order_committee_order_user_unique');
                $table->foreign('inventory_order_id', 'inv_order_committee_order_fk')
                    ->references('id')->on('inventory_orders')->cascadeOnDelete();
                $table->foreign('user_id', 'inv_order_committee_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('appointed_by_user_id', 'inv_order_committee_appointed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_order_committee_members');
        Schema::dropIfExists('inventory_evaluation_committee_members');
    }
};
