@php
    $fieldPrefix = $fieldPrefix ?? 'committee_members';
    $chairField = $chairField ?? 'committee_chair_user_id';
    $selectedIds = collect($selectedMemberIds ?? [])->map(fn ($id) => (int) $id)->values();
    $chairId = (int) ($chairUserId ?? 0);
@endphp

<div class="space-y-3" x-data="{
    selectedIds: @js($selectedIds->all()),
    chairId: @js($chairId ?: null),
    toggleMember(id) {
        id = Number(id);
        if (this.selectedIds.includes(id)) {
            this.selectedIds = this.selectedIds.filter(memberId => memberId !== id);
            if (this.chairId === id) this.chairId = null;
        } else {
            this.selectedIds.push(id);
        }
    },
    isSelected(id) { return this.selectedIds.includes(Number(id)); },
}">
        <p class="text-xs text-gray-500">
            Members who will evaluate supplier quotations after this purchase request is approved.
            @if($required ?? false)
                <span class="text-amber-700 font-medium">Required before submission for your organisation.</span>
            @else
                <span class="text-gray-500">Optional — you may appoint members when needed.</span>
            @endif
        </p>

    <div class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100">
        @forelse($businessUsers as $user)
            <div class="px-3 py-2.5 flex flex-wrap items-center justify-between gap-2 hover:bg-gray-50">
                <label class="inline-flex items-start gap-2 cursor-pointer flex-1 min-w-0">
                    <input type="checkbox"
                           value="{{ $user->id }}"
                           class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           :checked="isSelected({{ $user->id }})"
                           @change="toggleMember({{ $user->id }})">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-900">{{ $user->name }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $user->email }}</span>
                    </span>
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 shrink-0"
                       x-show="isSelected({{ $user->id }})" x-cloak>
                    <input type="radio"
                           name="{{ $chairField }}"
                           value="{{ $user->id }}"
                           class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                           x-model.number="chairId"
                           :disabled="!isSelected({{ $user->id }})">
                    Chair
                </label>
            </div>
        @empty
            <p class="px-3 py-6 text-sm text-gray-500 text-center">No active staff found for this organisation.</p>
        @endforelse
    </div>

    <template x-for="id in selectedIds" :key="'committee-member-' + id">
        <input type="hidden" :name="'{{ $fieldPrefix }}[]'" :value="id">
    </template>

    @error('committee_members')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('committee_members.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('committee_chair_user_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('committee')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
