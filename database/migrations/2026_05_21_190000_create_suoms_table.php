<?php

use App\Models\ItemUnit;
use App\Models\Suom;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suoms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'name']);
        });

        ItemUnit::query()
            ->where('business_id', '!=', 1)
            ->each(function (ItemUnit $unit) {
                Suom::query()->firstOrCreate(
                    [
                        'business_id' => $unit->business_id,
                        'name' => $unit->name,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'description' => $unit->description,
                        'is_active' => true,
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('suoms');
    }
};
