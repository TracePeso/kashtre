<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    {{-- Dictionary picker --}}
    <nav class="lg:col-span-1 bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 h-fit">
        @foreach ($groups as $groupName => $entries)
            <div class="mb-4">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">
                    {{ $groupName }}
                </div>
                <ul class="space-y-0.5">
                    @foreach ($entries as $key => $entry)
                        <li>
                            <button wire:click="selectDictionary('{{ $key }}')"
                                class="w-full text-left text-sm px-2 py-1.5 rounded transition
                                    {{ $dictionary === $key
                                        ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                {{ $entry['label'] }}
                                @if ($entry['readonly'] ?? false)
                                    <span class="text-[9px] text-gray-400 uppercase ml-1">read-only</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="lg:col-span-3 space-y-4">

        {{-- Business 1 is the platform and oversees every facility, so its
             administrators configure a chosen facility's dictionaries rather
             than their own. Everyone else is pinned to their own business and
             never sees this. --}}
        @if ($isKashtreAdmin)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                    Configuring facility
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <select wire:model="contextBusinessId" wire:change="selectBusiness"
                        class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 min-w-[16rem]">
                        <option value="">— choose a facility —</option>
                        @foreach ($businesses as $b)
                            <option value="{{ $b->id }}">
                                {{ $b->name }}@if ($b->entity_code) ({{ $b->entity_code }})@endif
                            </option>
                        @endforeach
                    </select>
                    @if ($contextBusiness)
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Changes are saved against <strong>{{ $contextBusiness->name }}</strong>,
                            tenant <span class="font-mono">{{ $contextBusiness->id }}</span>.
                        </span>
                    @endif
                </div>
            </div>
        @endif

        @if ($needsBusiness)
            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-900 dark:text-blue-200">
                Choose a facility above to view and edit its clinical dictionaries. These are
                per-facility — units, reason codes, wards and care teams belong to the entity that
                uses them, not to the platform.
            </div>
        @endif

        @if (! $available)
            <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-200">
                The Clinical Module is not reachable, so its dictionaries cannot be read or edited.
                These are configured in the Clinical Module and mirrored here; on the local driver they
                are seeded by migration and there is nothing to author.
            </div>
        @endif

        @if ($definition && ! $needsBusiness)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $definition['label'] }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $definition['about'] }}</p>
                <p class="mt-2 text-[11px] font-mono text-gray-400 dark:text-gray-500">{{ $definition['path'] }}</p>
            </div>

            @if ($statusMessage)
                <div class="rounded-lg border border-green-300 bg-green-50 dark:bg-green-900/30 dark:border-green-700 p-3 text-sm text-green-800 dark:text-green-200">
                    {{ $statusMessage }}
                </div>
            @endif

            @if ($errorMessage)
                <div class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/30 dark:border-red-700 p-3 text-sm text-red-800 dark:text-red-200">
                    {{ $errorMessage }}
                </div>
            @endif

            {{-- Editor --}}
            @if ($canManage && $customForm)
                @livewire($customForm, key('custom-form-'.$dictionary))
            @elseif ($canManage && ! $isReadonly && ! empty($definition['fields']))
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                        {{ $editingId !== null ? 'Edit entry #'.$editingId : 'Add an entry' }}
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($definition['fields'] as $field => $meta)
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                                    {{ $meta['label'] }}
                                    @if ($meta['required'] ?? false)<span class="text-red-600">*</span>@endif
                                </label>

                                @if (($meta['type'] ?? 'text') === 'boolean')
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model="form.{{ $field }}"
                                            class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                        Enabled
                                    </label>
                                @elseif (($meta['type'] ?? 'text') === 'textarea')
                                    <textarea wire:model="form.{{ $field }}" rows="2"
                                        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
                                @elseif (($meta['type'] ?? 'text') === 'json')
                                    <textarea wire:model="form.{{ $field }}" rows="6" placeholder="{}"
                                        class="w-full text-xs font-mono rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
                                @else
                                    <input type="{{ ($meta['type'] ?? 'text') === 'number' ? 'number' : 'text' }}"
                                        wire:model="form.{{ $field }}"
                                        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                @endif

                                @if (isset($fieldErrors[$field]))
                                    <div class="text-[10px] text-red-600 mt-0.5">{{ $fieldErrors[$field] }}</div>
                                @elseif (! empty($meta['help']))
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $meta['help'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button wire:click="save" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
                            {{ $editingId !== null ? 'Save changes' : 'Add' }}
                        </button>
                        @if ($editingId !== null)
                            <button wire:click="cancel" class="text-sm text-gray-500 px-3 py-2">Cancel</button>
                        @endif
                    </div>
                </div>
            @elseif ($isReadonly)
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    This dictionary is published read-only by the Clinical Module — it can be reviewed here
                    but is changed on their side.
                </p>
            @endif

            {{-- Search + status filter — every dictionary supports both the
                 same way (?search=, ?status=), so this is generic rather than
                 something each manifest entry has to declare. --}}
            @if ($available)
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Search</label>
                        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search this dictionary…"
                            class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Status</label>
                        <select wire:model.live="statusFilter"
                            class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                            <option value="">All</option>
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                    </div>
                    @if ($search !== '' || $statusFilter !== '')
                        <button wire:click="clearFilters"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:underline pb-1.5">
                            Clear
                        </button>
                    @endif
                </div>
            @endif

            {{-- Rows --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                @if (empty($rows))
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        @if (! $available)
                            Unavailable.
                        @elseif ($search !== '' || $statusFilter !== '')
                            No entries match this search.
                        @else
                            Nothing configured yet.
                        @endif
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    @foreach ($definition['columns'] as $heading)
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $heading }}</th>
                                    @endforeach
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    @if ($canManage && ! $isReadonly)
                                        <th class="px-4 py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($rows as $row)
                                    @php
                                        // Clinical's own status field wins; is_active is the
                                        // fallback for anything that only carries the boolean.
                                        $rowStatus = $row['status'] ?? (($row['is_active'] ?? true) ? 'ACTIVE' : 'INACTIVE');
                                        $rowActive = $rowStatus === 'ACTIVE';
                                    @endphp
                                    <tr wire:key="row-{{ $dictionary }}-{{ $row['id'] ?? $loop->index }}">
                                        @foreach ($definition['columns'] as $field => $heading)
                                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                @php $value = $row[$field] ?? null; @endphp
                                                @if (is_bool($value))
                                                    {{ $value ? 'Yes' : 'No' }}
                                                @elseif (is_array($value))
                                                    <span class="font-mono text-xs">{{ implode(', ', $value) }}</span>
                                                @else
                                                    {{ $value === null || $value === '' ? '—' : $value }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium uppercase
                                                {{ $rowActive
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                                {{ $rowStatus }}
                                            </span>
                                        </td>
                                        @if ($canManage && ! $isReadonly)
                                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                                <span class="inline-flex gap-3">
                                                    @if (! empty($definition['fields']))
                                                        <button wire:click="edit('{{ $row['id'] ?? '' }}', {{ \Illuminate\Support\Js::from($row) }})"
                                                            class="text-xs text-blue-700 dark:text-blue-300 hover:underline">Edit</button>
                                                    @endif
                                                    @if ($rowActive)
                                                        <button wire:click="deactivate('{{ $row['id'] ?? '' }}')"
                                                            wire:confirm="Deactivate this entry? It disappears from clinician drop-downs but every existing record that references it is unaffected."
                                                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:underline">Deactivate</button>
                                                    @else
                                                        <button wire:click="activate('{{ $row['id'] ?? '' }}')"
                                                            class="text-xs text-green-700 dark:text-green-400 hover:underline">Activate</button>
                                                    @endif
                                                </span>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
