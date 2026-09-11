<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "user pool" for a workflow step — real FK to
        // imaging_workflow_steps since both sides are within Imaging;
        // user_id stays a plain indexed column (no FK), same cross-domain
        // decoupling rule used for every other reference to a Main Module
        // User throughout this module.
        Schema::create('imaging_workflow_step_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_workflow_step_id')
                ->constrained('imaging_workflow_steps')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();

            $table->unique(['imaging_workflow_step_id', 'user_id'], 'imaging_workflow_step_users_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_step_users');
    }
};
