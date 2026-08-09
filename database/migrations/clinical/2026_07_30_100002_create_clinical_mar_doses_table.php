<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_mar_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_order_id')->constrained('clinical_medication_orders')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->enum('status', ['DUE', 'ADMINISTERED', 'MISSED', 'HELD'])->default('DUE');
            $table->unsignedBigInteger('administered_by_user_id')->nullable();
            $table->timestamp('administered_at')->nullable();
            $table->string('reason_code', 64)->nullable(); // clinical_reason_codes_master, when HELD/skipped
            $table->text('notes')->nullable();

            $table->index(['medication_order_id', 'scheduled_at'], 'idx_mar_dose_schedule');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_mar_doses');
    }
};
