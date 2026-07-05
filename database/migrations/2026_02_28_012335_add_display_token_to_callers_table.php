<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('callers')) {
            Schema::table('callers', function (Blueprint $table) {
                if (Schema::hasColumn('callers', 'display_token')) {
                    $table->dropIndex(['display_token']);
                    $table->string('display_token', 64)->nullable()->unique()->change();
                } else {
                    $table->string('display_token', 64)->nullable()->unique()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('callers')) {
            Schema::table('callers', function (Blueprint $table) {
                if (Schema::hasColumn('callers', 'display_token')) {
                    $table->dropUnique(['display_token']);
                    $table->string('display_token', 10)->nullable()->index()->change();
                }
            });
        }
    }
};
