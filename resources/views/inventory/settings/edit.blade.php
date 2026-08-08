<x-app-layout>
@php
    $activeTab = $activeTab ?? request()->query('tab', 'notifications');
    if (! in_array($activeTab, ['notifications', 'approvers', 'evaluation-committee', 'space-routing', 'capabilities'], true)) {
        $activeTab = 'notifications';
    }
    $notificationsTabUrl = route('inventory.settings.edit', ['tab' => 'notifications']);
    $approversTabUrl = route('inventory.settings.edit', ['tab' => 'approvers']);
    $evaluationCommitteeTabUrl = route('inventory.settings.edit', ['tab' => 'evaluation-committee']);
    $spaceRoutingTabUrl = route('inventory.settings.edit', ['tab' => 'space-routing']);
    $capabilitiesTabUrl = route('inventory.settings.edit', ['tab' => 'capabilities']);
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="{{ $activeTab === 'space-routing' ? 'max-w-6xl' : 'max-w-4xl' }} mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Inventory settings</h2>
                <p class="mt-1 text-sm text-gray-500">Approvers, evaluation committee, space routing, capabilities, and email notifications for your organisation.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        <div class="mt-8 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex flex-wrap" aria-label="Inventory settings tabs">
                    <a href="{{ $notificationsTabUrl }}"
                       class="flex-1 min-w-[8rem] py-4 px-3 text-center border-b-2 font-medium text-sm {{ $activeTab === 'notifications' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Notifications
                    </a>
                    <a href="{{ $approversTabUrl }}"
                       class="flex-1 min-w-[8rem] py-4 px-3 text-center border-b-2 font-medium text-sm {{ $activeTab === 'approvers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Approvers
                    </a>
                    <a href="{{ $evaluationCommitteeTabUrl }}"
                       class="flex-1 min-w-[8rem] py-4 px-3 text-center border-b-2 font-medium text-sm {{ $activeTab === 'evaluation-committee' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Evaluation committee
                    </a>
                    <a href="{{ $spaceRoutingTabUrl }}"
                       class="flex-1 min-w-[8rem] py-4 px-3 text-center border-b-2 font-medium text-sm {{ $activeTab === 'space-routing' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Space routing
                    </a>
                    <a href="{{ $capabilitiesTabUrl }}"
                       class="flex-1 min-w-[8rem] py-4 px-3 text-center border-b-2 font-medium text-sm {{ $activeTab === 'capabilities' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Capabilities
                    </a>
                </nav>
            </div>

            @if($activeTab === 'notifications')
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="text-lg font-medium text-gray-900">Email notifications</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Turn individual notification types on or off. Disabled notifications are skipped entirely — no email is sent.
                    </p>
                </div>

                @if($canManage)
                    <form action="{{ route('inventory.settings.update') }}" method="POST" class="px-6 py-5 space-y-6">
                        @csrf
                        @method('PUT')

                        @include('inventory.settings._notification-fields', ['config' => $config])

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Save notification settings
                            </button>
                        </div>
                    </form>
                @else
                    <div class="px-6 py-5 space-y-6">
                        <dl class="space-y-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Notify approvers on order submitted</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_approvers_on_order_submitted ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Notify finance on order submitted</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_finance_on_order_submitted ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Notify next approver after each step</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_next_approver_on_approval ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Notify on full approval</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_on_order_fully_approved ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Email RFQ to suppliers on approval</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_suppliers_on_rfq_approved ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">LPO emails</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->notify_on_lpo_issued ?? true) ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Finance notification emails</dt>
                                <dd class="mt-0.5 text-gray-900">
                                    @php($emails = $config->financeNotificationEmailList())
                                    @if($emails !== [])
                                        {{ implode(', ', $emails) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Copy LPO to approvers</dt>
                                <dd class="mt-0.5 font-medium text-gray-900">{{ ($config->lpo_email_copy_to_approvers ?? true) ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                        <p class="text-xs text-gray-500 border-t border-gray-100 pt-4">
                            To change these settings, you need the <strong>Edit Business Settings</strong> permission.
                        </p>
                    </div>
                @endif
            @elseif($activeTab === 'approvers')
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="text-lg font-medium text-gray-900">Approval matrix</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Assign Approver 1 and optional Approver 2 for RFQs, stock transfers, stock counts, and goods receive notes.
                        Technical supervisor is chosen separately on each goods receive note when creating it.
                    </p>
                </div>

                <div class="px-6 py-5">
                    @include('inventory.settings._approvers-tab', [
                        'config' => $config,
                        'businessUsers' => $businessUsers,
                        'canManage' => $canManage,
                    ])
                </div>
            @elseif($activeTab === 'space-routing')
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="text-lg font-medium text-gray-900">Client Space → End Store routing</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        When payment confirms for goods, the inventory queue stamps each line with the End Store mapped to that Client Space.
                        Choose outpatient (immediate pick) or inpatient (batch &amp; stage) strategy per space.
                    </p>
                </div>

                <div class="px-6 py-5 space-routing-filament">
                    <style>
                        .space-routing-filament .fi-ta-header-toolbar .fi-btn,
                        .space-routing-filament .fi-ta-empty-state-actions .fi-btn {
                            background-color: rgb(37 99 235);
                            color: white;
                        }
                        .space-routing-filament .fi-ta-header-toolbar .fi-btn:hover,
                        .space-routing-filament .fi-ta-empty-state-actions .fi-btn:hover {
                            background-color: rgb(29 78 216);
                        }
                        .space-routing-filament .fi-ta-header-toolbar .fi-btn-label,
                        .space-routing-filament .fi-ta-empty-state-actions .fi-btn-label {
                            color: white;
                        }
                    </style>
                    @livewire('inventory.list-client-space-store-assignments')
                </div>
            @elseif($activeTab === 'capabilities')
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="text-lg font-medium text-gray-900">Capabilities</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Turn optional inventory features on or off for this organisation. Disabled features are hidden from day-to-day screens.
                    </p>
                </div>

                @if($canManage)
                    <form action="{{ route('inventory.settings.capabilities.update') }}" method="POST" class="px-6 py-5 space-y-5">
                        @csrf
                        @method('PUT')

                        @include('inventory.settings._capability-fields', ['config' => $config])

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Save capabilities
                            </button>
                        </div>
                    </form>
                @else
                    <div class="px-6 py-5">
                        @include('inventory.settings._capability-fields', ['config' => $config, 'readOnly' => true])
                    </div>
                @endif
            @else
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="text-lg font-medium text-gray-900">Default evaluation committee</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Default members appointed to evaluate supplier quotations on external purchase orders.
                        Committee appointment is optional unless you enable the requirement below.
                    </p>
                </div>

                <div class="px-6 py-5">
                    @include('inventory.settings._evaluation-committee-tab', [
                        'config' => $config,
                        'businessUsers' => $businessUsers,
                        'canManage' => $canManage,
                        'defaultCommitteeMemberIds' => $defaultCommitteeMemberIds ?? [],
                        'defaultCommitteeChairId' => $defaultCommitteeChairId ?? null,
                    ])
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
