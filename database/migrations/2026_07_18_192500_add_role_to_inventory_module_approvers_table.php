<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_approvers', function (Blueprint $table) {
            $table->string('role', 40)->default('approver')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_approvers', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
