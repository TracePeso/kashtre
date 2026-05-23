<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_organizational_units', 'unit_kind')) {
                $table->string('unit_kind')->default('routing_node')->after('type');
                $table->index(['organization_id', 'unit_kind'], 'hr_units_org_kind_index');
            }
        });

        if (! Schema::hasTable('hr_client_spaces')) {
            Schema::create('hr_client_spaces', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organizational_unit_id')->unique()->constrained('hr_organizational_units')->cascadeOnDelete();
                $table->string('external_uuid');
                $table->string('external_business_uuid')->nullable();
                $table->string('external_branch_uuid')->nullable();
                $table->string('source_name');
                $table->json('source_payload')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['organization_id', 'external_uuid'], 'hr_client_spaces_org_external_unique');
                $table->index(['organization_id', 'active'], 'hr_client_spaces_org_active_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_client_spaces');

        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (Schema::hasColumn('hr_organizational_units', 'unit_kind')) {
                $table->dropIndex('hr_units_org_kind_index');
                $table->dropColumn('unit_kind');
            }
        });
    }
};
