<section class="bg-white shadow sm:rounded-lg overflow-hidden border border-slate-200">
    <div class="px-5 py-4 border-b border-gray-200 bg-slate-50/80">
        <h3 class="text-sm font-semibold text-gray-900">Item comments</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Internal notes for each RFQ line after supplier quotations are in — use these before generating LPOs.
        </p>
    </div>

    <form action="{{ route('inventory.orders.quotations.line-comments.store', $order) }}" method="POST" class="px-5 py-4 space-y-3">
        @csrf

        @forelse($order->lines as $index => $line)
            <div class="border border-gray-200 rounded-lg p-3">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $line->item?->name }}</p>
                        @if($line->item?->code)
                            <p class="text-xs text-gray-500">{{ $line->item->code }}</p>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 tabular-nums">
                        RFQ qty {{ number_format((float) $line->order_quantity_suom, 0) }}
                    </p>
                </div>
                <input type="hidden" name="line_comments[{{ $index }}][inventory_order_line_id]" value="{{ $line->id }}">
                <label class="sr-only" for="line-comment-{{ $line->id }}">Comment for {{ $line->item?->name }}</label>
                <textarea name="line_comments[{{ $index }}][quotation_analysis_comment]"
                          id="line-comment-{{ $line->id }}"
                          rows="2"
                          maxlength="2000"
                          placeholder="e.g. partial supply acceptable, preferred brand, delivery urgency…"
                          class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('line_comments.'.$index.'.quotation_analysis_comment', $line->quotation_analysis_comment) }}</textarea>
            </div>
        @empty
            <p class="text-sm text-gray-500 text-center py-4">No RFQ lines to comment on.</p>
        @endforelse

        @if($order->lines->isNotEmpty())
            <div class="pt-2 border-t border-gray-100">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-slate-700 hover:bg-slate-800">
                    Save item comments
                </button>
            </div>
        @endif
    </form>
</section>
