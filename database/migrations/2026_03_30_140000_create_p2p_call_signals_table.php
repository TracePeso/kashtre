<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_call_id')->constrained('p2p_calls')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->string('type');
            $table->json('data');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['receiver_id', 'delivered_at']);
            $table->index(['p2p_call_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_call_signals');
    }
};
