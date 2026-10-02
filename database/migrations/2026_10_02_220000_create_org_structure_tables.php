<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_id', 64);
            $table->string('name');
            $table->string('org_unit_type')->nullable();
            $table->string('head_name')->nullable();
            $table->string('head_assignment_external_id', 64)->nullable();
            $table->unsignedInteger('org_level')->default(1);
            $table->boolean('is_terminal')->default(false);
            $table->text('org_path')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'external_id']);
            $table->index('parent_id');
            $table->foreign('parent_id')->references('id')->on('org_units')->nullOnDelete();
        });

        Schema::create('org_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('terminal_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_space_id')->nullable()->constrained('client_spaces')->nullOnDelete();
            $table->foreignId('title_id')->nullable()->constrained('titles')->nullOnDelete();
            $table->string('external_id', 64);
            $table->string('position_title')->nullable();
            $table->string('assignment_type')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('hierarchy_level')->default(1);
            $table->boolean('roster_eligible')->default(false);
            $table->string('client_space_label')->nullable();
            $table->text('scope_note')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'external_id']);
            $table->index(['business_id', 'user_id']);
            $table->index(['business_id', 'is_primary']);
        });

        Schema::table('org_units', function (Blueprint $table) {
            $table->foreignId('head_assignment_id')
                ->nullable()
                ->after('head_user_id')
                ->constrained('org_assignments')
                ->nullOnDelete();
        });

        Schema::create('reporting_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained('org_assignments')->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('line_manager_assignment_id')->nullable();
            $table->unsignedBigInteger('approval_parent_assignment_id')->nullable();
            $table->unsignedBigInteger('approval_terminal_assignment_id')->nullable();
            $table->unsignedInteger('hierarchy_level')->default(1);
            $table->string('approval_route')->nullable();
            $table->text('approval_path_names')->nullable();
            $table->json('approval_path_external_ids')->nullable();
            $table->unsignedInteger('approval_depth')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('subject_assignment_id');
            $table->index(['business_id', 'subject_user_id']);
            $table->foreign('line_manager_assignment_id', 'rr_line_manager_fk')
                ->references('id')->on('org_assignments')->nullOnDelete();
            $table->foreign('approval_parent_assignment_id', 'rr_approval_parent_fk')
                ->references('id')->on('org_assignments')->nullOnDelete();
            $table->foreign('approval_terminal_assignment_id', 'rr_approval_terminal_fk')
                ->references('id')->on('org_assignments')->nullOnDelete();
        });

        Schema::create('staff_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_assignment_id')->nullable()->constrained('org_assignments')->nullOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_space_id')->nullable()->constrained('client_spaces')->nullOnDelete();
            $table->string('external_id', 64);
            $table->string('client_space_external_id', 64)->nullable();
            $table->string('position_title')->nullable();
            $table->decimal('allocation_percent', 5, 2)->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'external_id']);
            $table->index(['business_id', 'user_id']);
            $table->index(['business_id', 'client_space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deployments');
        Schema::dropIfExists('reporting_relationships');

        Schema::table('org_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_assignment_id');
        });

        Schema::dropIfExists('org_assignments');
        Schema::dropIfExists('org_units');
    }
};
