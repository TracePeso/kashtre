<div>
    @if(! $canManage)
        <p class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
            You can view space routing, but need the <strong>Edit Business Settings</strong> permission to change it.
        </p>
    @endif

    {{ $this->table }}
</div>
