<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">HR — System Settings</h1>
                    <p class="text-sm text-gray-500 mt-1">These settings are managed in the HR module.</p>
                </div>
                <a href="{{ route('hr.embed', ['path' => '/hr/settings']) }}"
                   class="text-sm bg-[#011478] text-white px-4 py-2 rounded-lg hover:bg-blue-900 transition">
                    Open Settings Hub →
                </a>
            </div>

            @php
            $sections = [
                ['title' => 'Leave Types & Policy',    'desc' => 'Configure leave entitlements, accruals and approval policies.',    'path' => '/hr/settings/hr-policy',   'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['title' => 'Shifts & Rosters',         'desc' => 'Manage shift schedules, assignments and roster rules.',            'path' => '/hr/settings/shifts',      'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['title' => 'Public Holidays',          'desc' => 'Define public holidays for your organisation.',                    'path' => '/hr/settings/holidays',    'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
                ['title' => 'MOH Settings',             'desc' => 'Ministry of Health regulatory export configuration.',              'path' => '/hr/settings/moh',         'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['title' => 'Device Pairing',           'desc' => 'Manage biometric and mobile clock-in devices.',                  'path' => '/hr/settings/devices',     'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['title' => 'Network & Field Duty',     'desc' => 'Configure network subnets and field duty assignments.',           'path' => '/hr/settings/networks',    'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9'],
                ['title' => 'Space Designations',       'desc' => 'Assign heads of spaces and configure space staffing models.',    'path' => '/hr/settings/spaces',      'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                ['title' => 'Governance Hierarchy',     'desc' => 'Define reporting lines and organisational governance nodes.',    'path' => '/hr/settings/governance',  'icon' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                ['title' => 'Workforce Settings',       'desc' => 'RVU task weights and revenue attribution rules.',                'path' => '/hr/settings/workforce',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($sections as $section)
                <a href="{{ route('hr.embed', ['path' => $section['path']]) }}"
                   class="bg-white rounded-lg shadow px-5 py-4 hover:shadow-md transition group flex gap-4 items-start">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition">
                        <svg class="w-5 h-5 text-[#011478]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $section['icon'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 group-hover:text-[#011478]">{{ $section['title'] }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5 leading-snug">{{ $section['desc'] }}</p>
                    </div>
                </a>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>
