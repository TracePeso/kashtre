<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_spaces')) {
            return;
        }

        if (! Schema::hasColumn('client_spaces', 'is_default')) {
            Schema::table('client_spaces', function (Blueprint $table) {
                $table->boolean('is_default')
                    ->default(false)
                    ->after('alternate_space_head_id');
                $table->index(['business_id', 'is_default']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_spaces') && Schema::hasColumn('client_spaces', 'is_default')) {
            Schema::table('client_spaces', function (Blueprint $table) {
                $table->dropIndex(['business_id', 'is_default']);
                $table->dropColumn('is_default');
            });
        }
    }
};
