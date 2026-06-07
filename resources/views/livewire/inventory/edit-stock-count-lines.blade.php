<div>
    {{ $this->table }}

    <p class="mt-3 text-xs text-gray-500">
        <strong>Physical</strong> = total units counted on the shelf (good + damaged + expired).
        <strong>Verifiable</strong> = damaged + expired (on shelf but unusable).
        <strong>Unverified loss</strong> = system − physical (units missing from the shelf).
        If physical equals system, unverified is 0 even when damaged/expired &gt; 0.
    </p>
</div>
