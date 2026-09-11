{{--
    Shared lazy-load placeholder for the chart's #[Lazy] panels (see
    ClinicalObservationsController@show). With 19 independent panels on one
    page, each mounting real HTTP calls to the Clinical Module, rendering
    them all synchronously in a single PHP request could exceed
    max_execution_time on its own — no single call has to hang for that to
    happen. #[Lazy] defers each panel's mount()/render() to its own
    follow-up request after the page paints, so the page always finishes
    fast and each panel loads independently instead of gating the rest.
--}}
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 animate-pulse">
    <div class="h-3 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
    <div class="mt-3 h-3 w-full bg-gray-100 dark:bg-gray-700/60 rounded"></div>
    <div class="mt-2 h-3 w-2/3 bg-gray-100 dark:bg-gray-700/60 rounded"></div>
</div>
