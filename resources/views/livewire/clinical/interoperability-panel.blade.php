<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Interoperability &amp; Quality Measures</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Volume 12 — read-only. Creating a bulk exchange job needs an authorization-policy decision this host
        doesn't own; running a quality measure needs its own measure-computation logic, not fabricated here.
    </p>

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Exchange Jobs</h5>
    @if (empty($exchangeJobs))
        <p class="text-xs text-gray-400 mb-4">None yet.</p>
    @else
        <ul class="text-sm mb-4 space-y-1">
            @foreach ($exchangeJobs as $job)
                <li wire:key="job-{{ $job['public_id'] }}">{{ $job['purpose_code'] ?? '' }} — {{ $job['status'] ?? '' }}</li>
            @endforeach
        </ul>
    @endif

    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Quality Measure Versions</h5>
    @if (empty($measureVersions))
        <p class="text-xs text-gray-400 mb-4">None defined yet.</p>
    @else
        <ul class="text-sm mb-4 space-y-1">
            @foreach ($measureVersions as $v)
                <li wire:key="mv-{{ $v['public_id'] }}">{{ $v['public_id'] }} — {{ $v['status'] ?? '' }}</li>
            @endforeach
        </ul>
    @endif

    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Measure Runs</h5>
    @if (empty($measureRuns))
        <p class="text-xs text-gray-400">None yet.</p>
    @else
        <ul class="text-sm space-y-1">
            @foreach ($measureRuns as $r)
                <li wire:key="mr-{{ $r['public_id'] }}">{{ $r['subject_public_id'] ?? '' }} — {{ $r['status'] ?? '' }}</li>
            @endforeach
        </ul>
    @endif
</div>
