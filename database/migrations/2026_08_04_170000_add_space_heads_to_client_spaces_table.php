<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_spaces', function (Blueprint $table) {
            $table->foreignId('space_head_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('deputy_space_head_id')
                ->nullable()
                ->after('space_head_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('alternate_space_head_id')
                ->nullable()
                ->after('deputy_space_head_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_spaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('space_head_id');
            $table->dropConstrainedForeignId('deputy_space_head_id');
            $table->dropConstrainedForeignId('alternate_space_head_id');
        });
    }
};
