<div>
    {{ $this->table }}

    <p class="mt-3 text-xs text-gray-500">
        <strong>Physical</strong> = total units counted on the shelf (good + damaged + expired).
        <strong>Verified</strong> = damaged + expired (on shelf but unusable).
        <strong>Unverified</strong> = system − physical − verified (units missing from the shelf with no recorded cause).
        If physical equals system, unverified is 0 even when damaged/expired &gt; 0.
    </p>
</div>
