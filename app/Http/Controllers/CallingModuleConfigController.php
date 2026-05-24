<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\CallingModuleConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallingModuleConfigController extends Controller
{
    public function __construct()
    {
        // Only Kashtre administrators (business_id = 1) with proper permissions
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id !== 1) {
                abort(403, 'Access denied. This feature is only available to Kashtre administrators.');
            }

            if (!in_array('View Calling Module', auth()->user()->permissions ?? [])) {
                abort(403, 'Access denied. You do not have permission to view the calling module configuration.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $configs = CallingModuleConfig::with(['business', 'createdBy', 'updatedBy'])
            ->orderBy('business_id')
            ->get();

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();

        return view('settings.calling-module.index', compact('configs', 'businesses'));
    }

    public function create()
    {
        if (!in_array('Add Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add calling module configurations.');
        }

        // Only show businesses that do not yet have a config
        $existingBusinessIds = CallingModuleConfig::pluck('business_id')->toArray();
        $businesses = Business::where('id', '!=', 1)
            ->whereNotIn('id', $existingBusinessIds)
            ->orderBy('name')
            ->get();

        return view('settings.calling-module.create', compact('businesses'));
    }

    public function store(Request $request)
    {
        if (!in_array('Add Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add calling module configurations.');
        }

        $validated = $request->validate([
            'business_id'   => 'required|exists:businesses,id',
            'description'   => 'nullable|string|max:1000',
            'is_active'     => 'boolean',
            'audio_enabled' => 'boolean',
            'video_enabled' => 'boolean',
        ]);

        $exists = CallingModuleConfig::where('business_id', $validated['business_id'])->exists();
        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'A calling module configuration already exists for this business.');
        }

        CallingModuleConfig::create([
            'business_id'   => $validated['business_id'],
            'description'   => $validated['description'] ?? null,
            'is_active'     => $request->boolean('is_active', true),
            'audio_enabled' => $request->boolean('audio_enabled', true),
            'video_enabled' => $request->boolean('video_enabled', true),
            'created_by'    => Auth::id(),
        ]);

        return redirect()->route('calling-module-configs.index')
            ->with('success', 'Calling module enabled for the selected business.');
    }

    public function edit(CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Edit Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit calling module configurations.');
        }

        $callingModuleConfig->load('business');

        return view('settings.calling-module.edit', compact('callingModuleConfig'));
    }

    public function update(Request $request, CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Edit Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit calling module configurations.');
        }

        $validated = $request->validate([
            'description'                => 'nullable|string|max:1000',
            'audio_enabled'              => 'boolean',
            'video_enabled'              => 'boolean',
            'default_emergency_message'  => 'nullable|string|max:500',
            'emergency_repeat_count'     => 'nullable|integer|min:1|max:10',
            'emergency_repeat_interval'  => 'nullable|integer|min:0|max:60',
            'emergency_key_cooldown'     => 'nullable|integer|min:10|max:3600',
        ]);

        $callingModuleConfig->update([
            'description'               => $validated['description'] ?? null,
            'audio_enabled'             => $request->boolean('audio_enabled', true),
            'video_enabled'             => $request->boolean('video_enabled', true),
            'default_emergency_message' => $validated['default_emergency_message'] ?? null,
            'emergency_repeat_count'    => $validated['emergency_repeat_count'] ?? 3,
            'emergency_repeat_interval' => $validated['emergency_repeat_interval'] ?? 5,
            'emergency_key_cooldown'    => $validated['emergency_key_cooldown'] ?? 180,
            'updated_by'                => Auth::id(),
        ]);

        return redirect()->route('calling-module-configs.index')
            ->with('success', 'Calling module configuration updated successfully.');
    }

    public function destroy(CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Delete Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to delete calling module configurations.');
        }

        $callingModuleConfig->delete();

        return redirect()->route('calling-module-configs.index')
            ->with('success', 'Calling module configuration removed successfully.');
    }

    public function toggleStatus(CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Manage Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to manage calling module configurations.');
        }

        $callingModuleConfig->update([
            'is_active'  => !$callingModuleConfig->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $callingModuleConfig->is_active ? 'activated' : 'deactivated';

        return redirect()->route('calling-module-configs.index')
            ->with('success', "Calling module {$status} successfully.");
    }

    public function toggleAudio(CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Manage Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $callingModuleConfig->update([
            'audio_enabled' => !$callingModuleConfig->audio_enabled,
            'updated_by'    => Auth::id(),
        ]);

        $status = $callingModuleConfig->audio_enabled ? 'enabled' : 'disabled';

        return redirect()->route('calling-module-configs.index')
            ->with('success', "Audio announcements {$status} successfully.");
    }

    public function toggleVideo(CallingModuleConfig $callingModuleConfig)
    {
        if (!in_array('Manage Calling Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $callingModuleConfig->update([
            'video_enabled' => !$callingModuleConfig->video_enabled,
            'updated_by'    => Auth::id(),
        ]);

        $status = $callingModuleConfig->video_enabled ? 'enabled' : 'disabled';

        return redirect()->route('calling-module-configs.index')
            ->with('success', "Video display {$status} successfully.");
    }
}
