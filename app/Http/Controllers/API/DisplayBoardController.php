<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Caller;
use App\Models\CallerLog;
use App\Models\CallingModuleConfig;
use App\Models\EmergencyAlert;
use App\Models\ServiceDeliveryQueue;
use App\Services\EmergencyAlertService;
use App\Services\CallingServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DisplayBoardController extends Controller
{
    private function corsJson(array $payload, int $status = 200)
    {
        return response()->json($payload, $status)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ]);
    }

    private function withCors($response)
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        return $response;
    }

    private function resolveCaller(?string $token): ?Caller
    {
        if (!$token) {
            return null;
        }

        return Caller::with('servicePoints')
            ->where('display_token', $token)
            ->where('status', 'active')
            ->first();
    }

    private function resolveConfig(int $businessId): ?CallingModuleConfig
    {
        return CallingModuleConfig::where('business_id', $businessId)->first();
    }

    public function latestCalls(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return $this->corsJson(['error' => 'Token required.'], 400);
        }

        $caller = $this->resolveCaller($token);

        if (!$caller) {
            return $this->corsJson(['error' => 'Invalid or inactive display token.'], 404);
        }

        $config = $this->resolveConfig($caller->business_id);
        $servicePointIds = $caller->servicePoints->pluck('id');

        $logsQuery = CallerLog::where('caller_id', $caller->id)
            ->whereDate('called_at', today())
            ->with([
                'client:id,visit_id',
                'servicePoint:id,name',
                'room:id,name',
            ]);

        $after = $request->query('after');
        if ($after !== null && is_numeric($after)) {
            $logsQuery->where('id', '>', (int) $after);
        }

        $logs = $logsQuery->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($log) => [
                'log_id' => $log->id,
                'visit_id' => $log->client?->visit_id ?: ($log->client_name ?: '—'),
                'service_point' => $log->servicePoint?->name ?: '—',
                'room' => $log->room?->name,
                'called_at' => $log->called_at?->format('H:i:s'),
                'audio_enabled' => (bool) ($config?->audio_enabled ?? true),
                'video_enabled' => (bool) ($config?->video_enabled ?? true),
            ]);

        $queue = ServiceDeliveryQueue::whereIn('service_point_id', $servicePointIds)
            ->where('status', 'pending')
            ->with([
                'client:id,visit_id',
                'servicePoint:id,name',
            ])
            ->orderBy('queued_at')
            ->get()
            ->values()
            ->map(fn ($q, $i) => [
                'position' => $i + 1,
                'visit_id' => $q->client?->visit_id ?: ('Client #' . $q->client_id),
                'service_point' => $q->servicePoint?->name ?: '—',
                'queued_at' => $q->queued_at?->format('H:i') ?: '—',
                'priority' => $q->priority ?? 'normal',
            ]);

        $nowServing = collect($servicePointIds)
            ->flatMap(function ($servicePointId) {
                $entries = Cache::get("display-serving:service-point:{$servicePointId}", []);

                return collect($entries)->map(fn ($entry) => [
                    'visit_id' => $entry['visit_id'] ?? ('Client #' . ($entry['client_id'] ?? '')),
                    'service_point' => $entry['service_point'] ?? '-',
                ])->values();
            })
            ->unique(fn ($entry) => ($entry['visit_id'] ?? '') . '|' . ($entry['service_point'] ?? ''))
            ->values();

        $activeEmergency = app(EmergencyAlertService::class)->resolveActiveAlertForBusiness($caller->business_id);

        $emergencyData = $activeEmergency ? [
            'id' => $activeEmergency->id,
            'message' => $activeEmergency->message,
            'display_message' => $activeEmergency->display_message ?: $activeEmergency->message,
            'service_point_name' => $activeEmergency->service_point_name,
            'triggered_at' => $activeEmergency->triggered_at?->format('H:i:s'),
        ] : null;

        return $this->corsJson([
            'caller_name' => $caller->name,
            'logs' => $logs,
            'queue' => $queue,
            'now_serving' => $nowServing,
            'active_emergency' => $emergencyData,
            'active_announcement' => null,
            'emergency_config' => [
                'repeat_count' => $config?->emergency_repeat_count ?? 3,
                'repeat_interval' => $config?->emergency_repeat_interval ?? 5,
                'display_duration' => $config?->emergency_display_duration ?? 0,
            ],
        ]);
    }

    public function streamAudio(Request $request)
    {
        $caller = $this->resolveCaller($request->query('token'));

        if (!$caller) {
            return $this->corsJson(['error' => 'Invalid or inactive display token.'], 404);
        }

        $logId = (int) $request->query('log_id', 0);
        if ($logId < 1) {
            return $this->corsJson(['error' => 'log_id is required.'], 400);
        }

        $log = CallerLog::where('id', $logId)
            ->where('caller_id', $caller->id)
            ->with([
                'client:id,visit_id',
                'servicePoint:id,name',
                'room:id,name',
            ])
            ->first();

        if (!$log) {
            return $this->corsJson(['error' => 'Log not found.'], 404);
        }

        $config = $this->resolveConfig($caller->business_id);

        if (!$config || !$config->audio_enabled || !$config->tts_voice_id) {
            Log::warning('DisplayBoardController::streamAudio missing voice configuration', [
                'caller_id' => $caller->id,
                'business_id' => $caller->business_id,
                'audio_enabled' => (bool) ($config?->audio_enabled ?? false),
                'tts_voice_id' => $config?->tts_voice_id,
            ]);

            return $this->corsJson(['error' => 'Audio is not configured for this display.'], 422);
        }

        $visitId = $log->client?->visit_id ?: ($log->client_name ?: 'Next client');
        $destination = $log->room?->name ?: ($log->servicePoint?->name ?: 'the room');
        $template = $config->announcement_message ?: 'Now serving {name}. Please proceed to {destination}.';
        $text = str_replace(['{name}', '{destination}'], [$visitId, $destination], $template);

        try {
            return $this->withCors(app(CallingServiceClient::class)->streamVoicePreview(
                $config->tts_voice_id,
                $text,
                (float) ($config->tts_stability ?? 0.5),
                (float) ($config->tts_similarity_boost ?? 0.75),
                (float) ($config->tts_speed ?? 1.0)
            ));
        } catch (\Throwable $e) {
            Log::error('DisplayBoardController::streamAudio failed', [
                'log_id' => $logId,
                'caller_id' => $caller->id,
                'business_id' => $caller->business_id,
                'calling_service_url' => config('services.calling_service.url'),
                'tts_voice_id' => $config->tts_voice_id,
                'error' => $e->getMessage(),
            ]);

            return $this->corsJson(['error' => 'Audio generation failed.'], 502);
        }
    }

    public function streamEmergencyAudio(Request $request)
    {
        $caller = $this->resolveCaller($request->query('token'));

        if (!$caller) {
            return $this->corsJson(['error' => 'Invalid or inactive display token.'], 404);
        }

        $emergencyId = (int) $request->query('emergency_id', 0);
        if ($emergencyId < 1) {
            return $this->corsJson(['error' => 'emergency_id is required.'], 400);
        }

        $alert = EmergencyAlert::where('id', $emergencyId)
            ->where('business_id', $caller->business_id)
            ->first();

        if (!$alert) {
            return $this->corsJson(['error' => 'Emergency alert not found.'], 404);
        }

        $config = $this->resolveConfig($caller->business_id);
        $voiceId = $config?->emergency_tts_voice_id ?: $config?->tts_voice_id;

        if (!$config || !$voiceId) {
            return $this->corsJson(['error' => 'Emergency audio is not configured for this display.'], 422);
        }

        $destination = $alert->room_name ?: ($alert->service_point_name ?: 'the area');
        $text = str_replace('{destination}', $destination, $alert->message);

        try {
            return $this->withCors(app(CallingServiceClient::class)->streamVoicePreview(
                $voiceId,
                $text,
                (float) ($config->emergency_tts_stability ?? $config->tts_stability ?? 0.5),
                (float) ($config->emergency_tts_similarity_boost ?? $config->tts_similarity_boost ?? 0.75),
                (float) ($config->emergency_tts_speed ?? $config->tts_speed ?? 1.0)
            ));
        } catch (\Throwable $e) {
            Log::error('DisplayBoardController::streamEmergencyAudio failed', [
                'emergency_id' => $emergencyId,
                'error' => $e->getMessage(),
            ]);

            return $this->corsJson(['error' => 'Audio generation failed.'], 502);
        }
    }

    public function streamAnnouncementAudio()
    {
        return $this->corsJson(['error' => 'No active announcement audio source.'], 404);
    }
}
