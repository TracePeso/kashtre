<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_staff_unavailabilities')) {
            return;
        }

        Schema::create('hr_staff_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained('hr_leave_types')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('hr_approval_requests')->nullOnDelete();
            $table->string('reason_type', 40)->default('leave');
            $table->string('title')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('approved');
            $table->boolean('blocks_rosters')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'staff_assignment_id', 'starts_on'], 'hr_staff_unavailability_staff_date_index');
            $table->index(['organization_id', 'status', 'blocks_rosters'], 'hr_staff_unavailability_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_unavailabilities');
    }
};
