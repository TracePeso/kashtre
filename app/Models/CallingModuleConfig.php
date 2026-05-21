<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallingModuleConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'is_active',
        'audio_enabled',
        'video_enabled',
        'description',
        'tts_voice_id',
        'tts_voice_name',
        'tts_stability',
        'tts_similarity_boost',
        'tts_speed',
        'announcement_message',
        'default_emergency_message',
        'emergency_display_message',
        'emergency_display_duration',
        'emergency_repeat_count',
        'emergency_repeat_interval',
        'emergency_button_1_name',
        'emergency_button_1_message',
        'emergency_button_1_display_message',
        'emergency_button_1_color',
        'emergency_button_2_name',
        'emergency_button_2_message',
        'emergency_button_2_display_message',
        'emergency_button_2_color',
        'emergency_key_cooldown',
        'emergency_flash_frequency',
        'emergency_flash_on',
        'emergency_flash_off',
        'emergency_tts_voice_id',
        'emergency_tts_voice_name',
        'emergency_tts_stability',
        'emergency_tts_similarity_boost',
        'emergency_tts_speed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'audio_enabled'        => 'boolean',
        'video_enabled'        => 'boolean',
        'tts_stability'            => 'float',
        'tts_similarity_boost'     => 'float',
        'tts_speed'                => 'float',
        'emergency_repeat_count'    => 'integer',
        'emergency_repeat_interval' => 'integer',
        'emergency_display_duration'  => 'integer',
        'emergency_key_cooldown'           => 'integer',
        'emergency_flash_frequency'        => 'integer',
        'emergency_flash_on'               => 'integer',
        'emergency_flash_off'              => 'integer',
        'emergency_tts_stability'          => 'float',
        'emergency_tts_similarity_boost'   => 'float',
        'emergency_tts_speed'              => 'float',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
