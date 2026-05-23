<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_open_shift_bids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hr_open_shift_id')->constrained('hr_open_shifts')->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bid_staff_uuid');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['hr_open_shift_id', 'staff_assignment_id'], 'hr_open_shift_bid_unique');
            $table->index(['status', 'submitted_at'], 'hr_open_shift_bid_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_open_shift_bids');
    }
};
