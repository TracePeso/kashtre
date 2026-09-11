<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @unless($forPdf ?? false)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @endunless
    <title>@yield('title', 'Document')</title>
    @if($forPdf ?? false)
        @include('partials.document-styles')
    @endif
    @stack('styles')
</head>
<body>
    @include('partials.document-shell-header')

    @yield('content')

    @include('partials.document-shell-footer')

    @stack('scripts')
</body>
</html>
