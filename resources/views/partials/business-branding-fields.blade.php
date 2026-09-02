@props([
    'business' => null,
    'logoRequired' => false,
    'showLogoPreview' => true,
    'idPrefix' => '',
])

@php
    $nameId = $idPrefix.'name';
    $emailId = $idPrefix.'email';
    $phoneId = $idPrefix.'phone';
    $addressId = $idPrefix.'address';
    $logoId = $idPrefix.'logo';
    $branding = $business ? \App\Support\BusinessBranding::for($business) : null;
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="{{ $nameId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Company name <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="name"
                id="{{ $nameId }}"
                value="{{ old('name', $business?->name) }}"
                required
                placeholder="Enter company name"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
            @error('name')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="{{ $emailId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Email <span class="text-red-500">*</span>
            </label>
            <input
                type="email"
                name="email"
                id="{{ $emailId }}"
                value="{{ old('email', $business?->email) }}"
                required
                placeholder="Enter contact email"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
            @error('email')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="{{ $phoneId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Phone <span class="text-red-500">*</span>
            </label>
            <input
                type="tel"
                name="phone"
                id="{{ $phoneId }}"
                value="{{ old('phone', $business?->phone) }}"
                required
                placeholder="Enter contact phone"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
            @error('phone')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="{{ $addressId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Address <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="address"
                id="{{ $addressId }}"
                value="{{ old('address', $business?->address) }}"
                required
                placeholder="Enter company address"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
            @error('address')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label for="{{ $logoId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Logo
                @if ($logoRequired)
                    <span class="text-red-500">*</span>
                @endif
            </label>

            @if($showLogoPreview && $branding?->hasLogo())
                <div class="mt-2 mb-3 flex items-center gap-4">
                    <div class="h-16 w-16 rounded-lg border border-gray-200 dark:border-gray-600 bg-white p-2 flex items-center justify-center overflow-hidden">
                        <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }} logo" class="max-h-full max-w-full object-contain">
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Upload a new file to replace the current logo.</p>
                </div>
            @endif

            <input
                type="file"
                name="logo"
                id="{{ $logoId }}"
                accept="image/*"
                @required($logoRequired)
                class="mt-1 block w-full text-gray-700 dark:text-gray-300"
            >
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF, or SVG. Max 2 MB.</p>
            @error('logo')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
