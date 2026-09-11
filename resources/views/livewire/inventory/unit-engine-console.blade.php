<div class="space-y-6">
    @unless($engineEnabled)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Unit engine is off. Set <code class="font-mono">UNIT_ENGINE_ENABLED=true</code> in <code class="font-mono">.env</code>, then
            <code class="font-mono">php artisan config:clear</code>.
        </div>
    @endunless

    @if(!empty($catalogStats['strict']))
        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
            <strong>Strict mode</strong> is on (<code class="font-mono">UNIT_ENGINE_STRICT=true</code>): unmapped packaging throws instead of silent legacy fallback.
        </div>
    @endif

    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
        @foreach([
            'catalog' => 'Catalog',
            'mappings' => 'Mappings',
            'rules' => 'Packaging rules',
            'composites' => 'Composites',
            'governance' => 'Governance',
            'policies' => 'Policies',
            'audit' => 'Audit',
        ] as $key => $label)
            <button type="button"
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium',
                        'bg-slate-800 text-white' => $activeTab === $key,
                        'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' => $activeTab !== $key,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($activeTab === 'catalog')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm space-y-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Unit catalog</h2>
                <p class="mt-1 text-sm text-gray-500">System + organisation units. Packaging is still set on each item (sale unit, order unit, factor).</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button"
                            wire:click="installSeedPack"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="installSeedPack">Install / refresh unit seed pack</span>
                        <span wire:loading wire:target="installSeedPack">Seeding…</span>
                    </button>
                    <p class="text-xs text-gray-500 max-w-xl">
                        Loads SYSTEM catalog (g, mg, box, carton, mg/mL, …), maps this organisation’s Item Unit names, and creates packaging rules where sale ≠ order unit.
                    </p>
                </div>
                @if($installMessage) <p class="mt-2 text-sm text-green-800">{{ $installMessage }}</p> @endif
                @if($installError) <p class="mt-2 text-sm text-red-700">{{ $installError }}</p> @endif

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Units</p>
                        <p class="font-mono text-lg font-semibold text-gray-900">{{ $catalogStats['units'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Mapped</p>
                        <p class="font-mono text-lg font-semibold text-gray-900">{{ $catalogStats['mappings_mapped'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Pending</p>
                        <p class="font-mono text-lg font-semibold text-gray-900">{{ $catalogStats['mappings_pending'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Pkg rules</p>
                        <p class="font-mono text-lg font-semibold text-gray-900">{{ $catalogStats['packaging_rules'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Linked items</p>
                        <p class="font-mono text-lg font-semibold text-gray-900">{{ $catalogStats['linked_items'] }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="catalogQuery" placeholder="name, symbol, code…"
                           class="mt-1 block w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm">
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Code</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Symbol</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Class</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Tenant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($catalogRows as $row)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['code'] }}</td>
                                    <td class="px-3 py-2">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $row['symbol'] }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['class'] }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $row['tenant'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">
                                        No units. Run <code class="font-mono">php artisan units:install</code>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-900">Add organisation unit</h3>
                <p class="mt-1 text-xs text-gray-500">Creates an active packaging/count unit for this business only (SYSTEM catalog stays read-only).</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Code</label>
                        <input type="text" wire:model="draftCode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Name</label>
                        <input type="text" wire:model="draftName" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Symbol</label>
                        <input type="text" wire:model="draftSymbol" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Class</label>
                        <select wire:model="draftClass" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="PACKAGING_CONTEXTUAL">Packaging</option>
                            <option value="COUNT_CONTEXTUAL">Count</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" wire:click="createDraftUnit"
                            class="inline-flex items-center rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-900">
                        Create unit
                    </button>
                </div>
                @if($draftMessage) <p class="mt-2 text-sm text-green-800">{{ $draftMessage }}</p> @endif
                @if($draftError) <p class="mt-2 text-sm text-red-700">{{ $draftError }}</p> @endif
            </div>
        </div>
    @endif

    @if($activeTab === 'mappings')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Legacy name mappings</h2>
            <p class="mt-1 text-sm text-gray-500">
                Names from Manage Item Units linked to the shared catalog. Map PENDING names (e.g. Test Unit) so packaging can use the engine.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach(['ALL', 'PENDING', 'MAPPED', 'UNMATCHED'] as $status)
                    <button type="button" wire:click="$set('mappingFilter', '{{ $status }}')"
                            @class([
                                'rounded-md px-2.5 py-1 text-xs font-medium border',
                                'bg-slate-800 text-white border-slate-800' => $mappingFilter === $status,
                                'bg-white text-gray-700 border-gray-300' => $mappingFilter !== $status,
                            ])>{{ $status }}</button>
                @endforeach
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Source name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Mapped to</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($mappingRows as $row)
                            <tr wire:key="map-{{ $row->id }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ $row->source_value }}</td>
                                <td class="px-3 py-2">{{ $row->status }} · {{ $row->match_method }}</td>
                                <td class="px-3 py-2">{{ $row->unit?->code ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($row->status !== 'MAPPED')
                                        <div class="flex flex-wrap items-center gap-2">
                                            <select wire:model="mapTargetUnitId" class="rounded-md border-gray-300 text-xs max-w-xs">
                                                <option value="">Core unit…</option>
                                                @foreach($coreUnitIdOptions as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="mapLegacy({{ $row->id }})"
                                                    class="text-xs font-medium text-blue-600 hover:text-blue-800">Map</button>
                                            <button type="button" wire:click="ignoreLegacy({{ $row->id }})"
                                                    class="text-xs font-medium text-gray-500 hover:text-gray-800">Ignore</button>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No mappings for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($activeTab === 'rules')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Packaging rules</h2>
            <p class="mt-1 text-sm text-gray-500">
                Created when you save an item with different sale/order units and a factor. Prefer keeping the factor in sync on the item form.
            </p>

            @if($rulesMessage) <p class="mt-2 text-sm text-green-800">{{ $rulesMessage }}</p> @endif
            @if($rulesError) <p class="mt-2 text-sm text-red-700">{{ $rulesError }}</p> @endif

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">From → To</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Item key</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Factor</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($packagingRuleRows as $row)
                            <tr wire:key="rule-{{ $row['id'] }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['from'] }} → {{ $row['to'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $row['item_key'] }}</td>
                                <td class="px-3 py-2 font-mono">{{ $row['factor'] }}</td>
                                <td class="px-3 py-2">
                                    @if($editingRuleId === $row['id'])
                                        <div class="flex items-center gap-2">
                                            <input type="text" wire:model="editingFactor" class="w-24 rounded-md border-gray-300 text-xs">
                                            <button type="button" wire:click="saveRuleFactor" class="text-xs font-medium text-blue-600">Save</button>
                                            <button type="button" wire:click="$set('editingRuleId', null)" class="text-xs text-gray-500">Cancel</button>
                                        </div>
                                    @else
                                        <button type="button"
                                                wire:click="startEditRule({{ $row['id'] }}, '{{ $row['factor'] }}')"
                                                class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit factor</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">
                                    No packaging rules yet. Save a good with order unit ≠ sale unit and a factor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($activeTab === 'composites')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Composite builder</h2>
            <p class="mt-1 text-sm text-gray-500">Build a ratio unit (numerator / denominator), e.g. mg per mL. Seeded examples: MG_PER_ML, G_PER_L.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Code</label>
                    <input type="text" wire:model="compositeCode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="MG_PER_DL">
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Name</label>
                    <input type="text" wire:model="compositeName" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Numerator</label>
                    <select wire:model="compositeNumeratorPublicId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Select…</option>
                        @foreach($unitPublicIdOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Denominator</label>
                    <select wire:model="compositeDenominatorPublicId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Select…</option>
                        @foreach($unitPublicIdOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" wire:click="previewComposite" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700">Preview</button>
                <button type="button" wire:click="saveComposite" class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white">Save composite</button>
            </div>
            @if($compositePreview) <p class="mt-3 text-sm text-gray-800 font-mono">{{ $compositePreview }}</p> @endif
            @if($compositeMessage) <p class="mt-2 text-sm text-green-800">{{ $compositeMessage }}</p> @endif
            @if($compositeError) <p class="mt-2 text-sm text-red-700">{{ $compositeError }}</p> @endif
        </div>
    @endif

    @if($activeTab === 'governance')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Organisation unit governance</h2>
            <p class="mt-1 text-sm text-gray-500">Activate, deprecate, or mark UCUM-verified for tenant-owned units (SYSTEM stays read-only).</p>
            @if($govMessage) <p class="mt-2 text-sm text-green-800">{{ $govMessage }}</p> @endif
            @if($govError) <p class="mt-2 text-sm text-red-700">{{ $govError }}</p> @endif
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Code</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Verification</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($governanceRows as $row)
                            <tr wire:key="gov-{{ $row['public_id'] }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['code'] }} · {{ $row['symbol'] }}</td>
                                <td class="px-3 py-2">{{ $row['status'] }}</td>
                                <td class="px-3 py-2">{{ $row['verification'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap space-x-2">
                                    @if($row['status'] !== 'ACTIVE')
                                        <button type="button" wire:click="activateUnit('{{ $row['public_id'] }}')" class="text-xs font-medium text-blue-600">Activate</button>
                                    @endif
                                    @if($row['status'] === 'ACTIVE')
                                        <button type="button" wire:click="deprecateUnit('{{ $row['public_id'] }}')" class="text-xs font-medium text-amber-700">Deprecate</button>
                                    @endif
                                    @if($row['verification'] !== 'VERIFIED')
                                        <button type="button" wire:click="verifyUnit('{{ $row['public_id'] }}')" class="text-xs font-medium text-green-700">Verify UCUM</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No tenant units yet. Create one on Catalog or Composites.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($activeTab === 'policies')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm space-y-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Module unit policies</h2>
                <p class="mt-1 text-sm text-gray-500">Preferred units for Clinical / LIMS / Inventory domain objects (analyte, method, item…).</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Module</label>
                    <select wire:model="policyModule" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="CLINICAL">CLINICAL</option>
                        <option value="LIMS">LIMS</option>
                        <option value="INVENTORY">INVENTORY</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Object type</label>
                    <input type="text" wire:model="policyObjectType" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="ANALYTE">
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Object public id</label>
                    <input type="text" wire:model="policyObjectId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="analyte-123">
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase text-gray-500">Usage role</label>
                    <input type="text" wire:model="policyRole" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="REPORTING">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase text-gray-500">Unit</label>
                    <select wire:model="policyUnitId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Select…</option>
                        @foreach($coreUnitIdOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="button" wire:click="savePolicy" class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white">Save policy</button>
            @if($policyMessage) <p class="text-sm text-green-800">{{ $policyMessage }}</p> @endif
            @if($policyError) <p class="text-sm text-red-700">{{ $policyError }}</p> @endif

            <div class="overflow-x-auto border-t border-gray-100 pt-4">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Module</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Object</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Role</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($policyRows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['module'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['type'] }} · {{ $row['object'] }}</td>
                                <td class="px-3 py-2">{{ $row['role'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['unit'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No policies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($activeTab === 'audit')
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Unit audit trail</h2>
            <p class="mt-1 text-sm text-gray-500">Recent governance and policy events for this organisation.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">When</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Object</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($auditRows as $row)
                            <tr>
                                <td class="px-3 py-2 text-xs text-gray-600">{{ $row['at'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['action'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['object'] }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['reason'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No audit events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
