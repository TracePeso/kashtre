@php
    $sendPasswordReset = old('send_password_reset', $sendPasswordReset ?? true);
    if (! is_bool($sendPasswordReset)) {
        $sendPasswordReset = \App\Models\Business::parseSendPasswordResetFlag($sendPasswordReset, true);
    }
@endphp
<div class="{{ $wrapperClass ?? 'mt-4' }}">
    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Imported user passwords</p>
    <input type="hidden" name="send_password_reset" value="0">
    <label class="flex items-start gap-3">
        <input
            type="checkbox"
            name="send_password_reset"
            id="{{ $idPrefix ?? '' }}send_password_reset"
            value="1"
            @checked($sendPasswordReset)
            class="mt-1 h-4 w-4 rounded border-gray-300 text-[#011478] focus:ring-[#011478]"
        >
        <span>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Send a password reset link</span>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                On by default. Each imported user is emailed a link to set their own password.
                Turn off so every imported user can sign in with the default password <span class="font-mono">password</span>.
            </p>
        </span>
    </label>
</div>
