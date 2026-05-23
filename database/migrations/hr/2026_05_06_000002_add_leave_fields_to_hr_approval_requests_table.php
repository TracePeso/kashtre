<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_approval_requests')) {
            return;
        }

        Schema::table('hr_approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_approval_requests', 'leave_type_id')) {
                $table->foreignId('leave_type_id')
                    ->nullable()
                    ->constrained('hr_leave_types')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_approval_requests', 'staff_assignment_id')) {
                $table->foreignId('staff_assignment_id')
                    ->nullable()
                    ->constrained('hr_staff_assignments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_approval_requests', 'start_date')) {
                $table->date('start_date')->nullable();
            }

            if (! Schema::hasColumn('hr_approval_requests', 'end_date')) {
                $table->date('end_date')->nullable();
            }

            if (! Schema::hasColumn('hr_approval_requests', 'requested_days')) {
                $table->decimal('requested_days', 8, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_approval_requests')) {
            return;
        }

        Schema::table('hr_approval_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('hr_approval_requests', 'leave_type_id')) {
                $table->dropConstrainedForeignId('leave_type_id');
            }

            if (Schema::hasColumn('hr_approval_requests', 'staff_assignment_id')) {
                $table->dropConstrainedForeignId('staff_assignment_id');
            }

            $dropColumns = [];

            foreach (['start_date', 'end_date', 'requested_days'] as $column) {
                if (Schema::hasColumn('hr_approval_requests', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
