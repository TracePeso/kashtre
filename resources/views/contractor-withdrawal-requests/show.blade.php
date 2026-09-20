<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Withdrawal Request Details
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('contractor-withdrawal-requests.index', $contractorWithdrawalRequest->contractorProfile) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Withdrawal Requests
                </a>
                <a href="{{ route('contractor-balance-statement.show', $contractorWithdrawalRequest->contractorProfile) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    View Contractor Account Statement
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    
                    <!-- Request Header -->
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-900 mb-2">Withdrawal Request #{{ $contractorWithdrawalRequest->uuid }}</h3>
                                <p class="text-blue-700"><strong>Contractor:</strong> {{ $contractorWithdrawalRequest->contractorProfile->user->name ?? 'Contractor' }}</p>
                                <p class="text-blue-600"><strong>Business:</strong> {{ $contractorWithdrawalRequest->contractorProfile->business->name }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    @if($contractorWithdrawalRequest->status === 'completed') bg-green-100 text-green-800
                                    @elseif($contractorWithdrawalRequest->status === 'rejected') bg-red-100 text-red-800
                                    @elseif(in_array($contractorWithdrawalRequest->status, ['pending', 'business_approved', 'kashtre_approved', 'approved', 'processing'])) bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $contractorWithdrawalRequest->getStatusLabel() }}
                                </span>
                                <p class="text-sm text-gray-500 mt-1">{{ $contractorWithdrawalRequest->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Request Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        
                        <!-- Financial Details -->
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Financial Details</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Requested Amount:</span>
                                    <span class="font-medium">UGX {{ number_format($contractorWithdrawalRequest->amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Withdrawal Charge:</span>
                                    <span class="font-medium">UGX {{ number_format($contractorWithdrawalRequest->withdrawal_charge, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Amount (Amount + Charge):</span>
                                    <span class="font-medium">UGX {{ number_format($contractorWithdrawalRequest->amount + $contractorWithdrawalRequest->withdrawal_charge, 2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-gray-900 font-medium">Payout Amount:</span>
                                    <span class="font-bold text-green-600">UGX {{ number_format($contractorWithdrawalRequest->amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Withdrawal Type:</span>
                                    <span class="font-medium">{{ ucfirst($contractorWithdrawalRequest->withdrawal_type) }}</span>
                                </div>
                                
                            </div>
                        </div>

                        <!-- Request Information -->
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Request Information</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Requested By:</span>
                                    <span class="font-medium">{{ $contractorWithdrawalRequest->requestedBy->name ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Request Date:</span>
                                    <span class="font-medium">{{ $contractorWithdrawalRequest->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</span>
                                </div>
                                
                                @if($contractorWithdrawalRequest->kashtre_approved_at)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Kashtre Approved:</span>
                                        <span class="font-medium">{{ $contractorWithdrawalRequest->kashtre_approved_at->format('M d, Y H:i') }}</span>
                                    </div>
                                @endif
                                @if($contractorWithdrawalRequest->completed_at)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Completed:</span>
                                        <span class="font-medium">{{ $contractorWithdrawalRequest->completed_at->inOperationalTimezone()->format('M d, Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Approval Progress -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Approval Progress</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Kashtre Approval -->
                            <div class="md:col-span-2">
                                <h5 class="text-md font-medium text-gray-700 mb-2">Kashtre Approval</h5>
                                @php
                                    // Count how many steps actually have approvals (>= 1 approval per step)
                                    $step1Approved = ($contractorWithdrawalRequest->kashtre_step_1_approvals ?? 0) >= 1;
                                    $step2Approved = ($contractorWithdrawalRequest->kashtre_step_2_approvals ?? 0) >= 1;
                                    $step3Approved = ($contractorWithdrawalRequest->kashtre_step_3_approvals ?? 0) >= 1;
                                    
                                    $kashtreStepsCompleted = ($step1Approved ? 1 : 0) + ($step2Approved ? 1 : 0) + ($step3Approved ? 1 : 0);
                                    
                                    // If status is fully approved, show 3/3
                                    if ($contractorWithdrawalRequest->status === 'approved') {
                                        $kashtreStepsCompleted = 3;
                                    }
                                    
                                    $kashtreProgressPct = ($kashtreStepsCompleted / 3) * 100;
                                @endphp
                                <div class="flex items-center space-x-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $kashtreProgressPct }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">
                                        {{ $kashtreStepsCompleted }}/3
                                    </span>
                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    @if($contractorWithdrawalRequest->account_number || $contractorWithdrawalRequest->mobile_money_number)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Payment Details</h4>
                            
                            @if($contractorWithdrawalRequest->payment_method === 'bank_transfer')
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    @if($contractorWithdrawalRequest->account_number)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Account Number</label>
                                            <p class="mt-1 text-sm text-gray-900">{{ $contractorWithdrawalRequest->account_number }}</p>
                                        </div>
                                    @endif
                                    @if($contractorWithdrawalRequest->account_name)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Account Name</label>
                                            <p class="mt-1 text-sm text-gray-900">{{ $contractorWithdrawalRequest->account_name }}</p>
                                        </div>
                                    @endif
                                    @if($contractorWithdrawalRequest->bank_name)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Bank Name</label>
                                            <p class="mt-1 text-sm text-gray-900">{{ $contractorWithdrawalRequest->bank_name }}</p>
                                        </div>
                                    @endif
                                </div>
                            @elseif($contractorWithdrawalRequest->payment_method === 'mobile_money')
                                @if($contractorWithdrawalRequest->mobile_money_number)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Mobile Money Number</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $contractorWithdrawalRequest->mobile_money_number }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif

                    <!-- Reason -->
                    @if($contractorWithdrawalRequest->reason)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Reason for Withdrawal</h4>
                            <p class="text-gray-700">{{ $contractorWithdrawalRequest->reason }}</p>
                        </div>
                    @endif

                    <!-- Rejection Reason -->
                    @if($contractorWithdrawalRequest->rejection_reason)
                        <div class="bg-red-50 rounded-lg border border-red-200 p-4 mb-6">
                            <h4 class="text-lg font-medium text-red-900 mb-2">Rejection Reason</h4>
                            <p class="text-red-700">{{ $contractorWithdrawalRequest->rejection_reason }}</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
@if(Auth::user()->business_id == 1)
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-4 flex items-center justify-between">
                <div>
                    @if(in_array($contractorWithdrawalRequest->status, ['business_approved', 'kashtre_approved']))
                        @php
                            $currentStep = $contractorWithdrawalRequest->current_kashtre_step;
                            $stepLevel = $contractorWithdrawalRequest->getStepApprovalLevel($currentStep);
                            $stepLevelName = ucfirst($stepLevel); // Initiator, Authorizer, or Approver
                        @endphp
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Kashtre Approval - Step {{ $currentStep }} of 3</span>
                            <span class="text-gray-500 ml-2">({{ $stepLevelName }})</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            @if($currentStep == 1)
                                Waiting for Initiator approval
                            @elseif($currentStep == 2)
                                Waiting for Authorizer approval
                            @elseif($currentStep == 3)
                                Waiting for Approver approval
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex gap-2">
                    @if(in_array($contractorWithdrawalRequest->status, ['business_approved', 'kashtre_approved']) && $contractorWithdrawalRequest->canUserApproveAtCurrentStep(Auth::user()))
                        <form method="POST" action="{{ route('contractor-withdrawal-requests.approve', $contractorWithdrawalRequest) }}" data-action="approve">
                            @csrf
                            <button class="bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded">Approve Step</button>
                        </form>
                        <form method="POST" action="{{ route('contractor-withdrawal-requests.reject', $contractorWithdrawalRequest) }}" data-action="reject">
                            @csrf
                            <input type="hidden" name="reason" value="Rejected by Kashtre approver" />
                            <button class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded">Reject</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Approve confirmation
            document.querySelectorAll('form[data-action="approve"]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (window.Swal) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'Do you want to approve this step?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, approve',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    }
                });
            });

            // Reject confirmation
            document.querySelectorAll('form[data-action="reject"]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (window.Swal) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'Do you want to reject this request?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, reject',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    }
                });
            });
        });
    </script>
</x-app-layout>

