<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_care_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('client_id')->index(); // Client::client_id (business-scoped)
            $table->string('visit_id')->nullable()->index();
            $table->enum('assignment_model', ['INDIVIDUAL', 'ROLE', 'TEAM', 'HYBRID']);
            $table->unsignedBigInteger('primary_doctor_user_id')->nullable(); // plain logical key -> core users table
            $table->unsignedBigInteger('primary_nurse_user_id')->nullable();
            $table->foreignId('assigned_team_id')->nullable()->constrained('clinical_care_teams'); // same connection
            $table->string('assigned_role_code', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'client_id', 'is_active'], 'idx_care_assign_patient');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_care_assignments');
    }
};
