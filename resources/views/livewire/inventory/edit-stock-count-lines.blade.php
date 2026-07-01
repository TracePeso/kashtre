<div>
    {{ $this->table }}

    <p class="mt-3 text-xs text-gray-500">
        <strong>Physical</strong> = total units counted on the shelf (good + damaged + expired).
        <strong>Unaccounted</strong> = system − physical − (damaged + expired) — units missing from the shelf with no recorded cause.
        <strong>Shrinkage</strong> = unaccounted + damaged + expired (total loss recorded on this count).
        If physical equals system, unaccounted is 0 even when damaged/expired &gt; 0.
    </p>
</div>
