<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Patient Workspace</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 2 — banner + longitudinal timeline, merging notes/observations/orders/problems by each
        entry's own clinically meaningful time. Distinct from the ward-census/handover views.
    </p>

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    @if ($banner)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5 text-xs">
            <div>
                <dt class="text-gray-400 uppercase mb-1">Encounter</dt>
                <dd>{{ $banner['encounter']['encounter_class'] ?? '—' }} · {{ $banner['encounter']['status'] ?? '—' }}
                    @if ($banner['encounter']['service'] ?? null) · {{ $banner['encounter']['service'] }} @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-400 uppercase mb-1">Location</dt>
                <dd>{{ $banner['location']['ward_name'] ?? '—' }}
                    @if ($banner['location']['room_number'] ?? null) · Room {{ $banner['location']['room_number'] }} @endif
                </dd>
            </div>
            @if (! empty($banner['safety_alerts']))
                <div class="sm:col-span-2">
                    <dt class="text-gray-400 uppercase mb-1">Safety alerts</dt>
                    <dd class="space-y-1">
                        @foreach ($banner['safety_alerts'] as $alert)
                            <div class="text-red-700 dark:text-red-300">⚠ {{ $alert['alert_label'] ?? '' }} ({{ $alert['severity_tier'] ?? '' }})</div>
                        @endforeach
                    </dd>
                </div>
            @endif
            @if ($banner['confidentiality']['restricted'] ?? false)
                <div class="sm:col-span-2 text-amber-700 dark:text-amber-300">
                    🔒 This chart carries a sensitivity restriction. The label/reason is withheld here regardless
                    of your access — see the Sensitivity Restrictions panel if you're authorized to view it.
                </div>
            @endif
        </div>
    @endif

    <h5 class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">Timeline</h5>
    <div class="space-y-1 max-h-96 overflow-y-auto">
        @forelse ($timeline as $entry)
            <div wire:key="tl-{{ $entry['type'] ?? '' }}-{{ $entry['id'] ?? $loop->index }}" class="text-xs border-t border-gray-100 dark:border-gray-700 py-1.5 flex items-center justify-between">
                <span><span class="font-medium">{{ $entry['type'] ?? '' }}</span> — {{ $entry['summary'] ?? '' }}</span>
                <span class="text-gray-400">{{ $entry['occurred_at'] ?? '' }}</span>
            </div>
        @empty
            <p class="text-xs text-gray-400">No timeline entries yet.</p>
        @endforelse
    </div>
</div>
