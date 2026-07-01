@php
    $fyMonth = (int) old('financial_year_start_month', $business?->financial_year_start_month ?? 1);
    $fyDay = (int) old('financial_year_start_day', $business?->financial_year_start_day ?? 1);
    $monthId = ($idPrefix ?? '').'financial_year_start_month';
    $dayId = ($idPrefix ?? '').'financial_year_start_day';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="{{ $monthId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Financial year starts (month) <span class="text-red-500">*</span>
        </label>
        <select name="financial_year_start_month" id="{{ $monthId }}" required
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            @foreach(range(1, 12) as $month)
                <option value="{{ $month }}" @selected($fyMonth === $month)>
                    {{ \Carbon\Carbon::create(null, $month, 1)->format('F') }}
                </option>
            @endforeach
        </select>
        @error('financial_year_start_month')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $dayId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Financial year starts (day) <span class="text-red-500">*</span>
        </label>
        <input type="number" name="financial_year_start_day" id="{{ $dayId }}"
               min="1" max="31" required value="{{ $fyDay }}"
               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        @error('financial_year_start_day')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

@if(isset($showCurrentPeriod) && $showCurrentPeriod && $business)
    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
        Current financial year:
        <span class="font-medium text-gray-900 dark:text-gray-100">
            {{ app(\App\Services\FinancialYearService::class)->periodLabel($business) }}
        </span>
    </p>
@endif
