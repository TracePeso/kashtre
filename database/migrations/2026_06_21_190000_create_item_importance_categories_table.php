<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_importance_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
        });

        $now = now();
        $businessIds = DB::table('businesses')->where('id', '!=', 1)->pluck('id');

        foreach ($businessIds as $businessId) {
            foreach ([
                ['slug' => 'essential', 'name' => 'Essential'],
                ['slug' => 'non_essential', 'name' => 'Non-essential'],
            ] as $category) {
                DB::table('item_importance_categories')->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'business_id' => $businessId,
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_importance_categories');
    }
};
