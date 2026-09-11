<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->string('technical_representative_name')->nullable()->after('delivery_note_original_name');
            $table->string('technical_representative_signature_path')->nullable()->after('technical_representative_name');
            $table->string('technical_representative_signature_original_name')->nullable()->after('technical_representative_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropColumn([
                'technical_representative_name',
                'technical_representative_signature_path',
                'technical_representative_signature_original_name',
            ]);
        });
    }
};
