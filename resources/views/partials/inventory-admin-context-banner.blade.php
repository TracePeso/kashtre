@if(!empty($inventoryAdminContextBusiness))
    <div class="bg-amber-50 border-b border-amber-200 px-4 py-2.5">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-amber-900">
                <span class="font-semibold">Admin view:</span>
                Browsing inventory for <span class="font-medium">{{ $inventoryAdminContextBusiness->name }}</span>
                <span class="text-amber-700">(read-only)</span>
            </p>
            <form action="{{ route('inventory.context.exit') }}" method="POST">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-amber-300 bg-white text-amber-900 hover:bg-amber-100">
                    Exit organisation view
                </button>
            </form>
        </div>
    </div>
@endif
