<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_routing_events', function (Blueprint $table) {
            $table->dropForeign(['to_unit_id']);
            $table->foreignId('to_unit_id')
                ->nullable()
                ->change()
                ->constrained('hr_organizational_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_routing_events', function (Blueprint $table) {
            $table->dropForeign(['to_unit_id']);
            $table->foreignId('to_unit_id')
                ->nullable(false)
                ->change()
                ->constrained('hr_organizational_units')
                ->cascadeOnDelete();
        });
    }
};
