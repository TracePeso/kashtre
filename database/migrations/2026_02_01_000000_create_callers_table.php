<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('status')->default('active');
            $table->string('token', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('display_token', 10)->nullable()->index();
            $table->text('announcement_message')->nullable();
            $table->decimal('speech_rate', 3, 2)->default(1.0);
            $table->decimal('speech_volume', 3, 2)->default(1.0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('caller_service_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caller_id');
            $table->unsignedBigInteger('service_point_id');
            $table->timestamps();

            $table->foreign('caller_id')->references('id')->on('callers')->onDelete('cascade');
            $table->foreign('service_point_id')->references('id')->on('service_points')->onDelete('cascade');
            $table->unique(['caller_id', 'service_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caller_service_points');
        Schema::dropIfExists('callers');
    }
};
