<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_spaces') || Schema::hasColumn('client_spaces', 'custom_business_name')) {
            return;
        }

        Schema::table('client_spaces', function (Blueprint $table) {
            $table->string('custom_business_name')->nullable()->after('name');
            $table->index('custom_business_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_spaces') || ! Schema::hasColumn('client_spaces', 'custom_business_name')) {
            return;
        }

        Schema::table('client_spaces', function (Blueprint $table) {
            $table->dropIndex(['custom_business_name']);
            $table->dropColumn('custom_business_name');
        });
    }
};
