@php
    $config = $moduleConfig ?? null;
    $fixedDaily = old('fixed_daily_average_suom', $config?->fixed_daily_average_suom ?? 0);
    $safetyDays = old('safety_stock_days', $config?->safety_stock_days ?? 0);
    $bufferDays = old('buffer_stock_days', $config?->buffer_stock_days ?? 0);
    $notificationDays = old('notification_to_order_days', $config?->notification_to_order_days ?? 0);
@endphp

<div class="border border-gray-200 rounded-lg p-4 space-y-4">
    <div>
        <p class="text-sm font-medium text-gray-900">Stock monitoring settings</p>
        <p class="text-xs text-gray-500 mt-0.5">
            Used on Monitor Stock and reports. Safety and buffer stock use the 15-day moving average (or fixed daily average when unavailable).
            Financial year is configured under Business Settings.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="fixed_daily_average_suom" class="block text-sm font-medium text-gray-700">
                Fixed daily average (sale units)
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

    <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">
        <div>
            <label for="notification_to_order_days" class="block text-sm font-medium text-gray-700">
                Notification to order (days)
            </label>
            <input type="number" name="notification_to_order_days" id="notification_to_order_days"
                   step="0.01" min="0"
                   value="{{ $notificationDays }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
    </div>
</div>
