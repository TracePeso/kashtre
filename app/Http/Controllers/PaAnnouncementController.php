<?php

namespace App\Http\Controllers;

use App\Events\PaAnnouncementStarted;
use App\Events\PaAnnouncementStopped;
use App\Events\PaAudioChunk;
use App\Events\PaWebRtcSignalToCaller;
use App\Events\PaWebRtcSignalToUser;
use App\Models\Caller;
use App\Models\CallingModuleConfig;
use App\Models\PaSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaAnnouncementController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $publicMethods = ['displayPaConfig', 'displaySignal', 'displayPaStream'];
            $method = $request->route()?->getActionMethod();

            if (in_array($method, $publicMethods, true)) {
                return $next($request);
            }

            if (auth()->user()->business_id === 1) {
                abort(403, 'PA management is for business users only.');
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
        $businessId = auth()->user()->business_id;

        $sections = PaSection::where('business_id', $businessId)
            ->with('callers')
            ->orderBy('name')
            ->get();

        $callers = Caller::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('settings.callers.pa-sections', compact('sections', 'callers'));
    }

    public function console()
    {
        $this->requireBroadcastAnnouncements();

        $businessId = auth()->user()->business_id;

        $sections = PaSection::where('business_id', $businessId)
            ->with(['callers' => function ($query) {
                $query->where('status', 'active')->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        return view('calling.public-address', compact('sections'));
    }

    public function store(Request $request)
    {
        $this->requireManage();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'caller_ids' => 'nullable|array',
            'caller_ids.*' => 'exists:callers,id',
        ]);

        $businessId = auth()->user()->business_id;

        $section = PaSection::create([
            'name' => $validated['name'],
            'business_id' => $businessId,
        ]);

        if (!empty($validated['caller_ids'])) {
            $validIds = Caller::whereIn('id', $validated['caller_ids'])
                ->where('business_id', $businessId)
                ->pluck('id');
            $section->callers()->sync($validIds);
        }

        return back()->with('success', "PA section \"{$section->name}\" created.");
    }

    public function update(Request $request, PaSection $paSection)
    {
        $this->requireManage();
        $this->authorise($paSection);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'caller_ids' => 'nullable|array',
            'caller_ids.*' => 'exists:callers,id',
        ]);

        $businessId = auth()->user()->business_id;

        $paSection->update(['name' => $validated['name']]);

        $validIds = Caller::whereIn('id', $validated['caller_ids'] ?? [])
            ->where('business_id', $businessId)
            ->pluck('id');
        $paSection->callers()->sync($validIds);

        return back()->with('success', "PA section \"{$paSection->name}\" updated.");
    }

    public function destroy(PaSection $paSection)
    {
        $this->requireManage();
        $this->authorise($paSection);

        $name = $paSection->name;
        $paSection->callers()->detach();
        $paSection->delete();

        return back()->with('success', "PA section \"{$name}\" deleted.");
    }

    public function start(Request $request)
    {
        $this->requireBroadcastAnnouncements();

        $request->validate([
            'section_id' => 'required|integer|exists:pa_sections,id',
        ]);

        $user = auth()->user();
        $businessId = $user->business_id;
        $section = PaSection::where('id', $request->section_id)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $activeCallers = $section->callers()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['callers.id', 'callers.name']);

        if ($activeCallers->isEmpty()) {
            return response()->json([
                'error' => 'This section has no active caller stations assigned.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lockKey = $this->lockKey($businessId);
        $active = Cache::get($lockKey);

        if ($active) {
            if ($active['user_id'] !== $user->id) {
                return response()->json([
                    'error' => 'PA is currently in use.',
                    'announcer_name' => $active['user_name'],
                    'section_name' => $active['section_name'],
                ], Response::HTTP_CONFLICT);
            }

            if (($active['section_id'] ?? null) !== $section->id) {
                return response()->json([
                    'error' => 'Stop the current announcement before switching sections.',
                ], Response::HTTP_CONFLICT);
            }
        }

        $sessionId = $active['session_id'] ?? (string) Str::uuid();

        Cache::put(
            $lockKey,
            $this->activePayload(
                $user->id,
                $user->name,
                $section->id,
                $section->name,
                $sessionId,
                $activeCallers->pluck('id')->map(fn ($id) => (int) $id)->all()
            ),
            now()->addSeconds(75)
        );

        Cache::put(
            $this->streamKey($businessId, $section->id),
            [
                'session_id' => $sessionId,
                'next_id' => 1,
                'items' => [],
            ],
            now()->addMinutes(5)
        );

        try {
            broadcast(new PaAnnouncementStarted(
                $businessId,
                $section->id,
                $section->name,
                $user->name,
            ));
        } catch (\Throwable $e) {
            Cache::forget($lockKey);

            Log::error('PA start broadcast failed', [
                'business_id' => $businessId,
                'section_id' => $section->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Realtime PA service is unavailable. Start the Reverb server and try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        Log::info('PA session started', [
            'business_id' => $businessId,
            'section_id' => $section->id,
            'session_id' => $sessionId,
            'caller_ids' => $activeCallers->pluck('id')->values()->all(),
            'user_id' => $user->id,
        ]);

        return response()->json([
            'status' => 'started',
            'section_id' => $section->id,
            'session_id' => $sessionId,
            'target_callers' => $activeCallers->map(fn ($caller) => [
                'id' => $caller->id,
                'name' => $caller->name,
            ])->values(),
        ]);
    }

    public function stop(Request $request)
    {
        $this->requireBroadcastAnnouncements();

        $user = auth()->user();
        $businessId = $user->business_id;
        $lockKey = $this->lockKey($businessId);
        $active = Cache::get($lockKey);

        if (!$active) {
            return response()->json(['status' => 'idle']);
        }

        if ($active['user_id'] !== $user->id && !$this->userHasPermission('Manage Callers')) {
            return response()->json([
                'error' => 'Not authorised to stop this announcement.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            foreach ($active['caller_ids'] ?? [] as $callerId) {
                broadcast(new PaWebRtcSignalToCaller(
                    (int) $callerId,
                    $active['session_id'],
                    'stop',
                    null,
                    $active['section_id'],
                    $active['section_name'],
                ));
            }

            Cache::forget($lockKey);
            Cache::forget($this->streamKey($businessId, (int) $active['section_id']));

            broadcast(new PaAnnouncementStopped($businessId, $active['section_id']));
        } catch (\Throwable $e) {
            Log::error('PA stop broadcast failed', [
                'business_id' => $businessId,
                'section_id' => $active['section_id'] ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Realtime PA service is unavailable. Start the Reverb server and try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['status' => 'stopped']);
    }

    public function chunk(Request $request)
    {
        $this->requireBroadcastAnnouncements();

        $request->validate([
            'chunk' => 'required|string',
            'is_init' => 'required|boolean',
            'mime_type' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $businessId = $user->business_id;
        $lockKey = $this->lockKey($businessId);
        $active = Cache::get($lockKey);

        if (!$active || $active['user_id'] !== $user->id) {
            return response()->json([
                'error' => 'No active PA session for this user.',
            ], Response::HTTP_FORBIDDEN);
        }

        Cache::put(
            $lockKey,
            $this->activePayload(
                $user->id,
                $user->name,
                $active['section_id'],
                $active['section_name'],
                $active['session_id'],
                $active['caller_ids'] ?? []
            ),
            now()->addSeconds(75)
        );

        $streamKey = $this->streamKey($businessId, (int) $active['section_id']);
        $streamState = Cache::get($streamKey, [
            'session_id' => $active['session_id'],
            'next_id' => 1,
            'items' => [],
        ]);

        if (($streamState['session_id'] ?? null) !== $active['session_id']) {
            $streamState = [
                'session_id' => $active['session_id'],
                'next_id' => 1,
                'items' => [],
            ];
        }

        $nextId = (int) ($streamState['next_id'] ?? 1);
        $items = (array) ($streamState['items'] ?? []);
        $items[] = [
            'id' => $nextId,
            'chunk' => $request->chunk,
            'is_init' => (bool) $request->is_init,
            'mime_type' => $request->input('mime_type') ?: 'audio/webm',
        ];

        if (count($items) > 60) {
            $items = array_slice($items, -60);
        }

        Cache::put($streamKey, [
            'session_id' => $active['session_id'],
            'next_id' => $nextId + 1,
            'items' => $items,
        ], now()->addMinutes(5));

        try {
            broadcast(new PaAudioChunk(
                $businessId,
                (int) $active['section_id'],
                $request->chunk,
                (bool) $request->is_init,
                $request->input('mime_type') ?: 'audio/webm',
            ));
        } catch (\Throwable $e) {
            Log::error('PA chunk broadcast failed', [
                'business_id' => $businessId,
                'section_id' => $active['section_id'] ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Realtime PA service is unavailable. Start the Reverb server and try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['status' => 'ok']);
    }

    public function status()
    {
        $this->requireBroadcastAnnouncements();

        $lockKey = $this->lockKey(auth()->user()->business_id);
        $active = Cache::get($lockKey);

        return response()->json([
            'active' => (bool) $active,
            'announcer_name' => $active['user_name'] ?? null,
            'section_id' => $active['section_id'] ?? null,
            'section_name' => $active['section_name'] ?? null,
            'session_id' => $active['session_id'] ?? null,
            'is_mine' => $active ? $active['user_id'] === auth()->id() : false,
        ]);
    }

    public function signalToCaller(Request $request)
    {
        $this->requireBroadcastAnnouncements();

        $validated = $request->validate([
            'caller_id' => 'required|integer|exists:callers,id',
            'session_id' => 'required|string',
            'type' => 'required|in:offer,candidate',
            'data' => 'required|array',
        ]);

        $active = Cache::get($this->lockKey(auth()->user()->business_id));
        if (!$active || $active['user_id'] !== auth()->id()) {
            return response()->json(['error' => 'No active PA session for this user.'], Response::HTTP_FORBIDDEN);
        }

        if ($validated['session_id'] !== ($active['session_id'] ?? null)) {
            return response()->json(['error' => 'PA session is no longer active.'], Response::HTTP_CONFLICT);
        }

        if (!in_array((int) $validated['caller_id'], $active['caller_ids'] ?? [], true)) {
            return response()->json(['error' => 'Caller station is not part of this announcement section.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            broadcast(new PaWebRtcSignalToCaller(
                (int) $validated['caller_id'],
                $active['session_id'],
                $validated['type'],
                $validated['data'],
                $active['section_id'],
                $active['section_name'],
            ));
        } catch (\Throwable $e) {
            Log::error('PA signal-to-caller broadcast failed', [
                'caller_id' => (int) $validated['caller_id'],
                'user_id' => auth()->id(),
                'session_id' => $active['session_id'] ?? null,
                'type' => $validated['type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Realtime PA service is unavailable. Start the Reverb server and try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        Log::info('PA signal sent to caller station', [
            'user_id' => auth()->id(),
            'caller_id' => (int) $validated['caller_id'],
            'session_id' => $active['session_id'],
            'type' => $validated['type'],
        ]);

        return response()->json(['status' => 'sent']);
    }

    public function displaySignal(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'session_id' => 'required|string',
            'type' => 'required|in:answer,candidate',
            'data' => 'required|array',
        ]);

        $caller = Caller::where('display_token', $validated['token'])
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid or inactive display token.'], Response::HTTP_UNAUTHORIZED);
        }

        $active = Cache::get($this->lockKey($caller->business_id));
        if (!$active) {
            return response()->json(['error' => 'No active PA session.'], Response::HTTP_CONFLICT);
        }

        if ($validated['session_id'] !== ($active['session_id'] ?? null)) {
            return response()->json(['error' => 'PA session is no longer active.'], Response::HTTP_CONFLICT);
        }

        if (!in_array((int) $caller->id, $active['caller_ids'] ?? [], true)) {
            return response()->json(['error' => 'Caller station is not part of this announcement section.'], Response::HTTP_FORBIDDEN);
        }

        Cache::put(
            $this->lockKey($caller->business_id),
            $this->activePayload(
                $active['user_id'],
                $active['user_name'],
                $active['section_id'],
                $active['section_name'],
                $active['session_id'],
                $active['caller_ids'] ?? []
            ),
            now()->addSeconds(75)
        );

        try {
            broadcast(new PaWebRtcSignalToUser(
                (int) $active['user_id'],
                (int) $caller->id,
                $active['session_id'],
                $validated['type'],
                $validated['data'],
            ));
        } catch (\Throwable $e) {
            Log::error('PA signal-to-user broadcast failed', [
                'caller_id' => (int) $caller->id,
                'user_id' => (int) $active['user_id'],
                'session_id' => $active['session_id'] ?? null,
                'type' => $validated['type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Realtime PA service is unavailable. Start the Reverb server and try again.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        Log::info('PA signal received from caller station', [
            'caller_id' => (int) $caller->id,
            'user_id' => (int) $active['user_id'],
            'session_id' => $active['session_id'],
            'type' => $validated['type'],
        ]);

        return response()->json(['status' => 'sent'])->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]);
    }

    public function displayPaStream(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ]);
        }

        $caller = Caller::where('display_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid token'], 401)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ]);
        }

        $sectionIds = DB::table('pa_section_callers')
            ->join('pa_sections', 'pa_sections.id', '=', 'pa_section_callers.pa_section_id')
            ->where('pa_section_callers.caller_id', $caller->id)
            ->where('pa_sections.business_id', $caller->business_id)
            ->pluck('pa_sections.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $active = Cache::get($this->lockKey($caller->business_id));

        if (!$active || !in_array((int) ($active['section_id'] ?? 0), $sectionIds, true)) {
            return response()->json([
                'active' => false,
                'session_id' => null,
                'items' => [],
            ])->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ]);
        }

        $stream = Cache::get($this->streamKey($caller->business_id, (int) $active['section_id']), [
            'session_id' => $active['session_id'],
            'next_id' => 1,
            'items' => [],
        ]);

        $after = max(0, (int) $request->query('after', 0));
        $items = array_values(array_filter(
            (array) ($stream['items'] ?? []),
            fn (array $item) => (int) ($item['id'] ?? 0) > $after
        ));

        return response()->json([
            'active' => true,
            'session_id' => $stream['session_id'] ?? $active['session_id'],
            'section_id' => (int) $active['section_id'],
            'items' => $items,
        ])->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]);
    }

    public function displayPaConfig(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $caller = \App\Models\Caller::where('display_token', $token)->first();
        if (!$caller) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $sectionIds = \DB::table('pa_section_callers')
            ->join('pa_sections', 'pa_sections.id', '=', 'pa_section_callers.pa_section_id')
            ->where('pa_section_callers.caller_id', $caller->id)
            ->where('pa_sections.business_id', $caller->business_id)
            ->pluck('pa_sections.id')
            ->toArray();

        $reverbHost = $this->publicReverbHost($request);

        return response()->json([
            'caller_id' => $caller->id,
            'business_id' => $caller->business_id,
            'section_ids' => $sectionIds,
            'reverb_host' => $reverbHost,
            'reverb_port' => config('broadcasting.connections.reverb.options.port', 443),
            'reverb_scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
            'reverb_use_tls' => (bool) config('broadcasting.connections.reverb.options.useTLS', true),
            'reverb_key' => config('broadcasting.connections.reverb.key'),
        ])->withHeaders(['Access-Control-Allow-Origin' => '*']);
    }

    private function requireManage(): void
    {
        if (!$this->userHasPermission('Manage Callers')) {
            abort(403, 'You need Manage Callers to manage PA sections.');
        }
    }

    private function requireBroadcastAnnouncements(): void
    {
        if (!$this->userHasPermission('Broadcast Announcements')) {
            abort(403, 'You need Broadcast Announcements permission to make public announcements.');
        }
    }

    private function authorise(PaSection $section): void
    {
        if ($section->business_id !== auth()->user()->business_id) {
            abort(403);
        }
    }

    private function userHasPermission(string $permission): bool
    {
        return in_array($permission, $this->userPermissions(), true);
    }

    private function userPermissions(): array
    {
        return (array) (auth()->user()->permissions ?? []);
    }

    private function lockKey(int $businessId): string
    {
        return "pa_active_{$businessId}";
    }

    private function streamKey(int $businessId, int $sectionId): string
    {
        return "pa_stream_{$businessId}_{$sectionId}";
    }

    private function publicReverbHost(Request $request): string
    {
        $configuredHost = config('broadcasting.connections.reverb.options.host')
            ?: config('reverb.apps.apps.0.options.host')
            ?: config('reverb.servers.reverb.hostname')
            ?: config('reverb.servers.reverb.host');

        $requestHost = $request->getHost();

        if (!$configuredHost) {
            return $requestHost;
        }

        $localHosts = ['localhost', '127.0.0.1', '0.0.0.0'];

        if (in_array($configuredHost, $localHosts, true) && !in_array($requestHost, $localHosts, true)) {
            return $requestHost;
        }

        return $configuredHost;
    }

    private function activePayload(
        int $userId,
        string $userName,
        int $sectionId,
        string $sectionName,
        string $sessionId,
        array $callerIds
    ): array
    {
        return [
            'user_id' => $userId,
            'user_name' => $userName,
            'section_id' => $sectionId,
            'section_name' => $sectionName,
            'session_id' => $sessionId,
            'caller_ids' => array_values(array_map('intval', $callerIds)),
        ];
    }
}
