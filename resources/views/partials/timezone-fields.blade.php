@php
    $timezoneOptions = $timezoneOptions ?? \App\Support\SharedTime::timezoneSelectOptions();
    $selectedTimezone = old($name ?? 'operational_timezone', $selectedTimezone ?? \App\Support\SharedTime::defaultTimezoneId());
    $fieldName = $name ?? 'operational_timezone';
    $allowInherit = $allowInherit ?? false;
    $required = $required ?? ! $allowInherit;
    $help = $help ?? ($allowInherit
        ? 'Leave as inherit to use the business timezone. Choose a zone only to override this branch.'
        : 'Branches inherit this timezone unless a branch sets its own override.');
@endphp

<div class="{{ $wrapperClass ?? '' }}">
    <label for="{{ $idPrefix ?? '' }}{{ $fieldName }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label ?? 'Timezone' }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <select
        name="{{ $fieldName }}"
        id="{{ $idPrefix ?? '' }}{{ $fieldName }}"
        @if($required) required @endif
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
    >
        @if($allowInherit)
            <option value="" @selected($selectedTimezone === '' || $selectedTimezone === null)>Inherit from business</option>
        @endif
        @foreach($timezoneOptions as $ianaId => $labelText)
            <option value="{{ $ianaId }}" @selected((string) $selectedTimezone === (string) $ianaId)>{{ $labelText }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @error($fieldName)
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
