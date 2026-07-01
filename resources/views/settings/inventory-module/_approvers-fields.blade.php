@php
    $approver1Id = old('approver_1', $moduleConfig?->approvers->firstWhere('approval_order', 1)?->user_id);
    $approver2Id = old('approver_2', $moduleConfig?->approvers->firstWhere('approval_order', 2)?->user_id);
@endphp

<div class="border border-gray-200 rounded-lg p-4 space-y-6" id="grn-approvers-fields">
    <div>
        <p class="text-sm font-medium text-gray-700">GRN Approvers</p>
        <p class="text-xs text-gray-500 mt-0.5">Assign 1–2 staff from this business who will approve Goods Received Notes before stock is updated.</p>
    </div>

    <div>
        <label for="search-grn-approver-1" class="block text-sm font-medium text-gray-700 mb-2">Approver 1 <span class="text-red-500">*</span></label>
        <input type="text"
               id="search-grn-approver-1"
               placeholder="Search by name or email..."
               autocomplete="off"
               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        <div class="mt-2 space-y-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 p-2">
            @forelse($businessUsers as $user)
                <label class="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-gray-50 cursor-pointer grn-approver-1-row"
                       data-name="{{ strtolower($user->name) }}"
                       data-email="{{ strtolower($user->email) }}">
                    <input type="radio"
                           name="approver_1"
                           value="{{ $user->id }}"
                           {{ (string) $approver1Id === (string) $user->id ? 'checked' : '' }}
                           required
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                    <span class="text-sm text-gray-900">
                        {{ $user->name }}
                        <span class="text-gray-500">({{ $user->email }})</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500 px-2 py-3">No active staff found for this business.</p>
            @endforelse
        </div>
        @error('approver_1')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="search-grn-approver-2" class="block text-sm font-medium text-gray-700 mb-2">Approver 2 <span class="text-gray-400">(optional)</span></label>
        <input type="text"
               id="search-grn-approver-2"
               placeholder="Search by name or email..."
               autocomplete="off"
               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        <div class="mt-2 space-y-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 p-2">
            <label class="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-gray-50 cursor-pointer grn-approver-2-row"
                   data-name="none"
                   data-email="">
                <input type="radio"
                       name="approver_2"
                       value=""
                       {{ empty($approver2Id) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                <span class="text-sm text-gray-500 italic">— None —</span>
            </label>
            @foreach($businessUsers as $user)
                <label class="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-gray-50 cursor-pointer grn-approver-2-row"
                       data-name="{{ strtolower($user->name) }}"
                       data-email="{{ strtolower($user->email) }}">
                    <input type="radio"
                           name="approver_2"
                           value="{{ $user->id }}"
                           {{ (string) $approver2Id === (string) $user->id ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                    <span class="text-sm text-gray-900">
                        {{ $user->name }}
                        <span class="text-gray-500">({{ $user->email }})</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('approver_2')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<script>
    (function () {
        function filterRows(inputId, rowClass) {
            const input = document.getElementById(inputId);
            if (!input || input.dataset.bound === '1') {
                return;
            }
            input.dataset.bound = '1';
            input.addEventListener('input', function () {
                const term = this.value.trim().toLowerCase();
                document.querySelectorAll('.' + rowClass).forEach(function (row) {
                    const name = row.dataset.name || '';
                    const email = row.dataset.email || '';
                    const visible = !term || name.includes(term) || email.includes(term);
                    row.style.display = visible ? 'flex' : 'none';
                });
            });
        }

        filterRows('search-grn-approver-1', 'grn-approver-1-row');
        filterRows('search-grn-approver-2', 'grn-approver-2-row');
    })();
</script>
