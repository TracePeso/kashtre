@php
    $requireTwoFactor = old('require_2fa', $requireTwoFactor ?? true);
    if (! is_bool($requireTwoFactor)) {
        $requireTwoFactor = \App\Models\Business::parseRequireTwoFactorFlag($requireTwoFactor, true);
    }
@endphp
<div class="{{ $wrapperClass ?? 'mt-4' }}">
    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Two-factor authentication</p>
    <input type="hidden" name="require_2fa" value="0">
    <label class="flex items-start gap-3">
        <input
            type="checkbox"
            name="require_2fa"
            id="{{ $idPrefix ?? '' }}require_2fa"
            value="1"
            @checked($requireTwoFactor)
            class="mt-1 h-4 w-4 rounded border-gray-300 text-[#011478] focus:ring-[#011478]"
        >
        <span>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Require 2FA for this organisation</span>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                On by default. When off, staff can sign in with a password only and are not forced to set up 2FA.
            </p>
        </span>
    </label>
</div>
