<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'staff_uuid')) {
                $table->string('staff_uuid')->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'is_hr_admin')) {
                $table->boolean('is_hr_admin')->default(false)->after('staff_uuid');
            }

            if (! Schema::hasColumn('users', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('email_verified_at');
            }
        });

        DB::statement("UPDATE users SET staff_uuid = uuid WHERE staff_uuid IS NULL OR staff_uuid = ''");
        DB::statement("UPDATE users SET deactivated_at = COALESCE(updated_at, NOW()) WHERE status = 'inactive' AND deactivated_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deactivated_at')) {
                $table->dropColumn('deactivated_at');
            }

            if (Schema::hasColumn('users', 'is_hr_admin')) {
                $table->dropColumn('is_hr_admin');
            }

            if (Schema::hasColumn('users', 'staff_uuid')) {
                $table->dropUnique(['staff_uuid']);
                $table->dropColumn('staff_uuid');
            }
        });
    }
};
