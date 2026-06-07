<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="reverb-enabled" content="0">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles        

        <script>
            if (localStorage.getItem('dark-mode') === 'false' || !('dark-mode' in localStorage)) {
                document.querySelector('html').classList.remove('dark');
                document.querySelector('html').style.colorScheme = 'light';
            } else {
                document.querySelector('html').classList.add('dark');
                document.querySelector('html').style.colorScheme = 'dark';
            }
        </script>         
    </head>
    <body class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400">

        <main class="bg-white dark:bg-gray-900">

            <!-- Content -->
            <div class="w-full">

                <div class="min-h-[100dvh] h-full">

                    <!-- Header -->
                    <div>
                        <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                            <!-- Logo -->
                            <a class="block" href="{{ route('dashboard') }}">
                                
                                 {{-- <img src="{{ asset('images/logo.png') }}"/> --}}
                                 <img src="{{ asset('images/logo.png') }}"
     alt="Logo"
     class="h-10 max-h-12 w-auto object-contain"
     style="max-width: 150px;" />

                            </a>
                        </div>
                    </div>

                    <div class="w-full max-w-3xl mx-auto px-4 py-8">
                        {{ $slot }}
                    </div>

                </div>

            </div>

            </div>

        </main>   

        @livewireScriptConfig
    </body>

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
