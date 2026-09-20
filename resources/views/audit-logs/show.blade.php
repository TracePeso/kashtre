<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">ID</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->id }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">UUID</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->uuid }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Action Type</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->action_type ?? 'System' }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Action</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->action }}</p>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Description</label>
        <p class="mt-1 text-sm text-gray-900">{{ $log->description ?? 'No description' }}</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Model Type</label>
            <p class="mt-1 text-sm text-gray-900">{{ class_basename($log->model_type) }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Model ID</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->model_id }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">User</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->user->name ?? 'System' }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Business</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->business->name ?? 'N/A' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">IP Address</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->ip_address ?? 'N/A' }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">User Agent</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->user_agent ?? 'N/A' }}</p>
        </div>
    </div>

    @if($log->old_values)
    <div>
        <label class="block text-sm font-medium text-gray-700">Old Values</label>
        <pre class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif

    @if($log->new_values)
    <div>
        <label class="block text-sm font-medium text-gray-700">New Values</label>
        <pre class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Created At</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->created_at->inOperationalTimezone()->format('Y-m-d H:i:s') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Updated At</label>
            <p class="mt-1 text-sm text-gray-900">{{ $log->updated_at->inOperationalTimezone()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</div>
