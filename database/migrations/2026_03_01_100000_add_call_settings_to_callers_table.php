<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('callers')) {
            Schema::table('callers', function (Blueprint $table) {
                $table->text('announcement_message')->nullable()->after('display_token');
                $table->float('speech_rate')->default(1.0)->after('announcement_message');
                $table->float('speech_volume')->default(1.0)->after('speech_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('callers')) {
            Schema::table('callers', function (Blueprint $table) {
                $table->dropColumn(['announcement_message', 'speech_rate', 'speech_volume']);
            });
        }
    }
};
