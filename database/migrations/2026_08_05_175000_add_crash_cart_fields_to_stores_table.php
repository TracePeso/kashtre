<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'is_crash_cart')) {
                $table->boolean('is_crash_cart')->default(false)->after('distribution_type');
            }
            if (! Schema::hasColumn('stores', 'crash_cart_status')) {
                $table->string('crash_cart_status', 32)->nullable()->after('is_crash_cart');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('stores', 'crash_cart_status')) {
                $cols[] = 'crash_cart_status';
            }
            if (Schema::hasColumn('stores', 'is_crash_cart')) {
                $cols[] = 'is_crash_cart';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
