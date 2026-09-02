<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_handoff_tokens', function (Blueprint $table) {
            $table->string('code_hash')->nullable()->change();
            $table->string('clinical_session_id')->nullable()->after('code_hash');
            $table->timestamp('clinical_notified_at')->nullable()->after('clinical_session_id');
        });

        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->foreignId('handoff_token_id')
                ->nullable()
                ->after('staged_at')
                ->constrained('inventory_handoff_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handoff_token_id');
        });

        Schema::table('inventory_handoff_tokens', function (Blueprint $table) {
            $table->dropColumn(['clinical_session_id', 'clinical_notified_at']);
            $table->string('code_hash')->nullable(false)->change();
        });
    }
};
