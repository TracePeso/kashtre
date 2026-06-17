@php
    $config = $inventoryModuleConfig ?? null;
    $fixedDaily = old('fixed_daily_average_suom', $config?->fixed_daily_average_suom ?? 0);
    $safetyDays = old('safety_stock_days', $config?->safety_stock_days ?? 0);
    $bufferDays = old('buffer_stock_days', $config?->buffer_stock_days ?? 0);
    $notificationDays = old('notification_to_order_days', $config?->notification_to_order_days ?? 0);
    $periodDays = old('period_of_order_days', $config?->period_of_order_days ?? 30);
    $fyMonth = old('financial_year_start_month', $config?->financial_year_start_month ?? 1);
@endphp

<div class="border border-gray-200 rounded-lg p-4 space-y-4">
    <div>
        <p class="text-sm font-medium text-gray-900">Stock monitoring settings</p>
        <p class="text-xs text-gray-500 mt-0.5">
            Used on Monitor Stock and reports. Safety and buffer stock (SUOM) use the 15-day moving average (or fixed daily average when unavailable).
            System stock (AR) uses the financial year start month below.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="fixed_daily_average_suom" class="block text-sm font-medium text-gray-700">
                Fixed daily average (SUOM)
            </label>
            <input type="number" name="fixed_daily_average_suom" id="fixed_daily_average_suom"
                   step="0.0001" min="0"
                   value="{{ $fixedDaily }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('fixed_daily_average_suom') border-red-300 @enderror">
            @error('fixed_daily_average_suom')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="safety_stock_days" class="block text-sm font-medium text-gray-700">
                Safety stock days <span class="text-red-500">*</span>
            </label>
            <input type="number" name="safety_stock_days" id="safety_stock_days"
                   step="0.01" min="0" required
                   value="{{ $safetyDays }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('safety_stock_days') border-red-300 @enderror">
            @error('safety_stock_days')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="buffer_stock_days" class="block text-sm font-medium text-gray-700">
                Buffer stock days <span class="text-red-500">*</span>
            </label>
            <input type="number" name="buffer_stock_days" id="buffer_stock_days"
                   step="0.01" min="0" required
                   value="{{ $bufferDays }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('buffer_stock_days') border-red-300 @enderror">
            @error('buffer_stock_days')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="financial_year_start_month" class="block text-sm font-medium text-gray-700">
                Financial year starts (month)
            </label>
            <select name="financial_year_start_month" id="financial_year_start_month"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                @foreach(range(1, 12) as $month)
                    <option value="{{ $month }}" @selected((int) $fyMonth === $month)>
                        {{ \Carbon\Carbon::create(null, $month, 1)->format('F') }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Anchors system stock (AR) calculations.</p>
        </div>
        <div>
            <label for="notification_to_order_days" class="block text-sm font-medium text-gray-700">
                Notification to order (days)
            </label>
            <input type="number" name="notification_to_order_days" id="notification_to_order_days"
                   step="0.01" min="0"
                   value="{{ $notificationDays }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Lead time buffer from first stockout notice to placing an order.</p>
        </div>
        <div>
            <label for="period_of_order_days" class="block text-sm font-medium text-gray-700">
                Default period of order (days)
            </label>
            <input type="number" name="period_of_order_days" id="period_of_order_days"
                   step="0.01" min="0"
                   value="{{ $periodDays }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Default comfort period covered by each order.</p>
        </div>
    </div>

    <div class="rounded-md bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">
        <span class="font-medium text-slate-700">Excel-aligned per item:</span>
        Stock days (N) = M ÷ (15-day avg or fixed avg) ·
        Days left (AM) = N − safety − buffer ·
        Shrinkage % (AV) = 100 × (AR − M) ÷ AR
    </div>
</div>
