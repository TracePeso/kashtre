<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_routing_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_unit_id')->nullable()->constrained('hr_organizational_units')->nullOnDelete();
            $table->foreignId('to_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
            $table->foreignId('routed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('routed_by_staff_uuid')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('routed_at');
            $table->timestamps();

            $table->index(['staff_assignment_id', 'routed_at'], 'hr_routing_events_assignment_time_index');
            $table->index(['organization_id', 'to_unit_id'], 'hr_routing_events_org_to_unit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_routing_events');
    }
};
