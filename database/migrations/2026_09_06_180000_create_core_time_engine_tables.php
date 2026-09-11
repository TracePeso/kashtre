<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Time Engine registries (Main Module) — Phases 1–7 foundation tables.
 * tenant_key: 'SYSTEM' or (string) business_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_time_zones', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('iana_id', 64)->unique();
            $table->string('display_name', 180);
            $table->string('region_code', 32)->nullable();
            $table->string('canonical_iana_id', 64)->nullable();
            $table->string('status', 24)->default('ACTIVE');
            $table->string('tzdb_release', 64)->nullable();
            $table->boolean('is_fixed_offset')->default(false);
            $table->timestamps();

            $table->index(['status', 'region_code'], 'idx_core_tz_status_region');
        });

        Schema::create('core_time_zone_policies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('scope_type', 40);
            $table->string('subject_public_id', 64);
            $table->string('purpose', 24)->default('OPERATIONAL');
            $table->string('iana_id', 64);
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status', 24)->default('DRAFT');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_key', 'scope_type', 'subject_public_id', 'status'], 'idx_tz_policy_lookup');
            $table->index(['tenant_key', 'purpose', 'status', 'effective_from'], 'idx_tz_policy_effective');
        });

        Schema::create('core_time_clock_health', function (Blueprint $table) {
            $table->id();
            $table->string('node_key', 120);
            $table->string('status', 24)->default('UNKNOWN');
            $table->integer('drift_seconds')->nullable();
            $table->string('source', 120)->nullable();
            $table->dateTime('checked_at_utc');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['node_key'], 'uq_clock_health_node');
        });

        Schema::create('core_business_calendars', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 64);
            $table->string('name', 180);
            $table->string('iana_id', 64)->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status', 24)->default('ACTIVE');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'code', 'version_no'], 'uq_biz_calendar_version');
        });

        Schema::create('core_business_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('core_business_calendars')->cascadeOnDelete();
            $table->date('local_date');
            $table->string('day_type', 24)->default('BUSINESS'); // BUSINESS, WEEKEND, HOLIDAY, CLOSED
            $table->string('label', 180)->nullable();
            $table->timestamps();

            $table->unique(['calendar_id', 'local_date'], 'uq_calendar_day');
        });

        Schema::create('core_financial_periods', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 64);
            $table->string('name', 180);
            $table->string('period_type', 24)->default('MONTH'); // DAY, WEEK, MONTH, QUARTER, YEAR, CUSTOM
            $table->date('local_start_date');
            $table->date('local_end_date'); // inclusive end date in local calendar
            $table->string('status', 24)->default('OPEN'); // OPEN, CLOSED, REOPENED
            $table->string('iana_id', 64)->nullable();
            $table->dateTime('closed_at_utc')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_key', 'code'], 'uq_fin_period_code');
            $table->index(['tenant_key', 'status', 'local_start_date'], 'idx_fin_period_active');
        });

        Schema::create('core_schedule_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('code', 64);
            $table->string('name', 180);
            $table->string('iana_id', 64);
            $table->string('local_time', 8); // HH:MM:SS wall clock
            $table->string('recurrence', 24)->default('DAILY'); // ONCE, DAILY, WEEKLY, MONTHLY
            $table->json('recurrence_rules')->nullable(); // e.g. weekdays [1,2,3,4,5]
            $table->string('dst_gap_policy', 24)->default('SHIFT_FORWARD');
            $table->string('dst_overlap_policy', 24)->default('EARLIER');
            $table->date('local_start_date');
            $table->date('local_end_date')->nullable();
            $table->string('status', 24)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['tenant_key', 'code'], 'uq_schedule_def_code');
        });

        Schema::create('core_schedule_occurrences', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('schedule_definition_id')->constrained('core_schedule_definitions')->cascadeOnDelete();
            $table->dateTime('occurred_at_utc');
            $table->string('occurred_local_datetime', 32);
            $table->string('iana_id', 64);
            $table->smallInteger('utc_offset_minutes');
            $table->string('materialization_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['schedule_definition_id', 'occurred_at_utc'], 'uq_schedule_occurrence');
        });

        Schema::create('core_device_time_observations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_key', 64);
            $table->string('device_public_id', 64);
            $table->string('raw_timestamp', 80);
            $table->string('raw_timezone', 64)->nullable();
            $table->dateTime('normalized_at_utc')->nullable();
            $table->string('normalized_local_datetime', 32)->nullable();
            $table->string('status', 24)->default('PENDING'); // PENDING, ACCEPTED, QUARANTINED, CORRECTED
            $table->string('confidence', 24)->default('MEDIUM');
            $table->string('quarantine_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_key', 'device_public_id', 'status'], 'idx_device_time_status');
        });

        Schema::create('core_time_audit', function (Blueprint $table) {
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
            $table->timestamps();

            $table->index(['tenant_key', 'object_type', 'object_public_id'], 'idx_time_audit_object');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_time_audit');
        Schema::dropIfExists('core_device_time_observations');
        Schema::dropIfExists('core_schedule_occurrences');
        Schema::dropIfExists('core_schedule_definitions');
        Schema::dropIfExists('core_financial_periods');
        Schema::dropIfExists('core_business_calendar_days');
        Schema::dropIfExists('core_business_calendars');
        Schema::dropIfExists('core_time_clock_health');
        Schema::dropIfExists('core_time_zone_policies');
        Schema::dropIfExists('core_time_zones');
    }
};
