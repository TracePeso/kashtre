<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('staff_uuid');
            $table->string('staff_name');
            $table->string('staff_cadre')->nullable();
            $table->string('home_branch_external_id')->nullable();
            $table->string('home_branch_name')->nullable();
            $table->enum('assignment_type', ['primary', 'secondary', 'temporary'])->default('primary');
            $table->string('assigned_by')->nullable();
            $table->enum('status', ['active', 'pending_routing', 'orphaned', 'inactive'])->default('pending_routing');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('routed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'staff_uuid']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_assignments');
    }
};
