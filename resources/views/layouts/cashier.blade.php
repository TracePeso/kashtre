<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ env('APP_NAME', 'Kashtre') }} – Cashier Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

@php
    $showEmergencyAmbient = $callingModuleEnabled;
@endphp

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

    <x-app.emergency-ambient
        :enabled="$showEmergencyAmbient"
        :active-alert="$activeEmergencyAlert"
        :display-duration="$callingModuleConfig->emergency_display_duration ?? 0"
        :flash-on="$callingModuleConfig->emergency_flash_on ?? 3"
        :flash-off="$callingModuleConfig->emergency_flash_off ?? 1"
    />

    <!-- Page wrapper -->
    <div class="flex h-[100dvh] overflow-hidden">

        <x-cashier.sidebar />

        <!-- Content area -->
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden bg-gray-100 dark:bg-gray-900"
            x-ref="contentarea">

            <x-cashier.header />

            <main class="grow">
                {{ $slot }}
            </main>
            
            <!-- Footer -->
            <footer class="w-full bg-gray-100 text-gray-600 py-2 border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <p class="text-sm text-gray-500">© Copyright {{ date('Y') }} Kashtre. All Rights Reserved</p>
                </div>
            </footer>

        </div>

    </div>
    @livewire('notifications')
    @livewireScriptConfig
</body>

</html>
