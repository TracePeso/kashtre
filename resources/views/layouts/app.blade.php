<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="user-id" content="{{ Auth::id() }}">
    <meta name="business-id" content="{{ Auth::user()->business_id }}">
    @endauth

    <title>{{ config('app.name', 'Kashtre') }}</title>
    <title>{{ env('APP_NAME', 'Kashtre') }} – Smart Payments and Collections Platform</title>
    <meta name="title" content="Kashtre – Smart Payments and Collections Platform">
    <meta name="description"
        content="Kashtre is a powerful platform for managing digital transactions, collections, and payouts with ease. Trusted by businesses across Africa.">
    <meta name="keywords"
        content="Kashtre, payments, digital wallet, collections, payouts, mobile money, financial platform, business payments, bulk payments, Uganda fintech">
    <meta name="author" content="Kashtre Ltd">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#011478" />
    <meta property="og:title" content="Kashtre – Smart Payments and Collections Platform" />
    <meta property="og:description"
        content="Kashtre enables businesses to send and receive payments securely through mobile money and bank integrations." />
    <meta property="og:image" content="{{ asset('images/logo.png') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Kashtre – Smart Payments and Collections Platform">
    <meta name="twitter:description"
        content="Kashtre simplifies business payments and collections for growing organizations.">
    <meta name="twitter:image" content="{{ asset('images/logo.png') }}">


    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @filamentStyles
    <!-- Styles -->
    @livewireStyles


</head>

<body class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400"
    :class="{ 'sidebar-expanded': sidebarExpanded }" x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('sidebar-expanded') == null ? true : localStorage.getItem('sidebar-expanded') == 'true' }" x-init="$watch('sidebarExpanded', value => localStorage.setItem('sidebar-expanded', value))">

    <script>
        const expanded = localStorage.getItem('sidebar-expanded');
        if (expanded == null) {
            localStorage.setItem('sidebar-expanded', 'true');
            document.querySelector('body').classList.add('sidebar-expanded');
        } else if (expanded === 'true') {
            document.querySelector('body').classList.add('sidebar-expanded');
        } else {
            document.querySelector('body').classList.remove('sidebar-expanded');
        }
    </script>

    <!-- Page wrapper -->
    <div class="flex h-[100dvh] overflow-hidden">
        <x-app.sidebar :variant="$attributes['sidebarVariant']" />

        <!-- Content area -->
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden @if ($attributes['background']) {{ $attributes['background'] }} @endif"
            x-ref="contentarea">

            <x-app.header :variant="$attributes['headerVariant']" />

            <main class="grow">
                @include('partials.inventory-admin-context-banner')
                {{ $slot }}
            </main>
            <!-- Footer -->
            <x-kashtre.cash-tray />

        </div>

    </div>
    @livewire('notifications')
    @filamentScripts
    @livewireScriptConfig
</body>

<div class="w-full bg-black text-white text-sm overflow-hidden fixed top-0 z-50">

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript">
    {{-- Success Message --}}
    @if (Session::has('success'))
        Swal.fire({
            icon: 'success',
            title: 'Done',
            text: '{{ Session::get('success') }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
    {{-- Warning Message -- Show in all environments --}}
    @if (Session::has('warning'))
        Swal.fire({
            icon: 'warning',
            title: 'Security Required',
            text: '{{ Session::get('warning') }}',
            confirmButtonText: 'Setup 2FA',
            confirmButtonColor: "#f59e0b",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: false
        });
    @endif
    {{-- Errors Message --}}
    @if (Session::has('error'))
        Swal.fire({
            icon: 'error',
            title: 'Opps!!!',
            text: '{{ Session::get('error') }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
    @if (Session::has('errors') || (isset($errors) && is_array($errors) && $errors->any()))
        Swal.fire({
            icon: 'error',
            title: 'Opps!!!',
            text: '{{ Session::get('errors')->first() }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
</script>

</html>
