<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('callers')) {
            return;
        }

        if (! Schema::hasColumn('callers', 'announcement_message')) {
            Schema::table('callers', function (Blueprint $table) {
                $table->text('announcement_message')->nullable()->after('display_token');
            });
        }

        if (! Schema::hasColumn('callers', 'speech_rate')) {
            Schema::table('callers', function (Blueprint $table) {
                $table->float('speech_rate')->default(1.0)->after('announcement_message');
            });
        }

        if (! Schema::hasColumn('callers', 'speech_volume')) {
            Schema::table('callers', function (Blueprint $table) {
                $table->float('speech_volume')->default(1.0)->after('speech_rate');
            });
        }
    }

    public function down(): void
    {
        // These columns are now part of the callers baseline schema.
    }
};
