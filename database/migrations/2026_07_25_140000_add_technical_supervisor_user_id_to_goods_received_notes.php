<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->foreignId('technical_supervisor_user_id')
                ->nullable()
                ->after('technical_representative_signature_original_name')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technical_supervisor_user_id');
        });
    }
};
