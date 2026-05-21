<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixExistingTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:existing-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix migration records for existing tables';

    protected ?int $batch = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for existing tables that need migration records...');

        $this->markMigrationAsRunIf(
            '2025_09_05_171600_create_transactions_table',
            Schema::hasTable('transactions'),
            'transactions table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_02_01_000000_create_callers_table',
            Schema::hasTable('callers'),
            'callers table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_02_27_100000_add_room_id_to_service_points_table',
            $this->hasColumns('service_points', ['room_id']),
            'service_points.room_id already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_02_27_100002_create_calling_module_configs_table',
            Schema::hasTable('calling_module_configs'),
            'calling_module_configs table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_02_28_012335_add_display_token_to_callers_table',
            $this->hasColumns('callers', ['display_token']),
            'callers.display_token already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_02_28_100000_create_caller_logs_table',
            Schema::hasTable('caller_logs'),
            'caller_logs table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_01_100000_add_call_settings_to_callers_table',
            $this->hasColumns('callers', ['announcement_message', 'speech_rate', 'speech_volume']),
            'caller call settings already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_03_100000_add_tts_settings_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', [
                'tts_voice_id',
                'tts_voice_name',
                'tts_stability',
                'tts_similarity_boost',
                'tts_speed',
                'announcement_message',
            ]),
            'calling module TTS settings already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_09_100000_add_audio_video_enabled_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', ['audio_enabled', 'video_enabled']),
            'calling module audio/video settings already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_09_130000_create_emergency_alerts_table',
            Schema::hasTable('emergency_alerts'),
            'emergency_alerts table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_09_140000_add_default_emergency_message_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', ['default_emergency_message']),
            'default emergency message already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_14_100000_add_emergency_repeat_settings_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', ['emergency_repeat_count', 'emergency_repeat_interval']),
            'emergency repeat settings already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_17_200000_add_emergency_buttons_to_calling_module_configs',
            $this->hasColumns('calling_module_configs', [
                'emergency_button_1_name',
                'emergency_button_1_message',
                'emergency_button_1_color',
                'emergency_button_2_name',
                'emergency_button_2_message',
                'emergency_button_2_color',
            ]),
            'emergency button columns already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_18_100000_add_emergency_display_message_to_configs',
            $this->hasColumns('calling_module_configs', ['emergency_display_message']),
            'emergency display message already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_18_110000_add_button_display_messages_to_calling_module_configs',
            $this->hasColumns('calling_module_configs', [
                'emergency_button_1_display_message',
                'emergency_button_2_display_message',
            ]),
            'emergency button display messages already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_18_120000_add_emergency_display_duration_to_configs',
            $this->hasColumns('calling_module_configs', ['emergency_display_duration']),
            'emergency display duration already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_18_130000_add_emergency_key_cooldown_to_calling_module_configs',
            $this->hasColumns('calling_module_configs', ['emergency_key_cooldown']),
            'emergency key cooldown already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_18_150000_add_display_message_to_emergency_alerts_table',
            $this->hasColumns('emergency_alerts', ['display_message']),
            'emergency alert display message already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_22_100000_add_queue_fields_to_emergency_alerts_table',
            $this->hasColumns('emergency_alerts', ['color', 'scheduled_announce_at']),
            'emergency alert queue fields already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_22_100001_add_flash_frequency_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', ['emergency_flash_frequency']),
            'emergency flash frequency already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_22_110000_add_emergency_tts_fields_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', [
                'emergency_tts_voice_id',
                'emergency_tts_voice_name',
                'emergency_tts_stability',
                'emergency_tts_similarity_boost',
                'emergency_tts_speed',
            ]),
            'emergency TTS fields already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_22_200000_add_flash_on_off_to_calling_module_configs_table',
            $this->hasColumns('calling_module_configs', ['emergency_flash_on', 'emergency_flash_off']),
            'emergency flash on/off settings already exist'
        );

        $this->markMigrationAsRunIf(
            '2026_03_22_234729_create_jobs_table',
            Schema::hasTable('jobs'),
            'jobs table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_24_010000_create_p2p_calls_table',
            Schema::hasTable('p2p_calls'),
            'p2p_calls table already exists'
        );

        $this->markMigrationAsRunIf(
            '2026_03_25_100000_create_pa_sections_table',
            Schema::hasTable('pa_sections') && Schema::hasTable('pa_section_callers'),
            'pa_sections tables already exist'
        );

        $this->info('Existing tables check completed');

        return 0;
    }

    protected function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    protected function markMigrationAsRunIf(string $migration, bool $condition, string $reason): void
    {
        if (! $condition || DB::table('migrations')->where('migration', $migration)->exists()) {
            return;
        }

        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $this->nextBatch(),
        ]);

        $this->info("Marked {$migration} as run ({$reason})");
    }

    protected function nextBatch(): int
    {
        if ($this->batch === null) {
            $this->batch = (DB::table('migrations')->max('batch') ?? 0) + 1;
        }

        return $this->batch;
    }
}
