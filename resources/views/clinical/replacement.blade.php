<x-app-layout>
    <div class="py-8 px-4 mx-auto max-w-7xl">
        <p class="mb-4">Clinical · Daily work</p>
        <main id="clinical-replacement" data-endpoint="{{ route('clinical.replacement.workflow') }}" data-csrf="{{ csrf_token() }}">
            <p role="status">Loading clinical tasks…</p>
        </main>
        <noscript>Enable JavaScript to use the clinical worklist.</noscript>
    </div>
    <script type="module" src="{{ route('clinical.replacement.asset', ['asset' => 'main-workflow-page.mjs']) }}"></script>
</x-app-layout>
