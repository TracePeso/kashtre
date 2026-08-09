<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD's `patient_beds`, renamed for clarity alongside clinical_wards.
        Schema::connection('clinical')->create('clinical_beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('clinical_wards'); // same connection — real FK is fine
            $table->string('bed_code', 32); // e.g. 'BED-01'
            $table->enum('operational_state', ['AVAILABLE', 'OCCUPIED', 'RESERVED'])->default('AVAILABLE');
            $table->string('current_client_id')->nullable(); // Client::client_id (business-scoped), plain logical key
            $table->string('current_visit_id')->nullable();
            $table->boolean('is_overflow')->default(false); // surge/extra bed flag
            $table->timestamps();

            $table->unique(['ward_id', 'bed_code'], 'uid_space_bed');
            $table->index(['current_client_id', 'operational_state'], 'idx_bed_occupancy');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_beds');
    }
};
