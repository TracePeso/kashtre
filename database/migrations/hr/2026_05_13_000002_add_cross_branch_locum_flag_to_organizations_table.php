<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'allow_cross_branch_locum_coverage')) {
                $table->boolean('allow_cross_branch_locum_coverage')
                    ->default(false)
                    ->after('weekend_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'allow_cross_branch_locum_coverage')) {
                $table->dropColumn('allow_cross_branch_locum_coverage');
            }
        });
    }
};
