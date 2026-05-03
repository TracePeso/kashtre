<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('visit_authorization_sessions')->nullable()->after('visit_expires_at')
                ->comment('Per remote insurer: session_code and expiry returned from third-party authorized-visits/register');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('visit_authorization_sessions');
        });
    }
};
