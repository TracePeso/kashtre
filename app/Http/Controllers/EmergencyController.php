<?php

namespace App\Http\Controllers;

use App\Models\Caller;
use App\Models\CallingModuleConfig;
use App\Models\EmergencyAlert;
use App\Models\Room;
use App\Models\ServicePoint;
use App\Services\EmergencyAlertService;
use App\Services\CallingServiceClient;
use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    private function resolveRoomContext(?ServicePoint $servicePoint = null): array
    {
        $roomId = session('room_id');
        $room = $roomId ? Room::find($roomId) : null;

        return [
            'room_name' => $room?->name,
        ];
    }

    private function requireEmergencyPerm(): void
    {
        // No permission check — all users can trigger/resolve emergencies
    }

    /**
     * Replace {destination} with the room the user is currently signed into.
     * Falls back to the service point name if no room is found.
     */
    private function resolvePlaceholders(string $text, ServicePoint $servicePoint): string
    {
        $roomName = null;

        $roomId = session('room_id');
        if ($roomId) {
            $room     = Room::find($roomId);
            $roomName = $room?->name;
        }

        return str_replace('{destination}', $roomName ?? $servicePoint->name, $text);
    }

    /**
     * Estimate how many seconds an announcement will take to fully complete,
     * including all configured repeats. Used to schedule queued emergencies.
     */
    private function estimateAnnounceDuration(EmergencyAlert $alert, CallingModuleConfig $config): int
    {
        $repeatCount    = max(1, (int) ($config->emergency_repeat_count ?? 1));
        $repeatInterval = max(0, (int) ($config->emergency_repeat_interval ?? 5));

        // Rough TTS estimate: ~3 characters per second at normal speed
        // Use emergency TTS speed if configured, fall back to regular speed
        $ttsSpeed       = max(0.5, (float) ($config->emergency_tts_speed ?? $config->tts_speed ?? 1.0));
        $audioSeconds   = (int) ceil(strlen($alert->message) / (3 * $ttsSpeed));
        $audioSeconds   = max(5, $audioSeconds);

        return $audioSeconds * $repeatCount + $repeatInterval * max(0, $repeatCount - 1);
    }

    /**
     * Resolve the next scheduled_announce_at for a newly queued emergency,
     * given the latest pending/active alert for this business.
     */
    private function nextScheduleAt(EmergencyAlert $latest, CallingModuleConfig $config): \Carbon\Carbon
    {
        $base = $latest->scheduled_announce_at ?? $latest->activated_at ?? $latest->triggered_at ?? now();

        $resolveDelay = max(
            (int) ($config->emergency_display_duration ?? 0),
            app(EmergencyAlertService::class)->estimateAnnounceDuration($latest, $config)
        );

        return \Carbon\Carbon::parse($base)->addSeconds($resolveDelay)->addSeconds(10);
    }

    public function trigger(Request $request, ServicePoint $servicePoint)
    {
        $user = auth()->user();

        $this->requireEmergencyPerm();

        if ($servicePoint->business_id !== $user->business_id) {
            abort(403);
        }

        $config = CallingModuleConfig::where('business_id', $user->business_id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['error' => 'Calling module is not enabled for your organisation.'], 422);
        }

        $message = trim($request->input('message', ''));
        if ($message === '') {
            return response()->json(['error' => 'Emergency message is required.'], 422);
        }
        $message = $this->resolvePlaceholders($message, $servicePoint);

        $displayMessage = $config->emergency_display_message
            ? $this->resolvePlaceholders($config->emergency_display_message, $servicePoint)
            : null;
        $roomContext = $this->resolveRoomContext($servicePoint);

        // Determine button metadata from the button that was pressed
        $buttonIndex = (int) $request->input('button_index', 1);
        $color       = ($buttonIndex === 2 ? $config->emergency_button_2_color : $config->emergency_button_1_color) ?? 'red';
        $buttonName  = ($buttonIndex === 2 ? $config->emergency_button_2_name : $config->emergency_button_1_name) ?: 'Emergency';

        $latest = EmergencyAlert::where('business_id', $user->business_id)
            ->whereNull('resolved_at')
            ->orderByDesc('scheduled_announce_at')
            ->first();

        $isActive = true;
        $scheduledAt = now();

        if ($latest) {
            $isActive = false;
            $scheduledAt = $this->nextScheduleAt($latest, $config);
        }

        $alert = EmergencyAlert::create([
            'business_id'           => $user->business_id,
            'service_point_id'      => $servicePoint->id,
            'service_point_name'    => $servicePoint->name,
            'room_name'             => $roomContext['room_name'],
            'button_name'           => $buttonName,
            'message'               => $message,
            'display_message'       => $displayMessage,
            'color'                 => $color,
            'is_active'             => $isActive,
            'triggered_by'          => $user->id,
            'triggered_at'          => now(),
            'activated_at'          => $isActive ? now() : null,
            'scheduled_announce_at' => $scheduledAt,
        ]);

        if ($isActive) {
            app(CallingServiceClient::class)->syncEmergency($alert);
            app(EmergencyAlertService::class)->scheduleAutoResolve($alert, $config);
        }

        return response()->json(['success' => true, 'alert_id' => $alert->id, 'message' => $message]);
    }

    public function resolve(Request $request, ServicePoint $servicePoint)
    {
        $user = auth()->user();

        $this->requireEmergencyPerm();

        if ($servicePoint->business_id !== $user->business_id) {
            abort(403);
        }

        // Resolve ALL pending and active alerts (including queued ones)
        EmergencyAlert::where('business_id', $user->business_id)
            ->whereNull('resolved_at')
            ->update([
                'is_active'   => false,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);

        app(CallingServiceClient::class)->resolveEmergency($user->business_id);

        return response()->json(['success' => true]);
    }

    public function triggerGlobal(Request $request)
    {
        $user = auth()->user();

        $config = CallingModuleConfig::where('business_id', $user->business_id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['error' => 'Calling module is not enabled for your organisation.'], 422);
        }

        $message = trim($request->input('message', ''));
        if ($message === '') {
            return response()->json(['error' => 'Emergency message is required.'], 422);
        }

        $displayMessage = trim($request->input('display_message', '')) ?: null;
        $roomContext = $this->resolveRoomContext();

        // Resolve {destination} placeholder using session room if available
        $destination = $roomContext['room_name'];
        $message = str_replace('{destination}', $destination ?? '', $message);
        if ($displayMessage) {
            $displayMessage = str_replace('{destination}', $destination ?? '', $displayMessage);
        }

        $buttonIndex = (int) $request->input('button_index', 1);
        $color       = ($buttonIndex === 2 ? $config->emergency_button_2_color : $config->emergency_button_1_color) ?? 'red';
        $buttonName  = ($buttonIndex === 2 ? $config->emergency_button_2_name : $config->emergency_button_1_name) ?: 'Emergency';

        $latest = EmergencyAlert::where('business_id', $user->business_id)
            ->whereNull('resolved_at')
            ->orderByDesc('scheduled_announce_at')
            ->first();

        $isActive = true;
        $scheduledAt = now();

        if ($latest) {
            $isActive = false;
            $scheduledAt = $this->nextScheduleAt($latest, $config);
        }

        $alert = EmergencyAlert::create([
            'business_id'           => $user->business_id,
            'service_point_id'      => null,
            'service_point_name'    => null,
            'room_name'             => $roomContext['room_name'],
            'button_name'           => $buttonName,
            'message'               => $message,
            'display_message'       => $displayMessage,
            'color'                 => $color,
            'is_active'             => $isActive,
            'triggered_by'          => $user->id,
            'triggered_at'          => now(),
            'activated_at'          => $isActive ? now() : null,
            'scheduled_announce_at' => $scheduledAt,
        ]);

        if ($isActive) {
            app(CallingServiceClient::class)->syncEmergency($alert);
            app(EmergencyAlertService::class)->scheduleAutoResolve($alert, $config);
        }

        return response()->json(['success' => true, 'alert_id' => $alert->id, 'message' => $message]);
    }

    public function status()
    {
        $user = auth()->user();

        $config = CallingModuleConfig::where('business_id', $user->business_id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['active' => false]);
        }

        $alert = app(EmergencyAlertService::class)->resolveActiveAlertForBusiness($user->business_id);

        if (!$alert) {
            return response()->json(['active' => false]);
        }

        $activeAt = $alert->activated_at ?? $alert->triggered_at;

        return response()->json([
            'active'           => true,
            'id'               => $alert->id,
            'message'          => $alert->display_message ?: $alert->message,
            'color'            => $alert->color ?? 'red',
            'flash_on'         => $config->emergency_flash_on  ?? 3,
            'flash_off'        => $config->emergency_flash_off ?? 1,
            'activated_at'     => $activeAt?->timestamp,
            'triggered_at'     => $alert->triggered_at->timestamp,
            'display_duration' => $config->emergency_display_duration ?? 0,
        ]);
    }

    public function log(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $date       = $request->input('date', now()->toDateString());

        $alerts = EmergencyAlert::where('business_id', $businessId)
            ->whereDate('triggered_at', $date)
            ->with(['triggeredBy', 'resolvedBy', 'servicePoint.room'])
            ->orderByDesc('triggered_at')
            ->get();

        return view('emergencies.log', compact('alerts', 'date'));
    }

    /**
     * Public token-authenticated endpoint for the display board.
     * Returns the active emergency color + flash_frequency for the business
     * associated with the given 4-digit display token.
     * No session required — CORS headers added via api.php middleware.
     */
    public function displayEmergencyStatus(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['active' => false])->header('Access-Control-Allow-Origin', '*');
        }

        $caller = Caller::where('display_token', $token)->first();
        if (!$caller) {
            return response()->json(['active' => false])->header('Access-Control-Allow-Origin', '*');
        }

        $config = CallingModuleConfig::where('business_id', $caller->business_id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['active' => false])->header('Access-Control-Allow-Origin', '*');
        }

        $alert = app(EmergencyAlertService::class)->resolveActiveAlertForBusiness($caller->business_id);

        if (!$alert) {
            return response()->json(['active' => false])->header('Access-Control-Allow-Origin', '*');
        }

        $activeAt = $alert->activated_at ?? $alert->triggered_at;

        return response()->json([
            'active'       => true,
            'id'           => $alert->id,
            'color'        => $alert->color ?? 'red',
            'flash_on'     => $config->emergency_flash_on  ?? 3,
            'flash_off'    => $config->emergency_flash_off ?? 1,
            'message'      => $alert->display_message ?: $alert->message,
            'activated_at' => $activeAt?->toISOString(),
            'triggered_at' => $alert->triggered_at?->toISOString(),
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function resolveGlobal(Request $request)
    {
        $user = auth()->user();

        // Resolve ALL pending and active alerts (including queued ones)
        EmergencyAlert::where('business_id', $user->business_id)
            ->whereNull('resolved_at')
            ->update([
                'is_active'   => false,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);

        app(CallingServiceClient::class)->resolveEmergency($user->business_id);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Emergency cleared.');
    }
}
