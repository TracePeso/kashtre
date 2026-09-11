<section class="bg-white shadow sm:rounded-lg overflow-hidden border border-indigo-100" id="evaluation-committee">
    <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50/40">
        <h3 class="text-sm font-semibold text-gray-900">Evaluation committee</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Appoint staff who will evaluate supplier quotations after this purchase request is approved.
            @if($evaluationCommitteeRequired ?? false)
                <span class="text-amber-700 font-medium">Required before submission.</span>
            @else
                Optional for your organisation.
            @endif
        </p>
    </div>

    <div class="px-5 py-4">
        @if($canManageCommittee ?? false)
            <form action="{{ route('inventory.orders.committee.update', $order) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                @include('inventory.partials.committee-member-fields', [
                    'businessUsers' => $businessUsers,
                    'selectedMemberIds' => $order->committeeMembers->pluck('user_id')->all(),
                    'chairUserId' => ($committeeChair ?? null)?->user_id,
                    'required' => $evaluationCommitteeRequired ?? false,
                ])

                <div class="flex justify-end pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Save committee
                    </button>
                </div>
            </form>
        @else
            @include('inventory.partials.order-committee-display', ['order' => $order])
        @endif
    </div>
</section>
