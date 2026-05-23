<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_policy_versions')) {
            return;
        }

        Schema::create('hr_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regional_policy_id')->constrained('hr_regional_policies')->cascadeOnDelete();
            $table->string('version_label', 80);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('weekly_standard_minutes');
            $table->unsignedSmallInteger('weekly_absolute_ceiling_minutes');
            $table->unsignedSmallInteger('daily_net_cap_minutes');
            $table->unsignedSmallInteger('minimum_rest_gap_minutes');
            $table->unsignedTinyInteger('consecutive_work_days_limit')->default(5);
            $table->unsignedSmallInteger('rest_after_consecutive_days_minutes')->default(1440);
            $table->unsignedSmallInteger('anchor_window_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_trigger_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'effective_from'], 'hr_policy_versions_org_active_start_idx');
            $table->index(['regional_policy_id', 'is_active'], 'hr_policy_versions_policy_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_policy_versions');
    }
};
