<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('caller_logs');

        Schema::create('caller_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('caller_id')->nullable();
            $table->foreignId('service_point_id')->nullable()->constrained('service_points')->onDelete('set null');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->string('client_name')->nullable();
            $table->string('item_name')->nullable();
            $table->foreignId('called_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('called_at');
            $table->timestamps();

            $table->index(['business_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caller_logs');
    }
};
