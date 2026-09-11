<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_care_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('clinical_care_teams')->cascadeOnDelete(); // same connection — real FK is fine
            $table->unsignedBigInteger('user_id')->index(); // plain logical key -> core users table
            $table->string('role_code', 64); // 'CONSULTANT', 'RESIDENT', 'NURSE', 'NUTRITIONIST'
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_care_team_members');
    }
};
