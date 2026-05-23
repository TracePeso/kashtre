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
        $this->repairOrganizationalUnitsTable();
        $this->backfillUnitsFromLegacyTiers();
        $this->repairStaffAssignmentsTable();
        $this->repairDutyRostersTable();
        $this->repairDutyRosterEntriesTable();
        $this->repairApprovalRequestsTable();
    }

    public function down(): void
    {
        // This migration repairs live schema drift and preserves data from the legacy tier tables.
    }

    private function repairOrganizationalUnitsTable(): void
    {
        if (! Schema::hasTable('hr_organizational_units')) {
            Schema::create('hr_organizational_units', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('hr_organizational_units')->nullOnDelete();
                $table->string('name');
                $table->string('type');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organization_id', 'parent_id']);
            });

            return;
        }

        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_organizational_units', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('hr_organizational_units', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('hr_organizational_units', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('organization_id')->constrained('hr_organizational_units')->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_organizational_units', 'name')) {
                $table->string('name')->nullable()->after('parent_id');
            }

            if (! Schema::hasColumn('hr_organizational_units', 'type')) {
                $table->string('type')->nullable()->after('name');
            }

            if (! Schema::hasColumn('hr_organizational_units', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    private function backfillUnitsFromLegacyTiers(): void
    {
        if (
            ! Schema::hasTable('hr_organizational_tiers') ||
            ! Schema::hasColumn('hr_organizational_tiers', 'name') ||
            ! Schema::hasColumn('hr_organizational_units', 'name')
        ) {
            return;
        }

        $tiers = DB::table('hr_organizational_tiers')
            ->orderBy('id')
            ->get();

        if ($tiers->isEmpty()) {
            return;
        }

        foreach ($tiers as $tier) {
            DB::table('hr_organizational_units')->updateOrInsert(
                ['id' => $tier->id],
                [
                    'uuid' => $tier->uuid ?: (string) Str::uuid(),
                    'organization_id' => $tier->organization_id,
                    'parent_id' => null,
                    'name' => $tier->name ?: "Unit {$tier->id}",
                    'type' => $tier->external_type ?: $tier->tier_type ?: 'unit',
                    'created_at' => $tier->created_at ?? now(),
                    'updated_at' => $tier->updated_at ?? now(),
                    'deleted_at' => $tier->deleted_at ?? null,
                ]
            );
        }

        $unitIds = DB::table('hr_organizational_units')->pluck('id')->all();

        foreach ($tiers as $tier) {
            if ($tier->parent_tier_id && in_array($tier->parent_tier_id, $unitIds, true)) {
                DB::table('hr_organizational_units')
                    ->where('id', $tier->id)
                    ->update(['parent_id' => $tier->parent_tier_id]);
            }
        }
    }

    private function repairStaffAssignmentsTable(): void
    {
        if (! Schema::hasTable('hr_staff_assignments')) {
            return;
        }

        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_staff_assignments', 'organizational_unit_id')) {
                $table->foreignId('organizational_unit_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('hr_organizational_units')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('hr_staff_assignments', 'organizational_tier_id')) {
            DB::table('hr_staff_assignments')
                ->whereNull('organizational_unit_id')
                ->whereIn('organizational_tier_id', DB::table('hr_organizational_units')->select('id'))
                ->update([
                    'organizational_unit_id' => DB::raw('organizational_tier_id'),
                ]);
        }
    }

    private function repairDutyRostersTable(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            Schema::create('hr_duty_rosters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organizational_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
                $table->string('cadre_or_discipline')->nullable();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('created_by')->nullable();
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organizational_unit_id', 'cadre_or_discipline']);
            });

            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_duty_rosters', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'organizational_unit_id')) {
                $table->foreignId('organizational_unit_id')->nullable()->after('organization_id')->constrained('hr_organizational_units')->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'cadre_or_discipline')) {
                $table->string('cadre_or_discipline')->nullable()->after('organizational_unit_id');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'name')) {
                $table->string('name')->nullable()->after('cadre_or_discipline');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'start_date')) {
                $table->date('start_date')->nullable()->after('name');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'created_by')) {
                $table->string('created_by')->nullable()->after('end_date');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'status')) {
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->after('created_by');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'approval_request_id')) {
                $table->foreignId('approval_request_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('hr_approval_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'approval_status')) {
                $table->string('approval_status')->default('not_submitted')->after('approval_request_id');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('published_at');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::table('hr_duty_rosters')
            ->whereNull('approval_status')
            ->update(['approval_status' => 'not_submitted']);
    }

    private function repairDutyRosterEntriesTable(): void
    {
        if (Schema::hasTable('hr_duty_roster_entries')) {
            return;
        }

        Schema::create('hr_duty_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duty_roster_id')->constrained('hr_duty_rosters')->cascadeOnDelete();
            $table->date('roster_date');
            $table->foreignId('staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->string('staff_uuid')->nullable();
            $table->string('staff_name');
            $table->string('staff_cadre')->nullable();
            $table->foreignId('shift_type_id')->nullable()->constrained('hr_shift_types')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'roster_date']);
            $table->index(['duty_roster_id', 'roster_date']);
            $table->index(['staff_assignment_id', 'roster_date']);
        });
    }

    private function repairApprovalRequestsTable(): void
    {
        if (! Schema::hasTable('hr_approval_requests')) {
            return;
        }

        Schema::table('hr_approval_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_approval_requests', 'linked_roster_id')) {
                $table->foreignId('linked_roster_id')
                    ->nullable()
                    ->after('approval_category')
                    ->constrained('hr_duty_rosters')
                    ->nullOnDelete();
            }
        });
    }
};
