@if($order->committeeMembers->isNotEmpty())
    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
        @foreach($order->committeeMembers as $member)
            <li class="px-4 py-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $member->user?->name ?? '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $member->user?->email }}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $member->role === 'chair' ? 'bg-indigo-50 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                    {{ $member->roleLabel() }}
                </span>
            </li>
        @endforeach
    </ul>
@else
    <p class="text-sm text-gray-500">No evaluation committee appointed for this order.</p>
@endif
