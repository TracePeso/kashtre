<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_client_space_staff_assignments')) {
            return;
        }

        Schema::create('hr_client_space_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_space_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->string('staff_uuid')->nullable();
            $table->enum('assignment_type', ['primary', 'secondary'])->default('secondary');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'client_space_unit_id', 'staff_assignment_id'],
                'hr_client_space_staff_unique'
            );
            $table->index(['organization_id', 'client_space_unit_id', 'assignment_type', 'status'], 'hr_client_space_staff_pool_index');
            $table->index(['organization_id', 'staff_uuid', 'status'], 'hr_client_space_staff_uuid_index');
        });

        $this->backfillPrimaryClientSpaceAssignments();
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_client_space_staff_assignments');
    }

    private function backfillPrimaryClientSpaceAssignments(): void
    {
        if (
            ! Schema::hasTable('hr_staff_assignments')
            || ! Schema::hasTable('hr_organizational_units')
            || ! Schema::hasColumn('hr_organizational_units', 'unit_kind')
        ) {
            return;
        }

        $now = now();

        DB::table('hr_staff_assignments')
            ->join('hr_organizational_units', 'hr_organizational_units.id', '=', 'hr_staff_assignments.organizational_unit_id')
            ->where('hr_organizational_units.unit_kind', 'client_space')
            ->where('hr_staff_assignments.status', 'active')
            ->select([
                'hr_staff_assignments.id',
                'hr_staff_assignments.organization_id',
                'hr_staff_assignments.organizational_unit_id',
                'hr_staff_assignments.staff_uuid',
            ])
            ->orderBy('hr_staff_assignments.id')
            ->chunkById(200, function ($assignments) use ($now): void {
                foreach ($assignments as $assignment) {
                    DB::table('hr_client_space_staff_assignments')->updateOrInsert(
                        [
                            'organization_id' => $assignment->organization_id,
                            'client_space_unit_id' => $assignment->organizational_unit_id,
                            'staff_assignment_id' => $assignment->id,
                        ],
                        [
                            'uuid' => (string) Str::uuid(),
                            'staff_uuid' => $assignment->staff_uuid,
                            'assignment_type' => 'primary',
                            'status' => 'active',
                            'assigned_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }, 'hr_staff_assignments.id', 'id');
    }
};
