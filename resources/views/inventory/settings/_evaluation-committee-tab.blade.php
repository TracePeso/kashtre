@if($canManage)
    <form action="{{ route('inventory.settings.evaluation-committee.update') }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-3">
            <div>
                <p class="text-sm font-medium text-gray-900">Committee requirement</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Optional. When members are listed here, they are shown on quotation analysis (before LPOs are issued) for external RFQs.
                </p>
            </div>
            <label class="inline-flex items-start gap-3 text-sm text-gray-700">
                <input type="hidden" name="evaluation_committee_required" value="0">
                <input type="checkbox" name="evaluation_committee_required" value="1"
                       @checked(old('evaluation_committee_required', $config->evaluation_committee_required ?? false))
                       class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>
                    <span class="font-medium text-gray-900">Require evaluation committee before LPOs</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        When enabled, the settings roster must be configured and is attached to the order before LPOs can be generated.
                    </span>
                </span>
            </label>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-900">Committee members</p>
            <p class="text-xs text-gray-500 mt-0.5">
                Leave empty to skip evaluation committee on orders. Add members to show the roster at quotation analysis.
            </p>
        </div>

        @include('inventory.partials.committee-member-fields', [
            'businessUsers' => $businessUsers,
            'selectedMemberIds' => $defaultCommitteeMemberIds ?? [],
            'chairUserId' => $defaultCommitteeChairId ?? null,
            'fieldPrefix' => 'committee_members',
            'chairField' => 'committee_chair_user_id',
            'required' => false,
        ])

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                Save evaluation committee settings
            </button>
        </div>
    </form>
@else
    <dl class="space-y-4 text-sm mb-5">
        <div>
            <dt class="text-gray-500">Committee required on external orders</dt>
            <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->evaluation_committee_required ?? false) ? 'Yes' : 'No (optional)' }}</dd>
        </div>
    </dl>

    @if($config->evaluationCommitteeMembers->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach($config->evaluationCommitteeMembers as $member)
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
        <p class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-md px-4 py-3">
            No evaluation committee configured. Add members above to show them at quotation analysis before LPOs are issued.
        </p>
    @endif
    <p class="mt-4 text-xs text-gray-500">
        To change these settings, you need the <strong>Edit Business Settings</strong> permission.
    </p>
@endif
