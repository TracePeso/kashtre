<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Credit Limit Change Requests') }}
            </h2>
            @php
                $isInitiator = in_array('initiator', $userApproverLevels ?? []);
            @endphp
            @if($isInitiator)
                <a href="{{ route('credit-limit-requests.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    + New Request
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Tabs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        @php
                            $queryParams = request()->except(['status', 'page']);
                            $queryParams['status'] = 'all';
                        @endphp
                        <a href="{{ route('credit-limit-requests.index', $queryParams) }}" 
                           class="{{ (request('status', 'all') === 'all') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            All <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">{{ $counts['all'] ?? 0 }}</span>
                        </a>
                        @if(isset($myPendingRequests['authorizations']) && $myPendingRequests['authorizations'] > 0)
                            @php $queryParams['status'] = 'initiated'; @endphp
                            <a href="{{ route('credit-limit-requests.index', $queryParams) }}" 
                               class="{{ request('status') === 'initiated' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Pending Authorization <span class="ml-2 bg-yellow-100 text-yellow-600 py-0.5 px-2 rounded-full text-xs">{{ $myPendingRequests['authorizations'] }}</span>
                            </a>
                        @endif
                        @if(isset($myPendingRequests['approvals']) && $myPendingRequests['approvals'] > 0)
                            @php $queryParams['status'] = 'authorized'; @endphp
                            <a href="{{ route('credit-limit-requests.index', $queryParams) }}" 
                               class="{{ request('status') === 'authorized' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Pending Approval <span class="ml-2 bg-blue-100 text-blue-600 py-0.5 px-2 rounded-full text-xs">{{ $myPendingRequests['approvals'] }}</span>
                            </a>
                        @endif
                        @php $queryParams['status'] = 'approved'; @endphp
                        <a href="{{ route('credit-limit-requests.index', $queryParams) }}" 
                           class="{{ request('status') === 'approved' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Approved <span class="ml-2 bg-green-100 text-green-600 py-0.5 px-2 rounded-full text-xs">{{ $counts['approved'] ?? 0 }}</span>
                        </a>
                        @php $queryParams['status'] = 'rejected'; @endphp
                        <a href="{{ route('credit-limit-requests.index', $queryParams) }}" 
                           class="{{ request('status') === 'rejected' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Rejected <span class="ml-2 bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs">{{ $counts['rejected'] ?? 0 }}</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ route('credit-limit-requests.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Search Input -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <input type="text" 
                                       name="search" 
                                       id="search" 
                                       value="{{ request('search') }}" 
                                       placeholder="Search by entity name, phone, email, UUID, or reason..."
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Entity Type Filter -->
                            <div>
                                <label for="entity_type" class="block text-sm font-medium text-gray-700 mb-1">Entity Type</label>
                                <select name="entity_type" 
                                        id="entity_type" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="all" {{ request('entity_type', 'all') === 'all' ? 'selected' : '' }}>All Types</option>
                                    <option value="client" {{ request('entity_type') === 'client' ? 'selected' : '' }}>Clients</option>
                                    <option value="third_party_payer" {{ request('entity_type') === 'third_party_payer' ? 'selected' : '' }}>Third Party Payers</option>
                                </select>
                            </div>

                            <!-- Status Filter (preserve existing status filter) -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" 
                                        id="status" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Status</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="initiated" {{ request('status') === 'initiated' ? 'selected' : '' }}>Initiated</option>
                                    <option value="authorized" {{ request('status') === 'authorized' ? 'selected' : '' }}>Authorized</option>
                                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                Search & Filter
                            </button>
                            @if(request('search') || (request('entity_type') && request('entity_type') !== 'all') || (request('status') && request('status') !== 'all'))
                                <a href="{{ route('credit-limit-requests.index') }}" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                                    Clear Filters
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($requests->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Limit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested Limit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Initiated By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($requests as $request)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $request->entity_name }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ ucfirst(str_replace('_', ' ', $request->entity_type)) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                UGX {{ number_format($request->current_credit_limit, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                UGX {{ number_format($request->requested_credit_limit, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @php
                                                    $changeAmount = $request->requested_credit_limit - $request->current_credit_limit;
                                                    $isIncrease = $changeAmount > 0;
                                                @endphp
                                                <span class="{{ $isIncrease ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $isIncrease ? '+' : '' }}UGX {{ number_format($changeAmount, 2) }}
                                                </span>
                                                <span class="text-xs text-gray-500 ml-1">
                                                    ({{ $isIncrease ? 'Upgrade' : 'Downgrade' }})
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                    @if($request->status === 'approved') bg-green-100 text-green-800
                                                    @elseif($request->status === 'rejected') bg-red-100 text-red-800
                                                    @elseif($request->status === 'authorized') bg-blue-100 text-blue-800
                                                    @elseif($request->status === 'initiated') bg-yellow-100 text-yellow-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    {{ ucfirst($request->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $request->initiatedBy->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $request->created_at->inOperationalTimezone()->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    @php
                                                        // Check if current user can approve/authorize this request
                                                        $userApproval = $request->approvals()
                                                            ->where('approver_id', auth()->id())
                                                            ->whereNull('action')
                                                            ->first();
                                                        $canAct = $userApproval && (
                                                            ($userApproval->approval_level === 'authorizer' && $request->status === 'initiated' && $request->current_step == 2) ||
                                                            ($userApproval->approval_level === 'approver' && $request->status === 'authorized' && $request->current_step == 3)
                                                        );
                                                    @endphp
                                                    
                                                    @if($canAct)
                                                        <form action="{{ route('credit-limit-requests.approve', $request) }}" method="POST" class="inline" onsubmit="return quickApprove(event, this, {{ $request->id }})">
                                                            @csrf
                                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <button type="button" onclick="quickReject({{ $request->id }})" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                            Reject
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('credit-limit-requests.show', $request) }}" class="text-blue-600 hover:text-blue-900">
                                                        View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $requests->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">No credit limit change requests found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        async function quickApprove(event, form, requestId) {
            event.preventDefault();
            
            const result = await Swal.fire({
                title: 'Approve Request?',
                text: 'Are you sure you want to approve this credit limit change request?',
                icon: 'question',
                input: 'textarea',
                inputLabel: 'Comment (optional)',
                inputPlaceholder: 'Enter your comment...',
                inputAttributes: {
                    'aria-label': 'Enter your comment'
                },
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Approve',
                cancelButtonText: 'Cancel'
            });

            if (!result.isConfirmed) {
                return false;
            }

            // Add comment to form if provided
            if (result.value) {
                const commentInput = document.createElement('input');
                commentInput.type = 'hidden';
                commentInput.name = 'comment';
                commentInput.value = result.value;
                form.appendChild(commentInput);
            }

            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Approving request',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form
            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to approve request');
                }

                Swal.fire({
                    title: 'Success!',
                    text: data.message || 'Request approved successfully',
                    icon: 'success',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });

            } catch (error) {
                console.error('Error approving request:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to approve request. Please try again.'
                });
            }

            return false;
        }

        async function quickReject(requestId) {
            const { value: formValues } = await Swal.fire({
                title: 'Reject Request',
                input: 'textarea',
                inputLabel: 'Rejection Reason (required)',
                inputPlaceholder: 'Please provide a reason for rejection...',
                inputAttributes: {
                    'aria-label': 'Enter rejection reason'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to provide a rejection reason!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Reject Request',
                cancelButtonText: 'Cancel'
            });

            if (!formValues) {
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Rejecting request',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('rejection_reason', formValues);

                const response = await fetch(`/credit-limit-requests/${requestId}/reject`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to reject request');
                }

                Swal.fire({
                    title: 'Rejected!',
                    text: data.message || 'Request rejected successfully',
                    icon: 'success',
                    confirmButtonColor: '#ef4444'
                }).then(() => {
                    window.location.reload();
                });

            } catch (error) {
                console.error('Error rejecting request:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to reject request. Please try again.'
                });
            }
        }
    </script>
    @endpush
</x-app-layout>

