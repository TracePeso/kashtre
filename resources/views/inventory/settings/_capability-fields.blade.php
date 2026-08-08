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
                <span class="block text-xs text-gray-500">Crash cart satellites with Ready → Deployed → Reconciling status, plus Crash cart usage on Record Usage.</span>
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
                <span class="block text-xs text-gray-500">Track batch or lot numbers on stock movements (coming later).</span>
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
                <span class="block text-xs text-gray-500">Track individual serial numbers (coming later).</span>
            </span>
        </label>
    </div>
</div>
