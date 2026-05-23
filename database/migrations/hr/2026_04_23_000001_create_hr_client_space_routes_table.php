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
        if (! Schema::hasTable('hr_client_space_routes')) {
            Schema::create('hr_client_space_routes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_space_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
                $table->foreignId('routing_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['client_space_unit_id', 'routing_unit_id'], 'hr_client_space_routes_unique');
                $table->index(['organization_id', 'routing_unit_id'], 'hr_client_space_routes_org_routing_index');
                $table->index(['client_space_unit_id', 'is_primary'], 'hr_client_space_routes_primary_index');
            });
        }

        if (
            ! Schema::hasTable('hr_organizational_units') ||
            ! Schema::hasColumn('hr_organizational_units', 'unit_kind') ||
            ! Schema::hasColumn('hr_organizational_units', 'parent_id')
        ) {
            return;
        }

        $now = now();
        $clientSpaces = DB::table('hr_organizational_units')
            ->where('unit_kind', 'client_space')
            ->whereNotNull('parent_id')
            ->get(['id', 'uuid', 'organization_id', 'parent_id', 'created_at', 'updated_at']);

        foreach ($clientSpaces as $clientSpace) {
            $routeExists = DB::table('hr_client_space_routes')
                ->where('client_space_unit_id', $clientSpace->id)
                ->where('routing_unit_id', $clientSpace->parent_id)
                ->exists();

            if ($routeExists) {
                DB::table('hr_client_space_routes')
                    ->where('client_space_unit_id', $clientSpace->id)
                    ->where('routing_unit_id', $clientSpace->parent_id)
                    ->update(['is_primary' => true, 'updated_at' => $now]);

                continue;
            }

            DB::table('hr_client_space_routes')->insert([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $clientSpace->organization_id,
                'client_space_unit_id' => $clientSpace->id,
                'routing_unit_id' => $clientSpace->parent_id,
                'is_primary' => true,
                'created_at' => $clientSpace->created_at ?? $now,
                'updated_at' => $clientSpace->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_client_space_routes');
    }
};
