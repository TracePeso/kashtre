<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Kashtre') }} HR</title>
    <meta name="description" content="Kashtre HR workspace">
    <meta name="author" content="Kashtre Ltd">
    <meta name="theme-color" content="#011478">

    <link rel="icon" href="{{ asset('images/kashtre_logo.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
    @livewireStyles

    <script>
        if (localStorage.getItem('dark-mode') === 'true') {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        }
    </script>
</head>
<body
    class="font-inter antialiased bg-slate-50 text-slate-700 dark:bg-slate-900 dark:text-slate-50"
    :class="{ 'sidebar-expanded': sidebarExpanded }"
    x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('hr-sidebar-expanded') == null ? true : localStorage.getItem('hr-sidebar-expanded') == 'true' }"
    x-init="$watch('sidebarExpanded', value => localStorage.setItem('hr-sidebar-expanded', value))"
>
    <script>
        const hrSidebarExpanded = localStorage.getItem('hr-sidebar-expanded');
        if (hrSidebarExpanded == null || hrSidebarExpanded === 'true') {
            document.body.classList.add('sidebar-expanded');
        }
    </script>

    <div class="flex h-[100dvh] overflow-hidden">
        <x-hr.sidebar />

        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            <header class="sticky top-0 z-30 before:absolute before:inset-0 before:-z-10 before:backdrop-blur-md before:bg-white/90 dark:before:bg-slate-900/90 after:absolute after:inset-x-0 after:top-full after:h-px after:bg-slate-200 dark:after:bg-slate-700/60">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center gap-4">
                            <button
                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 shadow-sm hover:bg-slate-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white lg:hidden"
                                @click.stop="sidebarOpen = !sidebarOpen"
                                aria-controls="sidebar"
                                :aria-expanded="sidebarOpen"
                            >
                                <span class="sr-only">Open sidebar</span>
                                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                    <rect x="4" y="5" width="16" height="2" />
                                    <rect x="4" y="11" width="16" height="2" />
                                    <rect x="4" y="17" width="16" height="2" />
                                </svg>
                            </button>

                            @if (isset($header))
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Kashtre HR</p>
                                    <h1 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $header }}</h1>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <x-theme-toggle />

                            @auth
                                <x-dropdown-profile />
                            @endauth
                        </div>
                    </div>
                </div>
            </header>

            <main class="grow p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-7xl">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-slate-200 bg-white/90 py-3 dark:border-slate-700/60 dark:bg-slate-900/90">
                <div class="mx-auto max-w-7xl px-4 text-center">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Copyright {{ date('Y') }} Kashtre. All Rights Reserved</p>
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
    @filamentScripts
    @livewireScripts
</body>
</html>
