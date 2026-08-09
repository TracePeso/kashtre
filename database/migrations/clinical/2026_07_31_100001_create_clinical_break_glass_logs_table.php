<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Engineering doc's clinical_break_glass_logs — an emergency
        // access grant when ReBAC (CareRelationshipChecker, Chunk 2)
        // finds no active care relationship, time-boxed and audited.
        Schema::connection('clinical')->create('clinical_break_glass_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->string('reason_code', 64); // clinical_reason_codes_master, category BREAK_GLASS
            $table->text('justification_note')->nullable();
            $table->timestamp('granted_until');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'user_id', 'created_at'], 'idx_break_glass_audit');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_break_glass_logs');
    }
};
