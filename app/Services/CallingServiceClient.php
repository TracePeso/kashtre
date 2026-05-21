<?php

namespace App\Services;

use App\Models\Caller;
use App\Models\CallingModuleConfig;
use App\Models\EmergencyAlert;
use App\Models\ServiceDeliveryQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CallingServiceClient
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = config('services.calling_service.url', 'http://127.0.0.1:8001');
        $this->secret  = config('services.calling_service.sync_secret', '');
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->secret,
            ])
            ->timeout(5);
    }

    public function syncQueue(ServiceDeliveryQueue $queueItem, ?string $forceStatus = null)
    {
        if (!$this->baseUrl) return;

        try {
            $client     = $queueItem->client;
            $clientName = $client ? ($client->first_name . ' ' . $client->surname) : null;

            // No status mapping — display is only affected by Call and Save & Exit
            if ($forceStatus) {
                $status = $forceStatus;
            } else {
                $status = $queueItem->status;
            }

            $this->client()->post('/api/v1/sync/queue', [
                'uuid'             => $queueItem->uuid,
                'business_id'      => $queueItem->business_id,
                'service_point_id' => $queueItem->service_point_id,
                'visit_id'         => $client ? ($client->visit_id ?? ('Client #' . $queueItem->client_id)) : ('Client #' . $queueItem->client_id),
                'client_name'      => $clientName,
                'status'           => $status,
                'priority'         => $queueItem->priority ?? 'normal',
                'queued_at'        => $queueItem->queued_at
                    ? $queueItem->queued_at->toDateTimeString()
                    : now()->toDateTimeString(),
                'is_force'         => $forceStatus !== null,
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::syncQueue failed: ' . $e->getMessage());
        }
    }

    public function deleteQueueItem(string $uuid)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->delete("/api/v1/sync/queue/{$uuid}");
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::deleteQueueItem failed: ' . $e->getMessage());
        }
    }

    public function triggerAnnouncement($callerId, $logId, $visitId, $clientName, $servicePointName, $roomName, bool $audioEnabled = true, bool $videoEnabled = true)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/announce', [
                'caller_id'          => $callerId,
                'kashtre_log_id'     => $logId,
                'visit_id'           => $visitId,
                'client_name'        => $clientName,
                'service_point_name' => $servicePointName,
                'room_name'          => $roomName,
                'audio_enabled'      => $audioEnabled,
                'video_enabled'      => $videoEnabled,
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::triggerAnnouncement failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch the list of available TTS voices from the calling service.
     *
     * @return array  Each item: ['voice_id', 'name', 'preview_url', 'category']
     * @throws \RuntimeException on failure
     */
    public function getVoices(): array
    {
        $response = $this->client()->timeout(15)->get('/api/voices');

        if (!$response->successful()) {
            throw new \RuntimeException($this->formatHttpError(
                'Could not fetch voices from calling service',
                $response->status(),
                $response->body()
            ));
        }

        return $response->json();
    }

    /**
     * Stream a TTS voice preview from the calling service.
     * Returns the raw Symfony StreamedResponse from the calling service.
     */
    public function streamVoicePreview(
        string $voiceId,
        string $text,
        float  $stability,
        float  $similarityBoost,
        float  $speed
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        $response = $this->client()
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->timeout(30)
            ->withOptions(['stream' => true])
            ->get('/api/voice-preview', [
                'voice_id'         => $voiceId,
                'text'             => $text,
                'stability'        => $stability,
                'similarity_boost' => $similarityBoost,
                'speed'            => $speed,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException($this->formatHttpError(
                'Audio preview failed from calling service',
                $response->status(),
                $response->body()
            ));
        }

        $body = $response->toPsrResponse()->getBody();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(4096);
                flush();
            }
        }, 200, [
            'Content-Type'      => 'audio/mpeg',
            'Cache-Control'     => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function formatHttpError(string $prefix, int $status, ?string $body = null): string
    {
        $snippet = trim((string) $body);

        if ($snippet === '') {
            return "{$prefix} (HTTP {$status}).";
        }

        return "{$prefix} (HTTP {$status}): " . Str::limit($snippet, 300);
    }

    /**
     * Push a caller (and its service points) to the calling service.
     * Caller must be loaded with its servicePoints relation.
     */
    public function syncCaller(Caller $caller)
    {
        if (!$this->baseUrl) return;

        try {
            $caller->loadMissing('servicePoints');

            $this->client()->post('/api/v1/sync/callers', [
                'kashtre_id'           => $caller->id,
                'business_id'          => $caller->business_id,
                'name'                 => $caller->name,
                'status'               => $caller->status,
                'display_token'        => $caller->display_token,
                'announcement_message' => $caller->announcement_message,
                'speech_rate'          => $caller->speech_rate ?? 1.0,
                'speech_volume'        => $caller->speech_volume ?? 1.0,
                'service_points'       => $caller->servicePoints->map(fn ($sp) => [
                    'kashtre_id' => $sp->id,
                    'name'       => $sp->name,
                ])->values()->all(),
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::syncCaller failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove a caller from the calling service by its kashtre ID.
     */
    public function deleteCaller(int $kashtre_id)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->delete("/api/v1/sync/callers/{$kashtre_id}");
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::deleteCaller failed: ' . $e->getMessage());
        }
    }

    /**
     * Push TTS/voice configuration for a business to the calling service.
     */
    public function syncVoiceConfig(CallingModuleConfig $config)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/voice-config', [
                'business_id'               => $config->business_id,
                'tts_voice_id'              => $config->tts_voice_id,
                'tts_voice_name'            => $config->tts_voice_name,
                'tts_stability'             => $config->tts_stability,
                'tts_similarity_boost'      => $config->tts_similarity_boost,
                'tts_speed'                 => $config->tts_speed,
                'announcement_message'      => $config->announcement_message,
                'is_active'                 => (bool) $config->is_active,
                'emergency_repeat_count'    => $config->emergency_repeat_count ?? 3,
                'emergency_repeat_interval' => $config->emergency_repeat_interval ?? 5,
                'emergency_display_duration'       => $config->emergency_display_duration ?? 0,
                'emergency_flash_frequency'        => $config->emergency_flash_frequency ?? 2,
                'emergency_flash_on'               => $config->emergency_flash_on  ?? 3,
                'emergency_flash_off'              => $config->emergency_flash_off ?? 1,
                'emergency_tts_voice_id'           => $config->emergency_tts_voice_id,
                'emergency_tts_voice_name'         => $config->emergency_tts_voice_name,
                'emergency_tts_stability'          => $config->emergency_tts_stability ?? 0.5,
                'emergency_tts_similarity_boost'   => $config->emergency_tts_similarity_boost ?? 0.75,
                'emergency_tts_speed'              => $config->emergency_tts_speed ?? 1.0,
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::syncVoiceConfig failed: ' . $e->getMessage());
        }
    }

    public function syncEmergency(EmergencyAlert $alert)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/emergency', [
                'business_id'        => $alert->business_id,
                'service_point_name' => $alert->service_point_name,
                'room_name'          => $alert->room_name,
                'message'            => $alert->message,
                'display_message'    => $alert->display_message ?? null,
                'color'              => $alert->color ?? 'red',
                'triggered_at'       => $alert->triggered_at->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::syncEmergency failed: ' . $e->getMessage());
        }
    }

    public function resolveEmergency(int $businessId)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/emergency/resolve', [
                'business_id' => $businessId,
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::resolveEmergency failed: ' . $e->getMessage());
        }
    }

    public function broadcastAnnouncement(int $businessId, string $message, string $type)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/announcement', [
                'business_id'  => $businessId,
                'message'      => $message,
                'type'         => $type,
                'triggered_at' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::broadcastAnnouncement failed: ' . $e->getMessage());
        }
    }

    public function resolveAnnouncement(int $businessId)
    {
        if (!$this->baseUrl) return;

        try {
            $this->client()->post('/api/v1/sync/announcement/resolve', [
                'business_id' => $businessId,
            ]);
        } catch (\Exception $e) {
            Log::error('CallingServiceClient::resolveAnnouncement failed: ' . $e->getMessage());
        }
    }
}
