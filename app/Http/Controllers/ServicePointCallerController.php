<?php

namespace App\Http\Controllers;

use App\Models\Caller;
use App\Models\CallingModuleConfig;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\CallingServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServicePointCallerController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id === 1) {
                abort(403, 'Access denied. Caller management is for business users only.');
            }

            $enabled = CallingModuleConfig::where('business_id', auth()->user()->business_id)
                ->where('is_active', true)
                ->exists();

            if (!$enabled) {
                abort(403, 'The calling module is not enabled for your organisation.');
            }

            $callerPerms = ['View Callers', 'Add Callers', 'Edit Callers', 'Manage Callers'];
            if (!array_intersect($callerPerms, auth()->user()->permissions ?? [])) {
                abort(403, 'Access denied. You do not have permission to access callers.');
            }

            return $next($request);
        });
    }

    private function hasPerm(string ...$perms): bool
    {
        $userPerms = auth()->user()->permissions ?? [];
        foreach ($perms as $perm) {
            if (in_array($perm, $userPerms)) return true;
        }
        return false;
    }

    /**
     * List all named callers with their attached service points and pending queues.
     */
    public function index()
    {
        $businessId = auth()->user()->business_id;

        $callers = Caller::where('business_id', $businessId)
            ->with('servicePoints')
            ->orderBy('name')
            ->get();

        $allServicePoints = ServicePoint::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('settings.callers.index', compact('callers', 'allServicePoints'));
    }

    /**
     * Voice / TTS settings page for this organisation.
     */
    public function callSettingsIndex()
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to access voice settings.');
        }

        $businessId = auth()->user()->business_id;
        $config     = CallingModuleConfig::where('business_id', $businessId)->first();

        return view('settings.callers.call-settings', compact('config'));
    }

    /**
     * Emergency settings page for this organisation.
     */
    public function emergencySettingsIndex()
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to access emergency settings.');
        }

        $businessId = auth()->user()->business_id;
        $config     = CallingModuleConfig::where('business_id', $businessId)->first();

        return view('settings.callers.emergency-settings', compact('config'));
    }

    /**
     * Save emergency settings (audio message, display message, repeat, buttons).
     */
    public function saveEmergencySettings(Request $request)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to save emergency settings.');
        }

        $businessId = auth()->user()->business_id;

        $validated = $request->validate([
            'emergency_display_duration' => 'nullable|integer|min:0|max:3600',
            'emergency_flash_frequency'  => 'nullable|integer|min:1|max:60',
            'emergency_flash_on'         => 'nullable|integer|min:1|max:60',
            'emergency_flash_off'        => 'nullable|integer|min:1|max:60',
            'emergency_repeat_count'     => 'nullable|integer|min:1|max:10',
            'emergency_repeat_interval'  => 'nullable|integer|min:0|max:60',
            'emergency_button_1_name'            => 'nullable|string|max:30',
            'emergency_button_1_message'         => 'nullable|string|max:500',
            'emergency_button_1_display_message' => 'nullable|string|max:500',
            'emergency_button_1_color'           => 'nullable|in:red,green,yellow,amber,blue',
            'emergency_button_2_name'            => 'nullable|string|max:30',
            'emergency_button_2_message'         => 'nullable|string|max:500',
            'emergency_button_2_display_message' => 'nullable|string|max:500',
            'emergency_button_2_color'           => 'nullable|in:red,green,yellow,amber,blue',
            'emergency_tts_voice_id'          => 'nullable|string|max:255',
            'emergency_tts_voice_name'        => 'nullable|string|max:255',
            'emergency_tts_stability'         => 'nullable|numeric|min:0|max:1',
            'emergency_tts_similarity_boost'  => 'nullable|numeric|min:0|max:1',
            'emergency_tts_speed'             => 'nullable|numeric|min:0.7|max:1.2',
        ]);

        CallingModuleConfig::where('business_id', $businessId)->update(
            array_merge($validated, ['updated_by' => auth()->id()])
        );

        $config = CallingModuleConfig::where('business_id', $businessId)->first();
        app(CallingServiceClient::class)->syncVoiceConfig($config);

        return back()->with('success', 'Emergency settings saved.');
    }

    /**
     * Save TTS voice settings for this organisation.
     */
    public function saveGlobalCallSettings(Request $request)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to save voice settings.');
        }

        $businessId = auth()->user()->business_id;

        $validated = $request->validate([
            'tts_voice_id'         => 'nullable|string|max:255',
            'tts_voice_name'       => 'nullable|string|max:255',
            'tts_stability'        => 'required|numeric|min:0|max:1',
            'tts_similarity_boost' => 'required|numeric|min:0|max:1',
            'tts_speed'            => 'required|numeric|min:0.7|max:1.2',
            'announcement_message' => 'nullable|string|max:500',
        ]);

        CallingModuleConfig::where('business_id', $businessId)->update(
            array_merge($validated, ['updated_by' => auth()->id()])
        );

        $config = CallingModuleConfig::where('business_id', $businessId)->first();
        app(CallingServiceClient::class)->syncVoiceConfig($config);

        return back()->with('success', 'Voice settings saved.');
    }

    /**
     * Return available TTS voices as JSON (proxied through the calling service).
     */
    public function getVoices()
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403);
        }

        try {
            $voices = app(CallingServiceClient::class)->getVoices();
            return response()->json($voices);
        } catch (\Throwable $e) {
            Log::error('getVoices proxy failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not fetch voices from the TTS provider.'], 502);
        }
    }

    /**
     * Stream a TTS voice preview (proxied through the calling service).
     */
    public function previewVoice(Request $request)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403);
        }

        $voiceId         = $request->query('voice_id');
        $text            = $request->query('text', 'Hello. This is a preview of the selected voice.');
        $stability       = (float) $request->query('stability', 0.5);
        $similarityBoost = (float) $request->query('similarity_boost', 0.75);
        $speed           = (float) $request->query('speed', 1.0);

        if (!$voiceId) {
            abort(400, 'voice_id is required.');
        }

        try {
            return app(CallingServiceClient::class)->streamVoicePreview(
                $voiceId, $text, $stability, $similarityBoost, $speed
            );
        } catch (\Throwable $e) {
            Log::error('previewVoice proxy failed', ['error' => $e->getMessage()]);
            abort(502, 'Audio preview failed.');
        }
    }

    /**
     * P2P call settings — list of all staff users with online presence.
     */
    public function p2pSettingsIndex()
    {
        $businessId = auth()->user()->business_id;

        $users = User::where('business_id', $businessId)
            ->where('id', '!=', auth()->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('settings.callers.p2p-settings', compact('users'));
    }

    /**
     * Save current user's personal P2P settings (name override, ringtone).
     */
    public function saveP2pSettings(Request $request)
    {
        $validated = $request->validate([
            'p2p_display_name' => 'nullable|string|max:255',
            'p2p_ringtone'     => 'required|in:default,rapid,deep,urgent',
        ]);

        auth()->user()->update($validated);

        return back()->with('success', 'Your personal P2P calling settings have been updated.');
    }

    /**
     * Form to create a new named caller.
     */
    public function create()
    {
        if (!$this->hasPerm('Add Callers', 'Manage Callers')) {
            abort(403, 'You need Add Callers or Manage Callers to create a caller.');
        }

        $businessId = auth()->user()->business_id;

        $servicePoints = ServicePoint::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('settings.callers.create', compact('servicePoints'));
    }

    /**
     * Create the caller and attach the selected service points.
     */
    public function store(Request $request)
    {
        if (!$this->hasPerm('Add Callers', 'Manage Callers')) {
            abort(403, 'You need Add Callers or Manage Callers to create a caller.');
        }

        $businessId = auth()->user()->business_id;

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'service_point_ids'   => 'required|array|min:1',
            'service_point_ids.*' => 'exists:service_points,id',
        ]);

        $caller = Caller::create([
            'name'        => $validated['name'],
            'business_id' => $businessId,
            'status'      => 'active',
        ]);

        $validIds = ServicePoint::whereIn('id', $validated['service_point_ids'])
            ->where('business_id', $businessId)
            ->pluck('id');

        $caller->servicePoints()->sync($validIds);

        app(CallingServiceClient::class)->syncCaller($caller->load('servicePoints'));

        return redirect()->route('service-point-callers.index')
            ->with('success', "Caller \"{$caller->name}\" created with {$validIds->count()} service point(s).");
    }

    /**
     * Form to edit (or view) a caller's details and display token.
     */
    public function edit(Caller $caller)
    {
        if (!$this->hasPerm('View Callers', 'Edit Callers', 'Manage Callers')) {
            abort(403, 'You need View Callers, Edit Callers, or Manage Callers to access this page.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $businessId = auth()->user()->business_id;

        $servicePoints = ServicePoint::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $attachedIds = $caller->servicePoints()->pluck('service_points.id')->toArray();

        $canEdit   = $this->hasPerm('Edit Callers', 'Manage Callers');
        $canManage = $this->hasPerm('Manage Callers');

        return view('settings.callers.edit', compact('caller', 'servicePoints', 'attachedIds', 'canEdit', 'canManage'));
    }

    /**
     * Update the caller's name and re-sync its service points.
     */
    public function update(Request $request, Caller $caller)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to update a caller.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $businessId = auth()->user()->business_id;

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'service_point_ids'   => 'required|array|min:1',
            'service_point_ids.*' => 'exists:service_points,id',
        ]);

        $caller->update(['name' => $validated['name']]);

        $validIds = ServicePoint::whereIn('id', $validated['service_point_ids'])
            ->where('business_id', $businessId)
            ->pluck('id');

        $caller->servicePoints()->sync($validIds);

        app(CallingServiceClient::class)->syncCaller($caller->load('servicePoints'));

        return redirect()->route('service-point-callers.index')
            ->with('success', "Caller \"{$caller->name}\" updated.");
    }

    /**
     * Remove a single service point from a caller.
     */
    public function removeServicePoint(Caller $caller, ServicePoint $servicePoint)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to modify a caller.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $caller->servicePoints()->detach($servicePoint->id);

        app(CallingServiceClient::class)->syncCaller($caller->load('servicePoints'));

        return redirect()->route('service-point-callers.index')
            ->with('success', "\"{$servicePoint->name}\" removed from {$caller->name}.");
    }

    /**
     * Update call announcement settings for a caller station.
     */
    public function updateCallSettings(Request $request, Caller $caller)
    {
        if (!$this->hasPerm('Edit Callers', 'Manage Callers')) {
            abort(403, 'You need Edit Callers or Manage Callers to update call settings.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $validated = $request->validate([
            'announcement_message' => 'nullable|string|max:500',
            'speech_rate'          => 'required|numeric|min:0.5|max:2',
            'speech_volume'        => 'required|numeric|min:0|max:1',
        ]);

        $caller->update($validated);

        app(CallingServiceClient::class)->syncCaller($caller->load('servicePoints'));

        return back()->with('success', 'Call settings saved for "' . $caller->name . '".');
    }

    /**
     * Generate (or regenerate) a display token for a caller station.
     */
    public function generateToken(Caller $caller)
    {
        if (!$this->hasPerm('Manage Callers')) {
            abort(403, 'You need Manage Callers to generate a display token.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $caller->update(['display_token' => str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT)]);

        app(CallingServiceClient::class)->syncCaller($caller->load('servicePoints'));

        return back()->with('success', 'Display token generated for "' . $caller->name . '".');
    }

    /**
     * Delete a caller entirely.
     */
    public function destroy(Caller $caller)
    {
        if (!$this->hasPerm('Manage Callers')) {
            abort(403, 'You need Manage Callers to delete a caller.');
        }

        if ($caller->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $name       = $caller->name;
        $kashtre_id = $caller->id;
        $caller->servicePoints()->detach();
        $caller->delete();

        app(CallingServiceClient::class)->deleteCaller($kashtre_id);

        return redirect()->route('service-point-callers.index')
            ->with('success', "Caller \"{$name}\" deleted.");
    }

}
