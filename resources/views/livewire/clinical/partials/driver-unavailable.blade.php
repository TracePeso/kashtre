{{--
    Shown when a panel's data lives in the Clinical Module but no endpoint
    exists for it yet, so there is nothing to read. Deliberately explicit about
    *why* it is empty — "no records" and "this panel cannot reach its data" look
    identical on a chart, and only one of them is safe to act on.
--}}
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $title }}</h4>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Managed by the Clinical Module. This panel has no endpoint to read from yet, so it is
        showing nothing rather than out-of-date local data.
    </p>
    @isset($detail)
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $detail }}</p>
    @endisset
</div>
