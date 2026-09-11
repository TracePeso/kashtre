<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Mirrors ImagingConsumptionException's shape — same "no store
        // resolvable" / "resolved store has no stock" failure modes,
        // surfaced for supervisor review instead of silently dropped.
        Schema::connection('clinical')->create('clinical_consumption_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('client_id')->index();
            $table->string('item_code', 64);
            $table->text('exception_reason');
            $table->boolean('resolved')->default(false);
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_consumption_exceptions');
    }
};
