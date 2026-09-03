<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drives the HR module's role_map fallback (App\Models\User::hasPermission
            // in HR_MODULE, config/hr_permissions.php) when synced via
            // hr:sync-from-kashtre / HrModuleSyncService::userPayload(). Null means no
            // HR module access.
            $table->string('hr_role')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hr_role');
        });
    }
};
