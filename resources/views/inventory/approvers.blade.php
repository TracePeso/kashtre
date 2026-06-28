<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">GRN Approvers</h2>
                <p class="mt-1 text-sm text-gray-500">Staff who approve RFQs and GRNs. LPO PDF copies can be emailed to finance and approvers.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        <div class="mt-8 bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Approval matrix</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Each GRN must be approved by the assigned approvers below before stock levels change. RFQs use the same approver matrix.
                </p>
            </div>

            @if($canManageApprovers)
                <form action="{{ route('inventory.approvers.update') }}" method="POST" class="px-6 py-5 space-y-5">
                    @csrf
                    @method('PUT')

                    @include('settings.inventory-module._approvers-fields', [
                        'inventoryModuleConfig' => $config,
                        'businessUsers' => $businessUsers,
                    ])

                    <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700">LPO email notifications</p>
                            <p class="text-xs text-gray-500 mt-0.5">When an LPO is issued, PDF copies are sent to these addresses (comma or newline separated).</p>
                        </div>
                        <textarea name="finance_notification_emails" rows="3"
                                  class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                  placeholder="finance@example.com, accounts@example.com">{{ old('finance_notification_emails', $config->finance_notification_emails) }}</textarea>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="lpo_email_copy_to_approvers" value="1"
                                   @checked(old('lpo_email_copy_to_approvers', $config->lpo_email_copy_to_approvers ?? true))
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Also email LPO copies to RFQ/GRN approvers
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Save approvers
                        </button>
                    </div>
                </form>
            @else
                <div class="px-6 py-5">
                    @if($config->approvers->count() > 0)
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach($config->approvers as $approver)
                                <li class="px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            Approver {{ $approver->approval_order }}: {{ $approver->user->name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $approver->user->email ?? '' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                            No GRN approvers have been assigned yet. Ask a user with Business Settings access to configure them.
                        </p>
                    @endif
                    <p class="mt-4 text-xs text-gray-500">
                        To change approvers, you need the <strong>Edit Business Settings</strong> permission.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
