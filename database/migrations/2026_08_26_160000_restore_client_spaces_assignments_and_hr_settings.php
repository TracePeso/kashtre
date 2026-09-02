<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreate Client Spaces + Space→End Store assignments + HR settings after
 * 2026_08_25_120000_drop_louis_client_spaces_and_calling_tables dropped them.
 * Idempotent: safe when tables already exist (fresh installs that never dropped).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_spaces')) {
            Schema::create('client_spaces', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->string('name');
                $table->string('description')->nullable();
                $table->foreignId('business_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
                $table->foreignId('space_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deputy_space_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('alternate_space_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('client_spaces', function (Blueprint $table) {
                if (! Schema::hasColumn('client_spaces', 'space_head_id')) {
                    $table->foreignId('space_head_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('client_spaces', 'deputy_space_head_id')) {
                    $table->foreignId('deputy_space_head_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('client_spaces', 'alternate_space_head_id')) {
                    $table->foreignId('alternate_space_head_id')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('client_space_store_assignments')) {
            Schema::create('client_space_store_assignments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_space_id')->constrained('client_spaces')->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->string('fulfillment_strategy', 32)->default('DISCRETE_IMMEDIATE');
                $table->boolean('supports_approved_pool')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['client_space_id']);
                $table->index(['business_id', 'store_id']);
                $table->index(['business_id', 'is_active']);
            });
        } elseif (! Schema::hasColumn('client_space_store_assignments', 'supports_approved_pool')) {
            Schema::table('client_space_store_assignments', function (Blueprint $table) {
                $table->boolean('supports_approved_pool')
                    ->default(true)
                    ->after('fulfillment_strategy');
            });
        }

        if (! Schema::hasTable('kashtre_hr_module_settings')) {
            Schema::create('kashtre_hr_module_settings', function (Blueprint $table) {
                $table->id();
                $table->string('url')->nullable();
                $table->text('api_key')->nullable();
                $table->boolean('sync_enabled')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_space_store_assignments');
        Schema::dropIfExists('client_spaces');
        Schema::dropIfExists('kashtre_hr_module_settings');
    }
};
