<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900">Expired escrow write-off</h2>
        <p class="mt-1 text-sm text-gray-500">Write off stock held in expired escrow (not dispensable). Also available from Monitor Stock.</p>
        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Store</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Item</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Escrow qty</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Write off</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($levels as $level)
                        <tr>
                            <td class="px-4 py-3">{{ $level->store?->name ?? ($stores[$level->store_id] ?? $level->store_id) }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900">{{ $level->item?->name ?? '—' }}</span>
                                @if($level->item?->code)
                                    <span class="block text-xs text-gray-500">{{ $level->item->code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $level->expired_quantity_suom, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @unless(\App\Support\InventoryBusinessContext::isAdminBrowsing())
                                    <form method="POST"
                                          action="{{ route('inventory.escrow.write-off') }}"
                                          class="js-escrow-writeoff-form inline-flex items-center gap-2 justify-end"
                                          data-item="{{ $level->item?->name ?? 'this item' }}"
                                          data-store="{{ $level->store?->name ?? ($stores[$level->store_id] ?? 'this store') }}">
                                        @csrf
                                        <input type="hidden" name="store_id" value="{{ $level->store_id }}">
                                        <input type="hidden" name="item_id" value="{{ $level->item_id }}">
                                        <input type="number" step="0.0001" min="0.0001" max="{{ (float) $level->expired_quantity_suom }}"
                                               name="quantity" value="{{ (float) $level->expired_quantity_suom }}"
                                               class="js-escrow-qty w-28 rounded-md border-gray-300 text-sm text-right">
                                        <button type="submit" class="rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                                            Write off
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">Read-only</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No expired escrow quantities.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.js-escrow-writeoff-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const qtyInput = form.querySelector('.js-escrow-qty');
            const qty = qtyInput ? qtyInput.value : '';
            const item = form.dataset.item || 'this item';
            const store = form.dataset.store || 'this store';

            if (!qty || Number(qty) <= 0) {
                Swal.fire({
                    title: 'Quantity required',
                    text: 'Enter a quantity greater than zero to write off.',
                    icon: 'warning',
                    confirmButtonColor: '#dc2626',
                });
                return;
            }

            Swal.fire({
                title: 'Write off expired escrow?',
                html: `This will permanently write off <strong>${escapeHtml(qty)}</strong> of <strong>${escapeHtml(item)}</strong> at <strong>${escapeHtml(store)}</strong>. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, write off',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
</x-app-layout>
