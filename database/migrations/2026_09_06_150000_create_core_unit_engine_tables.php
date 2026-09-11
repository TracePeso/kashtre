<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Unit Engine foundation registries (Main Module).
 * tenant_key: 'SYSTEM' for platform seed, or (string) business_id for tenant units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_quantity_kinds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 64);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('dimension_vector');
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('ACTIVE');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'code'], 'uq_quantity_kind_tenant_code');
            $table->index(['tenant_key', 'status', 'effective_from'], 'idx_quantity_kind_active');
        });

        Schema::create('core_unit_prefixes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 32);
            $table->string('name', 64);
            $table->string('symbol', 16);
            $table->smallInteger('radix')->default(10);
            $table->smallInteger('exponent');
            $table->string('factor_decimal', 80);
            $table->boolean('is_binary')->default(false);
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('ACTIVE');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'code'], 'uq_unit_prefix_tenant_code');
            $table->unique(['tenant_key', 'symbol', 'is_binary'], 'uq_unit_prefix_symbol');
        });

        Schema::create('core_units', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 128);
            $table->string('unit_class', 40);
            $table->string('canonical_name', 180);
            $table->string('symbol', 80);
            $table->string('ascii_symbol', 120)->nullable();
            $table->string('standard_system_uri', 255)->nullable();
            $table->string('ucum_code', 255)->nullable();
            $table->string('ucum_version', 32)->nullable();
            $table->string('standard_verification_status', 24)->default('UNVERIFIED');
            $table->foreignId('quantity_kind_id')->constrained('core_quantity_kinds')->restrictOnDelete();
            $table->json('dimension_vector');
            $table->boolean('allows_prefix')->default(false);
            $table->boolean('allows_composition')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('DRAFT');
            $table->foreignId('replaced_by_unit_id')->nullable()->constrained('core_units')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'code'], 'uq_core_units_tenant_code');
            $table->index(['tenant_key', 'quantity_kind_id', 'status'], 'idx_core_units_kind_status');
            $table->index(['tenant_key', 'ucum_code'], 'idx_core_units_ucum');
        });

        Schema::create('core_unit_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('unit_id')->constrained('core_units')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('scale_decimal', 100)->default('1');
            $table->string('offset_decimal', 100)->default('0');
            $table->string('reference_unit_public_id', 26)->nullable();
            $table->string('named_algorithm', 100)->nullable();
            $table->unsignedTinyInteger('calculation_scale')->default(18);
            $table->unsignedTinyInteger('display_precision')->default(2);
            $table->string('rounding_mode', 32)->default('HALF_UP');
            $table->json('localized_labels')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'version_no'], 'uq_core_unit_versions');
            $table->index(['unit_id', 'status', 'effective_from'], 'idx_core_unit_version_effective');
        });

        Schema::create('core_unit_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_version_id')->constrained('core_unit_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('operator', 20);
            $table->foreignId('component_unit_id')->nullable()->constrained('core_units')->restrictOnDelete();
            $table->foreignId('prefix_id')->nullable()->constrained('core_unit_prefixes')->restrictOnDelete();
            $table->smallInteger('exponent')->default(1);
            $table->string('scalar_decimal', 80)->nullable();
            $table->string('annotation', 120)->nullable();
            $table->timestamps();

            $table->unique(['unit_version_id', 'sequence'], 'uq_unit_component_sequence');
        });

        Schema::create('core_unit_aliases', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->foreignId('unit_id')->constrained('core_units')->restrictOnDelete();
            $table->string('alias', 180);
            $table->string('locale', 12)->default('en');
            $table->string('scope_module', 64)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->string('status', 24)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['tenant_key', 'alias', 'locale', 'scope_module'], 'uq_unit_alias_scope');
        });

        Schema::create('core_conversion_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->foreignId('from_unit_id')->constrained('core_units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('core_units')->restrictOnDelete();
            $table->string('rule_type', 40);
            $table->string('scale_decimal', 100)->nullable();
            $table->string('offset_decimal', 100)->nullable();
            $table->string('named_algorithm', 100)->nullable();
            $table->boolean('is_bidirectional')->default(false);
            $table->unsignedTinyInteger('calculation_scale')->default(18);
            $table->unsignedTinyInteger('display_precision')->default(4);
            $table->string('rounding_mode', 32)->default('HALF_UP');
            $table->string('min_input_decimal', 100)->nullable();
            $table->string('max_input_decimal', 100)->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status', 24)->default('DRAFT');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'from_unit_id', 'to_unit_id', 'version_no'], 'uq_conversion_rule_version');
            $table->index(['tenant_key', 'from_unit_id', 'to_unit_id', 'status'], 'idx_conversion_lookup');
        });

        Schema::create('core_conversion_rule_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_rule_id')->constrained('core_conversion_rules')->cascadeOnDelete();
            $table->string('context_type', 40);
            $table->string('context_public_id', 64);
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->unique(['conversion_rule_id', 'context_type', 'context_public_id'], 'uq_conversion_context');
        });

        Schema::create('core_module_unit_policies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('module_code', 64);
            $table->string('domain_object_type', 64);
            $table->string('domain_object_public_id', 64);
            $table->foreignId('unit_id')->constrained('core_units')->restrictOnDelete();
            $table->string('usage_role', 24);
            $table->unsignedTinyInteger('display_precision')->nullable();
            $table->string('status', 24)->default('ACTIVE');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_key', 'module_code', 'domain_object_type', 'domain_object_public_id', 'unit_id', 'usage_role'],
                'uq_module_unit_policy'
            );
        });

        Schema::create('core_unit_audit', function (Blueprint $table) {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->string('tenant_key', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->string('object_type', 64);
            $table->string('object_public_id', 64);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_key', 'object_type', 'object_public_id'], 'idx_unit_audit_object');
        });

        Schema::create('core_legacy_unit_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_key', 64);
            $table->string('source_module', 64);
            $table->string('source_table', 128);
            $table->string('source_value', 255);
            $table->foreignId('unit_id')->nullable()->constrained('core_units')->restrictOnDelete();
            $table->string('match_method', 32)->default('PENDING');
            $table->string('status', 24)->default('PENDING');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'source_module', 'source_table', 'source_value'], 'uq_legacy_unit_mapping');
        });

        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'sale_unit_public_id')) {
                $table->string('sale_unit_public_id', 26)->nullable()->after('suom_per_ouom');
            }
            if (! Schema::hasColumn('items', 'order_unit_public_id')) {
                $table->string('order_unit_public_id', 26)->nullable()->after('sale_unit_public_id');
            }
            if (! Schema::hasColumn('items', 'packaging_rule_public_id')) {
                $table->string('packaging_rule_public_id', 26)->nullable()->after('order_unit_public_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            foreach (['packaging_rule_public_id', 'order_unit_public_id', 'sale_unit_public_id'] as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('core_legacy_unit_mappings');
        Schema::dropIfExists('core_unit_audit');
        Schema::dropIfExists('core_module_unit_policies');
        Schema::dropIfExists('core_conversion_rule_contexts');
        Schema::dropIfExists('core_conversion_rules');
        Schema::dropIfExists('core_unit_aliases');
        Schema::dropIfExists('core_unit_components');
        Schema::dropIfExists('core_unit_versions');
        Schema::dropIfExists('core_units');
        Schema::dropIfExists('core_unit_prefixes');
        Schema::dropIfExists('core_quantity_kinds');
    }
};
