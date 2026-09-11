@php
    $readOnly = $readOnly ?? false;
    $checkbox = fn (string $name, bool $default = false) => old($name, $config->{$name} ?? $default);
@endphp

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-semibold text-gray-900">Clinical stock features</h4>
        <p class="text-xs text-gray-500 mt-0.5">Optional End Store capabilities. Turn on only what this organisation uses.</p>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_floor_stock_management" value="0">
            @endunless
            <input type="checkbox" name="enable_floor_stock_management" value="1"
                   @checked($checkbox('enable_floor_stock_management', true))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Floor stock management</span>
                <span class="block text-xs text-gray-500">Satellite stores under End Stores, and recording usage from End Store / Satellite stock (including administrative usage).</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_crash_cart_management" value="0">
            @endunless
            <input type="checkbox" name="enable_crash_cart_management" value="1"
                   @checked($checkbox('enable_crash_cart_management', false))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Crash cart management</span>
                <span class="block text-xs text-gray-500">Satellite stores with role Crash cart: fixed manifest, break seal, record usage. Not EndStore queue nodes.</span>
            </span>
        </label>
    </div>

    <div class="border-t border-gray-200 pt-6">
        <h4 class="text-sm font-semibold text-gray-900">Traceability</h4>
        <p class="text-xs text-gray-500 mt-0.5">Extra tracking fields for later inventory flows. Safe to leave off until needed.</p>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_batch_lot_tracking" value="0">
            @endunless
            <input type="checkbox" name="enable_batch_lot_tracking" value="1"
                   @checked($checkbox('enable_batch_lot_tracking', false))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Batch / lot tracking</span>
                <span class="block text-xs text-gray-500">Require batch/lot on End Store dispense when enabled.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_serial_number_tracking" value="0">
            @endunless
            <input type="checkbox" name="enable_serial_number_tracking" value="1"
                   @checked($checkbox('enable_serial_number_tracking', false))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Serial number tracking</span>
                <span class="block text-xs text-gray-500">Require serial numbers on End Store dispense when enabled.</span>
            </span>
        </label>
    </div>

    <div class="border-t border-gray-200 pt-6">
        <h4 class="text-sm font-semibold text-gray-900">Ordering &amp; network</h4>
        <p class="text-xs text-gray-500 mt-0.5">Turn off features this organisation does not use. Defaults keep existing tenants enabled.</p>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_internal_ordering" value="0">
            @endunless
            <input type="checkbox" name="enable_internal_ordering" value="1"
                   @checked($checkbox('enable_internal_ordering', true))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Internal ordering</span>
                <span class="block text-xs text-gray-500">Internal replenishment and store-to-store draft orders.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_automated_stock_counts" value="0">
            @endunless
            <input type="checkbox" name="enable_automated_stock_counts" value="1"
                   @checked($checkbox('enable_automated_stock_counts', true))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Automated stock counts</span>
                <span class="block text-xs text-gray-500">Stock count drafts, submit, and approval workflow.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700 {{ $readOnly ? 'opacity-80' : '' }}">
            @unless($readOnly)
                <input type="hidden" name="enable_multi_store_network" value="0">
            @endunless
            <input type="checkbox" name="enable_multi_store_network" value="1"
                   @checked($checkbox('enable_multi_store_network', true))
                   @disabled($readOnly)
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
            <span>
                <span class="font-medium text-gray-900">Multi-store network view</span>
                <span class="block text-xs text-gray-500">Monitor Stock network roll-up across descendant stores.</span>
            </span>
        </label>
    </div>

    <div class="border-t border-gray-200 pt-6 space-y-4">
        <div>
            <h4 class="text-sm font-semibold text-gray-900">Label dictionary</h4>
            <p class="text-xs text-gray-500 mt-0.5">Tenant terminology overrides. Leave blank for platform defaults.</p>
        </div>
        @php $dict = old('label_dictionary', $config->label_dictionary ?? []); @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach([
                'label_client' => ['Client', $dict['client'] ?? ''],
                'label_client_space' => ['Client Space', $dict['client_space'] ?? ''],
                'label_item' => ['Inventory Item', $dict['item'] ?? ''],
                'label_store' => ['Store', $dict['store'] ?? ''],
                'label_usage_record' => ['Usage Record', $dict['usage_record'] ?? ''],
            ] as $name => [$placeholder, $value])
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ $placeholder }}</label>
                    <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                           @disabled($readOnly)
                           placeholder="{{ $placeholder }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm disabled:opacity-60">
                </div>
            @endforeach
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Visit re-activation lookback (days)</label>
            <input type="number" min="1" name="visit_reactivation_lookback_days"
                   value="{{ old('visit_reactivation_lookback_days', $config->visit_reactivation_lookback_days ?? 30) }}"
                   @disabled($readOnly)
                   class="mt-1 block w-40 rounded-md border-gray-300 text-sm disabled:opacity-60">
            <p class="mt-1 text-xs text-gray-500">Open End Store tickets within this window reattach when a client gets a new visitor ID.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Administrative usage purposes</label>
            <textarea name="admin_usage_purposes" rows="3"
                      @disabled($readOnly)
                      placeholder="Cleaning, Disinfection, Spill Management"
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm disabled:opacity-60">{{ old('admin_usage_purposes', implode("\n", $config->admin_usage_purposes ?? [])) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">One purpose per line (or comma-separated). Used on Record Usage for administrative context.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">STAT / urgent priority keywords</label>
            <input type="text" name="stat_priority_keywords"
                   value="{{ old('stat_priority_keywords', implode(',', $config->stat_priority_keywords ?? ['STAT', 'URGENT'])) }}"
                   @disabled($readOnly)
                   placeholder="STAT,URGENT"
                   class="mt-1 block w-full rounded-md border-gray-300 text-sm disabled:opacity-60">
            <p class="mt-1 text-xs text-gray-500">Comma-separated. Matching paid goods jump to the top of the End Store queue with the STAT tone.</p>
        </div>
    </div>
</div>
