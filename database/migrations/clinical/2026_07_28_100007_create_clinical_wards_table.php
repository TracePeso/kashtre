<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD's `client_spaces` (Building/Wing -> Ward -> Room), renamed to
        // avoid colliding with the app's existing App\Models\ClientSpace
        // (a generic named room/service-point already used by HR/Imaging).
        // client_space_id below is a plain indexed pointer to that existing
        // room record — not a FK, since ClientSpace lives on the default
        // connection and this table lives on 'clinical' (kept isolated
        // even though both point at the same physical DB today).
        Schema::connection('clinical')->create('clinical_wards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('building_wing', 64)->nullable();
            $table->string('ward_code', 64);
            $table->string('ward_name', 128);
            $table->unsignedBigInteger('client_space_id')->nullable()->index(); // App\Models\ClientSpace.id (room), logical link only
            $table->unsignedBigInteger('inventory_store_id')->nullable()->index(); // Inventory sub-store, logical link only
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'ward_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_wards');
    }
};
