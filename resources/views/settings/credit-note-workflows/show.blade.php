<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-4">
                        <li>
                            <div>
                                <a href="{{ route('credit-note-workflows.index') }}" class="text-gray-400 hover:text-gray-500">
                                    <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                    </svg>
                                    <span class="sr-only">Refund Workflows</span>
                                </a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-4 text-sm font-medium text-gray-500">View</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Refund Workflow Details
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $creditNoteWorkflow->business->name }}
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                @if(in_array('Edit Credit Note Workflows', auth()->user()->permissions ?? []))
                <a href="{{ route('credit-note-workflows.edit', $creditNoteWorkflow) }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Workflow
                </a>
                @endif
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6 space-y-6">
                <!-- Business Info -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Business</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $creditNoteWorkflow->business->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $creditNoteWorkflow->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $creditNoteWorkflow->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $creditNoteWorkflow->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $creditNoteWorkflow->updated_at->inOperationalTimezone()->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Workflow Approvers -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Workflow Approvers</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Default Technical Supervisor</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $creditNoteWorkflow->defaultSupervisor ? $creditNoteWorkflow->defaultSupervisor->name . ' (' . $creditNoteWorkflow->defaultSupervisor->email . ')' : 'Not Set' }}
                            </dd>
                            <dd class="mt-1 text-xs text-gray-500">Step 1: Verifies refund requests</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Authorizers</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @php
                                    $authorizerList = $creditNoteWorkflow->authorizers->map(fn($user) => $user->name . ' (' . $user->email . ')');
                                @endphp
                                @if($authorizerList->isNotEmpty())
                                    <ul class="space-y-1">
                                        @foreach($authorizerList as $name)
                                            <li>{{ $name }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-gray-400">Not Set</span>
                                @endif
                            </dd>
                            <dd class="mt-1 text-xs text-gray-500">Step 2: Authorizes refund (1-3 members)</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Approvers</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @php
                                    $approverList = $creditNoteWorkflow->approvers->map(fn($user) => $user->name . ' (' . $user->email . ')');
                                @endphp
                                @if($approverList->isNotEmpty())
                                    <ul class="space-y-1">
                                        @foreach($approverList as $name)
                                            <li>{{ $name }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-gray-400">Not Set</span>
                                @endif
                            </dd>
                            <dd class="mt-1 text-xs text-gray-500">Step 3: Final approval (1-3 members)</dd>
                        </div>
                    </dl>
                </div>

                <!-- Service Point Supervisors -->
                @if($servicePoints->count() > 0)
                <div class="border-t border-gray-200 pt-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Supervisor Reassignment Capability
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>Supervisors assigned to service points can <strong>reassign "in progress" items</strong> from one user to another for better workload management.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Service Point Supervisors</h3>
                    <div class="space-y-4">
                        @foreach($servicePoints as $servicePoint)
                            @php
                                $assignments = $servicePointSupervisors->get($servicePoint->id, collect());
                                $assignedSupervisors = collect($assignments)->map(fn($assignment) => $assignment->supervisor)->filter();
                            @endphp
                            <div class="border border-gray-200 rounded-md p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">
                                            {{ $servicePoint->name }}
                                            @if($servicePoint->description)
                                                <span class="text-gray-500 text-xs">({{ $servicePoint->description }})</span>
                                            @endif
                                        </h4>
                                        <div class="mt-2 text-sm text-gray-600">
                                            <span class="font-medium">Supervisors:</span>
                                            @if($assignedSupervisors->isNotEmpty())
                                                <ul class="mt-1 space-y-1">
                                                    @foreach($assignedSupervisors as $supervisorUser)
                                                        <li>{{ $supervisorUser->name }} ({{ $supervisorUser->email }})</li>
                                                    @endforeach
                                                </ul>
                                            @elseif($creditNoteWorkflow->defaultSupervisor)
                                                <span class="text-gray-500">Default: {{ $creditNoteWorkflow->defaultSupervisor->name }} ({{ $creditNoteWorkflow->defaultSupervisor->email }})</span>
                                            @else
                                                <span class="text-gray-400">Not Set</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Up to 4 supervisors can reassign in-progress items
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="border-t border-gray-200 pt-6">
                    <p class="text-sm text-gray-500">No service points found for this business.</p>
                </div>
                @endif

                <!-- Actions -->
                <div class="border-t border-gray-200 pt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('credit-note-workflows.index') }}" 
                       class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Back to List
                    </a>
                    @if(in_array('Edit Credit Note Workflows', auth()->user()->permissions ?? []))
                    <a href="{{ route('credit-note-workflows.edit', $creditNoteWorkflow) }}" 
                       class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Edit Workflow
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>

