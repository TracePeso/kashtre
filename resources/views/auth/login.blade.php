<x-authentication-layout>
    <div class="text-center mb-6">
        <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white">Welcome back to {{env('APP_NAME')}}</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Secure access to your payment dashboard</p>
    </div>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    <!-- Login Form -->
    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div>
            <x-label for="email" value="Email Address" />
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="you@example.com" />
        </div>

        <div x-data="{ show: false }">
            <x-label for="password" value="Password" />
            <div class="relative">
                <x-input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="pr-10"
                    x-bind:type="show ? 'text' : 'password'"
                />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    x-on:click="show = !show"
                    x-bind:aria-label="show ? 'Hide password' : 'Show password'"
                    tabindex="-1"
                >
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            @if (Route::has('password.request'))
                <a class="text-sm text-blue-600 dark:text-blue-400 hover:underline" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif
        </div>

        <div>
            <x-button class="w-full justify-center">
                🔐 Sign in
            </x-button>
        </div>

        <x-validation-errors class="mt-4" />
    </form>

    <!-- Alternative Login Links -->
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="text-center space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">Are you a cashier or third-party payer?</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('cashier.login') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium">
                    Cashier Login
                </a>
                <span class="hidden sm:inline text-gray-400">|</span>
                <a href="{{ route('third-party-payer.login') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium">
                    Third-Party Payer Login
                </a>
            </div>
        </div>
    </div>
</x-authentication-layout>
