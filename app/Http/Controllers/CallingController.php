<?php

namespace App\Http\Controllers;

use App\Models\Caller;
use App\Models\CallerLog;
use App\Models\CallingModuleConfig;
use App\Models\PaSection;
use App\Models\ServiceDeliveryQueue;
use App\Models\ServicePoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CallingController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id === 1) {
                abort(403, 'This page is for business staff only.');
            }

            $enabled = CallingModuleConfig::where('business_id', auth()->user()->business_id)
                ->where('is_active', true)
                ->exists();

            if (!$enabled) {
                abort(403, 'The calling module is not enabled for your organisation.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $user       = auth()->user();
        $businessId = $user->business_id;
        $callerId   = session('caller_id');

        // All active callers for the selection screen
        $callers = Caller::where('business_id', $businessId)
            ->where('status', 'active')
            ->withCount('servicePoints')
            ->orderBy('name')
            ->get();

        if (!$callerId) {
            return view('calling.index', [
                'callers'       => $callers,
                'caller'        => null,
                'servicePoints' => collect(),
                'paSections'    => collect(),
            ]);
        }

        // Load the selected caller with service points + their pending queues
        $caller = Caller::where('id', $callerId)
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->with(['servicePoints' => function ($query) {
                $query->with(['pendingDeliveryQueues' => function ($q) {
                    $q->with('client')->orderBy('queued_at');
                }]);
            }])
            ->first();

        // Caller was deleted or deactivated — clear session
        if (!$caller) {
            session()->forget('caller_id');
            return redirect()->route('calling.index');
        }

        $paSections = PaSection::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('calling.index', [
            'callers'       => $callers,
            'caller'        => $caller,
            'servicePoints' => $caller->servicePoints,
            'paSections'    => $paSections,
        ]);
    }

    /**
     * Store the selected caller in session.
     */
    public function selectCaller(Request $request)
    {
        $validated = $request->validate([
            'caller_id' => 'required|integer|exists:callers,id',
        ]);

        Caller::where('id', $validated['caller_id'])
            ->where('business_id', auth()->user()->business_id)
            ->where('status', 'active')
            ->firstOrFail();

        session(['caller_id' => $validated['caller_id']]);

        return redirect()->route('calling.index');
    }

    /**
     * Clear the selected caller from session.
     */
    public function deselectCaller()
    {
        session()->forget('caller_id');
        return redirect()->route('calling.index');
    }

    /**
     * Record a client being called (called from speaker button JS).
     */
    public function announce(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'nullable|integer|exists:clients,id',
            'client_name'      => 'nullable|string|max:255',
            'service_point_id' => 'required|integer|exists:service_points,id',
            'item_name'        => 'nullable|string|max:255',
            'type'             => 'nullable|in:calling,serving,stop-serving',
        ]);

        $user         = auth()->user();
        $businessId   = $user->business_id;
        $callerId     = session('caller_id');

        $servicePoint = ServicePoint::where('id', $validated['service_point_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        $moduleConfig = CallingModuleConfig::where('business_id', $businessId)->first();
        $audioEnabled = $moduleConfig ? (bool) $moduleConfig->audio_enabled : true;
        $videoEnabled = $moduleConfig ? (bool) $moduleConfig->video_enabled : true;

        // If no caller is selected in session (e.g. called from the service point show page
        // rather than the calling module), auto-detect from the service point's linked callers.
        if (!$callerId) {
            $callerId = Caller::where('business_id', $businessId)
                ->where('status', 'active')
                ->whereHas('servicePoints', fn ($q) => $q->where('service_points.id', $servicePoint->id))
                ->value('id');
        }

        $type = $validated['type'] ?? 'calling';

        if ($type === 'serving') {
            if (!empty($validated['client_id'])) {
                $this->storeServingState(
                    $servicePoint,
                    (int) $validated['client_id'],
                    $validated['client_name'] ?? null
                );
            }
            // View Details opened — fake the queue status to the display board without updating DB
            if (!empty($validated['client_id'])) {
                $pendingQueues = ServiceDeliveryQueue::where('client_id', $validated['client_id'])
                    ->where('service_point_id', $servicePoint->id)
                    ->whereNotIn('status', ['completed', 'done'])
                    ->get();

                if (app()->bound(\App\Services\CallingServiceClient::class) || class_exists(\App\Services\CallingServiceClient::class)) {
                    $callingClient = app(\App\Services\CallingServiceClient::class);
                    foreach ($pendingQueues as $q) {
                        $callingClient->syncQueue($q, 'serving');
                    }
                }
            }
            return response()->json(['success' => true, 'log_id' => null]);
        }

        if ($type === 'stop-serving') {
            if (!empty($validated['client_id'])) {
                $this->clearServingState($servicePoint, (int) $validated['client_id']);
            }
            // View Details closed / Save & Exit — revert the fake 'serving' status on the display
            if (!empty($validated['client_id'])) {
                $pendingQueues = ServiceDeliveryQueue::where('client_id', $validated['client_id'])
                    ->where('service_point_id', $servicePoint->id)
                    ->whereNotIn('status', ['completed', 'done'])
                    ->get();

                if (app()->bound(\App\Services\CallingServiceClient::class) || class_exists(\App\Services\CallingServiceClient::class)) {
                    $callingClient = app(\App\Services\CallingServiceClient::class);
                    foreach ($pendingQueues as $q) {
                        // Pass 'pending' as forceStatus so the display removes it
                        $callingClient->syncQueue($q, 'pending');
                    }
                }
            }
            return response()->json(['success' => true, 'log_id' => null]);
        }

        // Call Next — create CallerLog so display plays audio
        $log = CallerLog::create([
            'business_id'      => $businessId,
            'caller_id'        => $callerId ?: null,
            'service_point_id' => $servicePoint->id,
            'room_id'          => session('room_id'),
            'client_id'        => $validated['client_id'] ?? null,
            'client_name'      => $validated['client_name'] ?? null,
            'item_name'        => $validated['item_name'] ?? null,
            'called_by'        => $user->id,
            'called_at'        => now(),
        ]);

        if ($callerId && ($audioEnabled || $videoEnabled) && (app()->bound(\App\Services\CallingServiceClient::class) || class_exists(\App\Services\CallingServiceClient::class))) {
            $visitId = $validated['client_name'] ?? ('Client #' . ($validated['client_id'] ?? 'Unknown'));
            app(\App\Services\CallingServiceClient::class)->triggerAnnouncement(
                $callerId,
                $log->id,
                $visitId,
                $validated['client_name'] ?? null,
                $servicePoint->name,
                optional($log->room)->name,
                $audioEnabled,
                $videoEnabled
            );
        }

        return response()->json(['success' => true, 'log_id' => $log->id]);
    }

    private function storeServingState(ServicePoint $servicePoint, int $clientId, ?string $clientName = null): void
    {
        $cacheKey = $this->servingStateCacheKey($servicePoint->id);
        $entries = Cache::get($cacheKey, []);

        $visitId = trim((string) ($clientName ?? ''));

        if ($visitId === '') {
            $client = \App\Models\Client::select('visit_id', 'name')->find($clientId);
            $visitId = $client?->visit_id ?: ($client?->name ?: ('Client #' . $clientId));
        }

        $entries[$clientId] = [
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'service_point_id' => $servicePoint->id,
            'service_point' => $servicePoint->name,
            'started_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $entries, now()->addHours(12));
    }

    private function clearServingState(ServicePoint $servicePoint, int $clientId): void
    {
        $cacheKey = $this->servingStateCacheKey($servicePoint->id);
        $entries = Cache::get($cacheKey, []);

        unset($entries[$clientId]);

        if (empty($entries)) {
            Cache::forget($cacheKey);
            return;
        }

        Cache::put($cacheKey, $entries, now()->addHours(12));
    }

    private function servingStateCacheKey(int $servicePointId): string
    {
        return "display-serving:service-point:{$servicePointId}";
    }

    /**
     * Show the call log for today (and optionally older dates).
     */
    public function log(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $date       = $request->input('date', now()->toDateString());

        $logs = CallerLog::where('business_id', $businessId)
            ->whereDate('called_at', $date)
            ->with(['caller', 'servicePoint.room', 'room', 'client', 'calledBy'])
            ->orderByDesc('called_at')
            ->get();

        return view('callers.log', compact('logs', 'date'));
    }
}
