@if($canManage)
    <form action="{{ route('inventory.settings.approvers.update') }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')

        @include('settings.inventory-module._approvers-fields', [
            'moduleConfig' => $config,
            'businessUsers' => $businessUsers,
            'showTechnicalSupervisor' => false,
        ])

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                Save approvers
            </button>
        </div>
    </form>
@else
    @if($config->approvers->count() > 0)
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach($config->approvers as $approver)
                <li class="px-4 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $approver->roleLabel() }}: {{ $approver->user->name ?? '—' }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $approver->user->email ?? '' }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
            No approvers have been assigned yet. Ask a user with Business Settings access to configure them.
        </p>
    @endif
    <p class="mt-4 text-xs text-gray-500">
        To change approvers, you need the <strong>Edit Business Settings</strong> permission.
    </p>
@endif
