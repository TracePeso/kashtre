@php
    $tabs = [
        ['route' => 'inventory.receive', 'label' => 'Receive Goods', 'match' => 'inventory.receive*'],
        ['route' => 'inventory.monitor', 'label' => 'Monitor Stock', 'match' => 'inventory.monitor*'],
        ['route' => 'inventory.stock-counts.index', 'label' => 'Stock Counts', 'match' => 'inventory.stock-counts*'],
        ['route' => 'inventory.consumption.index', 'label' => 'Consumption', 'match' => 'inventory.consumption*'],
        ['route' => 'inventory.approvers', 'label' => 'GRN Approvers', 'match' => 'inventory.approvers*'],
    ];
@endphp
<nav class="mt-6 border-b border-gray-200" aria-label="Inventory sections">
    <ul class="-mb-px flex flex-wrap gap-x-6 gap-y-2">
        @foreach($tabs as $tab)
            <li>
                <a href="{{ route($tab['route']) }}"
                   class="inline-block py-3 px-1 border-b-2 text-sm font-medium whitespace-nowrap {{ request()->routeIs($tab['match']) ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
