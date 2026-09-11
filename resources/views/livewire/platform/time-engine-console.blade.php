<div>
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
            {{ $enabled ? 'SHARED_TIME_ENABLED' : 'engine flag off' }}
        </span>
        <span class="text-xs text-gray-500 font-mono">TZDB {{ $tzdb }}</span>
    </div>

    <div class="flex flex-wrap gap-1 border-b border-gray-200 mb-4">
        @foreach ([
            'clock' => 'Clock',
            'catalogue' => 'Catalogue',
            'policies' => 'Policies',
            'convert' => 'Convert',
            'business' => 'Business date',
            'schedules' => 'Schedules',
            'calendars' => 'Calendars',
            'periods' => 'Periods',
            'devices' => 'Devices',
            'audit' => 'Audit',
        ] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="px-3 py-2 text-sm {{ $activeTab === $key ? 'border-b-2 border-blue-600 text-blue-700 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'clock')
        <div class="space-y-3">
            <p class="text-sm text-gray-600">Trusted UTC from the shared clock:</p>
            <p class="font-mono text-lg text-gray-900">{{ $nowUtc }}</p>
            <div class="text-sm text-gray-600">
                Node health:
                @if ($clockHealth)
                    <span class="font-medium">{{ $clockHealth->status }}</span>
                    (drift {{ $clockHealth->drift_seconds }}s, checked {{ $clockHealth->checked_at_utc }})
                @else
                    <span class="text-amber-700">not recorded — run time:install</span>
                @endif
            </div>
            <button type="button" wire:click="recordClockHealthy" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">
                Record healthy check
            </button>
        </div>
    @endif

    @if ($activeTab === 'catalogue')
        <div class="space-y-3">
            <input type="search" wire:model.live.debounce.300ms="catalogueQuery" placeholder="Search IANA…"
                   class="w-full max-w-md rounded border-gray-300 text-sm" />
            <div class="overflow-x-auto border border-gray-200 rounded">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-3 py-2">IANA</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Region</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($zones as $z)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono">{{ $z->iana_id }}</td>
                                <td class="px-3 py-2">{{ $z->display_name }}</td>
                                <td class="px-3 py-2">{{ $z->region_code }}</td>
                                <td class="px-3 py-2">{{ $z->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-gray-500">No zones. Run <code class="font-mono">php artisan time:install</code>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activeTab === 'policies')
        <div class="space-y-4">
            <div class="grid sm:grid-cols-3 gap-3 max-w-3xl">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">IANA</label>
                    <input wire:model="policyIana" class="w-full rounded border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Scope</label>
                    <select wire:model="policyScope" class="w-full rounded border-gray-300 text-sm">
                        <option value="TENANT">TENANT</option>
                        <option value="BRANCH">BRANCH</option>
                        <option value="PLATFORM">PLATFORM</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Purpose</label>
                    <select wire:model="policyPurpose" class="w-full rounded border-gray-300 text-sm">
                        <option value="OPERATIONAL">OPERATIONAL</option>
                        <option value="FINANCIAL">FINANCIAL</option>
                        <option value="REPORTING">REPORTING</option>
                        <option value="PRESENTATION">PRESENTATION</option>
                    </select>
                </div>
            </div>
            <button type="button" wire:click="activateTenantPolicy" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Activate policy</button>
            @if ($policyMessage)<p class="text-sm text-emerald-700">{{ $policyMessage }}</p>@endif
            @if ($policyError)<p class="text-sm text-red-600">{{ $policyError }}</p>@endif

            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Resolve as-of (UTC ISO)</label>
                    <input wire:model="resolveAsOf" placeholder="optional" class="rounded border-gray-300 text-sm" />
                </div>
                <button type="button" wire:click="runResolve" class="text-sm px-3 py-1.5 border border-gray-300 rounded">Resolve now</button>
            </div>
            @if ($resolveResult)
                <pre class="text-xs bg-gray-50 p-3 rounded overflow-x-auto">{{ json_encode($resolveResult, JSON_PRETTY_PRINT) }}</pre>
            @endif

            <div class="overflow-x-auto border border-gray-200 rounded">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Scope</th>
                            <th class="px-3 py-2">IANA</th>
                            <th class="px-3 py-2">Purpose</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">v</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($policies as $p)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2">{{ $p->scope_type }}:{{ $p->subject_public_id }}</td>
                                <td class="px-3 py-2 font-mono">{{ $p->iana_id }}</td>
                                <td class="px-3 py-2">{{ $p->purpose }}</td>
                                <td class="px-3 py-2">{{ $p->status }}</td>
                                <td class="px-3 py-2">{{ $p->version_no }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activeTab === 'convert')
        <div class="space-y-3 max-w-xl">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Local civil datetime</label>
                <input wire:model="localInput" placeholder="2026-03-08 02:30:00" class="w-full rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">IANA</label>
                <input wire:model="localIana" class="w-full rounded border-gray-300 text-sm" />
            </div>
            <button type="button" wire:click="convertLocal" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Convert to UTC</button>
            @if ($convertResult)
                <pre class="text-xs bg-gray-50 p-3 rounded">{{ json_encode($convertResult, JSON_PRETTY_PRINT) }}</pre>
            @endif
            @if ($convertError)<p class="text-sm text-red-600">{{ $convertError }}</p>@endif
        </div>
    @endif

    @if ($activeTab === 'business')
        <div class="space-y-3 max-w-xl">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Window IANA</label>
                <input wire:model="bizDateIana" class="w-full rounded border-gray-300 text-sm" />
            </div>
            <button type="button" wire:click="showBusinessDate" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Compute business date</button>
            @if ($bizDateResult)<p class="text-sm font-mono text-gray-800">{{ $bizDateResult }}</p>@endif
        </div>
    @endif

    @if ($activeTab === 'schedules')
        <div class="space-y-3 max-w-xl">
            <div class="grid grid-cols-2 gap-2">
                <input wire:model="scheduleCode" placeholder="Code" class="rounded border-gray-300 text-sm" />
                <input wire:model="scheduleTime" placeholder="HH:MM:SS" class="rounded border-gray-300 text-sm" />
                <input wire:model="scheduleIana" class="rounded border-gray-300 text-sm col-span-2" />
                <select wire:model="scheduleRecurrence" class="rounded border-gray-300 text-sm col-span-2">
                    <option value="DAILY">DAILY</option>
                    <option value="WEEKLY">WEEKLY</option>
                    <option value="ONCE">ONCE</option>
                </select>
            </div>
            <button type="button" wire:click="createSchedule" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Create + preview</button>
            @if ($scheduleMessage)<p class="text-sm text-gray-700">{{ $scheduleMessage }}</p>@endif
            @if ($schedulePreview)
                <pre class="text-xs bg-gray-50 p-3 rounded overflow-x-auto">{{ json_encode($schedulePreview, JSON_PRETTY_PRINT) }}</pre>
            @endif
            <ul class="text-sm text-gray-600 list-disc pl-5">
                @foreach ($schedules as $s)
                    <li>{{ $s->code }} — {{ $s->local_time }} {{ $s->iana_id }} ({{ $s->recurrence }})</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($activeTab === 'calendars')
        <div class="space-y-3">
            <div class="flex gap-2 max-w-xl">
                <input wire:model="calendarCode" class="rounded border-gray-300 text-sm" />
                <input wire:model="calendarName" class="flex-1 rounded border-gray-300 text-sm" />
            </div>
            <button type="button" wire:click="createCalendar" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Create + seed weekends</button>
            @if ($calendarMessage)<p class="text-sm text-gray-700">{{ $calendarMessage }}</p>@endif
            <ul class="text-sm text-gray-600 list-disc pl-5">
                @foreach ($calendars as $c)
                    <li>{{ $c->code }} v{{ $c->version_no }} — {{ $c->name }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($activeTab === 'periods')
        <div class="space-y-3 max-w-xl">
            <div class="grid grid-cols-2 gap-2">
                <input wire:model="periodCode" placeholder="Code (YYYY-MM)" class="rounded border-gray-300 text-sm" />
                <input wire:model="periodName" placeholder="Name" class="rounded border-gray-300 text-sm" />
                <input wire:model="periodStart" type="date" class="rounded border-gray-300 text-sm" />
                <input wire:model="periodEnd" type="date" class="rounded border-gray-300 text-sm" />
            </div>
            <button type="button" wire:click="openPeriod" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Open period</button>
            @if ($periodMessage)<p class="text-sm text-gray-700">{{ $periodMessage }}</p>@endif
            <table class="min-w-full text-sm border border-gray-200 rounded">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Range</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach ($periods as $p)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-mono">{{ $p->code }}</td>
                            <td class="px-3 py-2">{{ $p->local_start_date?->format('Y-m-d') }} → {{ $p->local_end_date?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">{{ $p->status }}</td>
                            <td class="px-3 py-2">
                                @if ($p->isOpen())
                                    <button type="button" wire:click="closePeriod({{ $p->id }})" class="text-xs text-red-600">Close</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'devices')
        <div class="space-y-3 max-w-xl">
            <input wire:model="deviceId" placeholder="Device public id" class="w-full rounded border-gray-300 text-sm" />
            <input wire:model="deviceRaw" placeholder="Raw timestamp" class="w-full rounded border-gray-300 text-sm" />
            <input wire:model="deviceTz" placeholder="Raw timezone" class="w-full rounded border-gray-300 text-sm" />
            <button type="button" wire:click="observeDevice" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded">Normalize</button>
            @if ($deviceMessage)<p class="text-sm text-gray-700">{{ $deviceMessage }}</p>@endif
            <table class="min-w-full text-sm border border-gray-200 rounded">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="px-3 py-2">Device</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">UTC</th><th class="px-3 py-2">Reason</th></tr>
                </thead>
                <tbody>
                    @foreach ($devices as $d)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-mono text-xs">{{ $d->device_public_id }}</td>
                            <td class="px-3 py-2">{{ $d->status }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $d->normalized_at_utc }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $d->quarantine_reason }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'audit')
        <div class="overflow-x-auto border border-gray-200 rounded">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="px-3 py-2">Object</th>
                        <th class="px-3 py-2">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audits as $a)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $a->created_at }}</td>
                            <td class="px-3 py-2">{{ $a->action }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $a->object_type }}:{{ $a->object_public_id }}</td>
                            <td class="px-3 py-2 text-xs">{{ $a->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-gray-500">No audit events yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
