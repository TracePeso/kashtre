<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_approval_workflow_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('hr_approval_workflows')->cascadeOnDelete();
            $table->enum('approver_level', ['primary', 'secondary', 'tertiary']);
            $table->string('approver_staff_uuid');
            $table->string('approver_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'approver_level', 'approver_staff_uuid'], 'workflow_level_staff_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_approval_workflow_approvers');
    }
};
