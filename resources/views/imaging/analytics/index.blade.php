<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">Imaging Analytics</h2>
        <p class="mt-1 text-sm text-gray-500">Studies per modality, turnaround times, critical findings, and radiologist productivity.</p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow sm:rounded-lg p-5">
                <p class="text-sm text-gray-500">Avg. Report Turnaround</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">
                    {{ $avgTurnaroundHours !== null ? number_format($avgTurnaroundHours, 1).' hrs' : '—' }}
                </p>
                <p class="text-xs text-gray-400 mt-1">Image acquired &rarr; report submitted</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-5">
                <p class="text-sm text-gray-500">Avg. Verification Delay</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">
                    {{ $avgVerificationDelayHours !== null ? number_format($avgVerificationDelayHours, 1).' hrs' : '—' }}
                </p>
                <p class="text-xs text-gray-400 mt-1">Report submitted &rarr; verified</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-5">
                <p class="text-sm text-gray-500">Total Studies</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ array_sum($procedureVolumes) }}</p>
                <p class="text-xs text-gray-400 mt-1">Across all statuses</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-5">
                <p class="text-sm text-gray-500">Critical Findings</p>
                <p class="mt-1 text-2xl font-bold text-red-600">{{ $criticalFindings->count() }}</p>
                <p class="text-xs text-gray-400 mt-1">Most recent {{ $criticalFindings->count() }} shown below</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Studies Per Modality</h3>
                @if(empty($studiesPerModality))
                    <p class="text-sm text-gray-500">No studies yet.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($studiesPerModality as $modality => $count)
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-0">
                                <span class="text-gray-800">{{ $modality }}</span>
                                <span class="font-semibold text-gray-900">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Procedure Volumes By Status</h3>
                @if(empty($procedureVolumes))
                    <p class="text-sm text-gray-500">No studies yet.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($procedureVolumes as $status => $count)
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-0">
                                <span class="text-gray-800">{{ ucfirst(strtolower(str_replace('_', ' ', $status))) }}</span>
                                <span class="font-semibold text-gray-900">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Radiologist Productivity</h3>
                @if(empty($radiologistProductivity))
                    <p class="text-sm text-gray-500">No reports yet.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-200">
                                <th class="pb-2">Radiologist</th>
                                <th class="pb-2 text-right">Authored</th>
                                <th class="pb-2 text-right">Verified</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($radiologistProductivity as $row)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2 text-gray-800">{{ $row['name'] }}</td>
                                    <td class="py-2 text-right font-medium text-gray-900">{{ $row['authored'] }}</td>
                                    <td class="py-2 text-right font-medium text-gray-900">{{ $row['verified'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Critical Findings</h3>
                @if($criticalFindings->isEmpty())
                    <p class="text-sm text-gray-500">None flagged.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($criticalFindings as $report)
                            <li class="text-sm border-b border-gray-100 pb-2 last:border-0">
                                <a href="{{ $report->imagingStudy ? route('imaging-studies.show', $report->imagingStudy) : '#' }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                    {{ $report->imagingStudy?->accession_number ?? '—' }}
                                </a>
                                <span class="text-gray-500">
                                    · {{ ucfirst(strtolower($report->status)) }} · {{ $report->created_at->format('M d, Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
</x-app-layout>
