<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Withdrawal Request Details') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('withdrawal-requests.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Requests
                </a>
                <a href="{{ route('business-balance-statement.index') }}" 
                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Balance Statement
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Request Overview -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">Withdrawal Request #{{ $withdrawalRequest->uuid }}</h3>
                            <p class="text-gray-600">Created on {{ $withdrawalRequest->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @if($withdrawalRequest->status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($withdrawalRequest->status === 'business_approved') bg-blue-100 text-blue-800
                                @elseif($withdrawalRequest->status === 'kashtre_approved') bg-indigo-100 text-indigo-800
                                @elseif($withdrawalRequest->status === 'approved') bg-green-100 text-green-800
                                @elseif($withdrawalRequest->status === 'rejected') bg-red-100 text-red-800
                                @elseif($withdrawalRequest->status === 'processing') bg-purple-100 text-purple-800
                                @elseif($withdrawalRequest->status === 'completed') bg-green-100 text-green-800
                                @elseif($withdrawalRequest->status === 'failed') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $withdrawalRequest->formatted_status }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Amount Details -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Amount Details</h4>
                            <div class="space-y-2">
                                @php
                                    // Use withdrawal_charge directly from the request (single entry approach)
                                    $actualCharge = $withdrawalRequest->withdrawal_charge ?? 0;
                                    $actualAmount = $withdrawalRequest->amount;
                                    $totalDeduction = $actualAmount + $actualCharge;
                                @endphp
                                
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Requested Amount:</span>
                                    <span class="font-semibold">{{ number_format($actualAmount, 2) }} UGX</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Withdrawal Charge:</span>
                                    <span class="font-semibold text-red-600">{{ number_format($actualCharge, 2) }} UGX</span>
                                </div>
                                
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-gray-900 font-semibold">Total Deduction:</span>
                                    <span class="font-bold text-orange-600">{{ number_format($totalDeduction, 2) }} UGX</span>
                                </div>
                            </div>
                        </div>

                        <!-- Request Details -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Request Details</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-gray-600">Type:</span>
                                    <span class="font-semibold">{{ ucfirst($withdrawalRequest->withdrawal_type) }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Requested By:</span>
                                    <span class="font-semibold">{{ $withdrawalRequest->requester->name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Business Details -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Business Details</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-gray-600">Business:</span>
                                    <span class="font-semibold">{{ $withdrawalRequest->business->name }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Account:</span>
                                    <span class="font-semibold">{{ $withdrawalRequest->business->account_number }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    @if($withdrawalRequest->bank_name || $withdrawalRequest->account_name || $withdrawalRequest->account_number || $withdrawalRequest->mobile_money_number)
                    <div class="mt-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-3">Bank Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if($withdrawalRequest->payment_method)
                            <div>
                                <span class="text-blue-700 text-sm font-medium">Payment Method:</span>
                                <span class="font-semibold text-blue-900 ml-2">{{ ucfirst(str_replace('_', ' ', $withdrawalRequest->payment_method)) }}</span>
                            </div>
                            @endif
                            @if($withdrawalRequest->bank_name)
                            <div>
                                <span class="text-blue-700 text-sm font-medium">Bank Name:</span>
                                <span class="font-semibold text-blue-900 ml-2">{{ $withdrawalRequest->bank_name }}</span>
                            </div>
                            @endif
                            @if($withdrawalRequest->account_name)
                            <div>
                                <span class="text-blue-700 text-sm font-medium">Account Name:</span>
                                <span class="font-semibold text-blue-900 ml-2">{{ $withdrawalRequest->account_name }}</span>
                            </div>
                            @endif
                            @if($withdrawalRequest->account_number)
                            <div>
                                <span class="text-blue-700 text-sm font-medium">Account Number:</span>
                                <span class="font-semibold text-blue-900 ml-2">{{ $withdrawalRequest->account_number }}</span>
                            </div>
                            @endif
                            @if($withdrawalRequest->mobile_money_number)
                            <div>
                                <span class="text-blue-700 text-sm font-medium">Mobile Money Number:</span>
                                <span class="font-semibold text-blue-900 ml-2">{{ $withdrawalRequest->mobile_money_number }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Reason -->
                    <div class="mt-6">
                        <h4 class="font-semibold text-gray-900 mb-2">Reason for Withdrawal</h4>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded-lg">{{ $withdrawalRequest->reason }}</p>
                    </div>

                    <!-- Approval Progress -->
                    <div class="mt-6">
                        <h4 class="font-semibold text-gray-900 mb-4">Approval Progress</h4>
                        @php
                            $progress = $withdrawalRequest->getApprovalProgress();
                            $currentStep = $withdrawalRequest->getCurrentApprovalStep();
                        @endphp
                        
                        <div class="space-y-4">
                            <!-- Current Step -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-blue-900">Current Step:</span>
                                    <span class="text-blue-700">{{ $currentStep }}</span>
                                </div>
                            </div>

                            <!-- Business Progress -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-semibold text-gray-700">Business Approval</span>
                                    <span class="text-sm text-gray-600">{{ $progress['business']['completed'] }}/{{ $progress['business']['total'] }} steps</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                         style="width: {{ $progress['business']['percentage'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="text-xs text-gray-500">Step 1: Initiator</span>
                                    <span class="text-xs text-gray-500">Step 2: Authorizer</span>
                                    <span class="text-xs text-gray-500">Step 3: Approver</span>
                                </div>
                            </div>

                            <!-- Kashtre Progress - Only visible to Kashtre users -->
                            @if(Auth::user()->business_id == 1)
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-semibold text-gray-700">Kashtre Approval</span>
                                    <span class="text-sm text-gray-600">{{ $progress['kashtre']['completed'] }}/{{ $progress['kashtre']['total'] }} steps</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                         style="width: {{ $progress['kashtre']['percentage'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="text-xs text-gray-500">Step 1: Initiator</span>
                                    <span class="text-xs text-gray-500">Step 2: Authorizer</span>
                                    <span class="text-xs text-gray-500">Step 3: Approver</span>
                                </div>
                            </div>
                            @endif

                            <!-- Overall Progress - Show different info for business vs Kashtre users -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-semibold text-gray-700">Overall Progress</span>
                                    @if(Auth::user()->business_id == 1)
                                        <span class="text-sm text-gray-600">{{ $progress['overall']['completed'] }}/{{ $progress['overall']['total'] }} approvals</span>
                                    @else
                                        <span class="text-sm text-gray-600">{{ $progress['business']['completed'] }}/{{ $progress['business']['total'] }} business approvals</span>
                                    @endif
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    @if(Auth::user()->business_id == 1)
                                        <div class="bg-purple-600 h-3 rounded-full transition-all duration-300" 
                                             style="width: {{ $progress['overall']['percentage'] }}%"></div>
                                    @else
                                        <div class="bg-purple-600 h-3 rounded-full transition-all duration-300" 
                                             style="width: {{ $progress['business']['percentage'] }}%"></div>
                                    @endif
                                </div>
                                <div class="text-center mt-2">
                                    @if(Auth::user()->business_id == 1)
                                        <span class="text-sm font-semibold text-gray-700">{{ $progress['overall']['percentage'] }}% Complete</span>
                                    @else
                                        <span class="text-sm font-semibold text-gray-700">{{ $progress['business']['percentage'] }}% Business Complete</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Approval Status -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Status</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Business Approval -->
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Business Approval</h4>
                            <div class="space-y-2">
                                @php
                                    // Get withdrawal setting to show assigned approvers
                                    $withdrawalSetting = \App\Models\WithdrawalSetting::where('business_id', $withdrawalRequest->business_id)
                                        ->where('is_active', true)
                                        ->first();
                                    
                                    // Get actual approvals for this request
                                    $actualApprovals = $withdrawalRequest->approvals()->with('approver')->get();
                                    
                                    // Count actual business approvals (business users only, excluding Kashtre)
                                    // This includes the initiator's approval record if they auto-approved
                                    $totalBusinessApprovals = $actualApprovals->where('approver_type', 'user')
                                        ->where('approver_level', 'business')
                                        ->where('action', 'approved')
                                        ->count();
                                @endphp
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-600">Approvals Received:</span>
                                    <span class="font-semibold">{{ $totalBusinessApprovals }} / {{ $withdrawalRequest->required_business_approvals }}</span>
                                </div>
                                
                                <!-- Level 1: Initiator -->
                                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                    <span class="text-sm text-gray-600">Initiator (Level 1):</span>
                                    <span class="font-semibold text-blue-700">✓ {{ $withdrawalRequest->requester->name }}</span>
                                </div>
                                
                                @if($withdrawalSetting)
                                    <!-- Level 2: Authorizers -->
                                    @foreach($withdrawalSetting->businessAuthorizers as $authorizer)
                                        @php
                                            $hasApproved = $actualApprovals->where('approver_id', $authorizer->approver_id)
                                                ->where('approver_type', 'user')
                                                ->where('action', 'approved')
                                                ->isNotEmpty();
                                        @endphp
                                        <div class="flex items-center justify-between p-3 {{ $hasApproved ? 'bg-green-50' : 'bg-yellow-50' }} rounded-lg">
                                            <span class="text-sm text-gray-600">Authorizer (Level 2):</span>
                                            <span class="font-semibold {{ $hasApproved ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $hasApproved ? '✓' : '⏳' }} {{ $authorizer->user->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                    
                                    <!-- Level 3: Approvers -->
                                    @foreach($withdrawalSetting->businessApprovers as $approver)
                                        @php
                                            $hasApproved = $actualApprovals->where('approver_id', $approver->approver_id)
                                                ->where('approver_type', 'user')
                                                ->where('action', 'approved')
                                                ->isNotEmpty();
                                        @endphp
                                        <div class="flex items-center justify-between p-3 {{ $hasApproved ? 'bg-green-50' : 'bg-yellow-50' }} rounded-lg">
                                            <span class="text-sm text-gray-600">Approver (Level 3):</span>
                                            <span class="font-semibold {{ $hasApproved ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $hasApproved ? '✓' : '⏳' }} {{ $approver->user->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endif
                                
                                @if($withdrawalRequest->business_approved_at)
                                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <span class="text-sm text-gray-600">Approved At:</span>
                                        <span class="font-semibold text-green-700">{{ $withdrawalRequest->business_approved_at->format('M d, Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Kashtre Approval - Only visible to Kashtre users -->
                        @if(Auth::user()->business_id == 1)
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Kashtre Approval</h4>
                            <div class="space-y-2">
                                @php
                                    // Count actual kashtre approvals (Kashtre level only)
                                    $actualKashtreApprovals = $actualApprovals->where('approver_type', 'user')
                                        ->where('approver_level', 'kashtre')
                                        ->where('action', 'approved')
                                        ->count();
                                @endphp
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-600">Approvals Received:</span>
                                    <span class="font-semibold">{{ $actualKashtreApprovals }} / {{ $withdrawalRequest->required_kashtre_approvals }}</span>
                                </div>
                                
                                @if($withdrawalSetting)
                                    <!-- Level 1: Kashtre Initiators -->
                                    @foreach($withdrawalSetting->kashtreInitiators as $initiator)
                                        @php
                                            $hasApproved = $actualApprovals->where('approver_id', $initiator->approver_id)
                                                ->where('approver_type', 'user')
                                                ->where('action', 'approved')
                                                ->isNotEmpty();
                                        @endphp
                                        <div class="flex items-center justify-between p-3 {{ $hasApproved ? 'bg-green-50' : 'bg-yellow-50' }} rounded-lg">
                                            <span class="text-sm text-gray-600">Kashtre Initiator (Level 1):</span>
                                            <span class="font-semibold {{ $hasApproved ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $hasApproved ? '✓' : '⏳' }} {{ $initiator->user->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                    
                                    <!-- Level 2: Kashtre Authorizers -->
                                    @foreach($withdrawalSetting->kashtreAuthorizers as $authorizer)
                                        @php
                                            $hasApproved = $actualApprovals->where('approver_id', $authorizer->approver_id)
                                                ->where('approver_type', 'user')
                                                ->where('action', 'approved')
                                                ->isNotEmpty();
                                        @endphp
                                        <div class="flex items-center justify-between p-3 {{ $hasApproved ? 'bg-green-50' : 'bg-yellow-50' }} rounded-lg">
                                            <span class="text-sm text-gray-600">Kashtre Authorizer (Level 2):</span>
                                            <span class="font-semibold {{ $hasApproved ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $hasApproved ? '✓' : '⏳' }} {{ $authorizer->user->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                    
                                    <!-- Level 3: Kashtre Approvers -->
                                    @foreach($withdrawalSetting->kashtreApprovers as $approver)
                                        @php
                                            $hasApproved = $actualApprovals->where('approver_id', $approver->approver_id)
                                                ->where('approver_type', 'user')
                                                ->where('action', 'approved')
                                                ->isNotEmpty();
                                        @endphp
                                        <div class="flex items-center justify-between p-3 {{ $hasApproved ? 'bg-green-50' : 'bg-yellow-50' }} rounded-lg">
                                            <span class="text-sm text-gray-600">Kashtre Approver (Level 3):</span>
                                            <span class="font-semibold {{ $hasApproved ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $hasApproved ? '✓' : '⏳' }} {{ $approver->user->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endif
                                
                                @if($withdrawalRequest->kashtre_approved_at)
                                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <span class="text-sm text-gray-600">Approved At:</span>
                                        <span class="font-semibold text-green-700">{{ $withdrawalRequest->kashtre_approved_at->format('M d, Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Approval Progress -->
                    <div class="mt-6">
                        <h4 class="font-semibold text-gray-900 mb-3">Approval Progress</h4>
                        <div class="bg-gray-200 rounded-full h-2">
                            @php
                                if (Auth::user()->business_id == 1) {
                                    $totalApprovals = $withdrawalRequest->required_business_approvals + $withdrawalRequest->required_kashtre_approvals;
                                    $receivedApprovals = $totalBusinessApprovals + $actualKashtreApprovals;
                                } else {
                                    $totalApprovals = $withdrawalRequest->required_business_approvals;
                                    $receivedApprovals = $totalBusinessApprovals;
                                }
                                $progress = $totalApprovals > 0 ? ($receivedApprovals / $totalApprovals) * 100 : 0;
                            @endphp
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">
                            @if(Auth::user()->business_id == 1)
                                {{ $receivedApprovals }} of {{ $totalApprovals }} approvals received
                            @else
                                {{ $receivedApprovals }} of {{ $totalApprovals }} business approvals received
                            @endif
                        </p>
                    </div>

                    <!-- Approval Actions -->
                    @php
                        $user = auth()->user();
                        $canApprove = false;
                        
                        // Check if user can approve this request using new step-by-step logic
                        if (in_array($withdrawalRequest->status, ['pending', 'business_approved'])) {
                            // User can approve if they are assigned to the current step (even if they created it)
                            $canApprove = $withdrawalRequest->canUserApproveAtCurrentStep($user);
                        }
                        
                        // Check if user has already approved this request at the current step
                        $currentStep = $withdrawalRequest->getCurrentStepNumber();
                        $currentLevel = $withdrawalRequest->getCurrentApprovalLevel();
                        $userHasApproved = \App\Models\WithdrawalRequestApproval::where('withdrawal_request_id', $withdrawalRequest->id)
                            ->where('approver_id', $user->id)
                            ->where('approval_step', $currentStep)
                            ->where('approver_level', $currentLevel)
                            ->where('action', 'approved')
                            ->exists();
                    @endphp

                    @if($canApprove && !$userHasApproved)
                    <div class="mt-6">
                        <h4 class="font-semibold text-gray-900 mb-3">Your Action</h4>
                        <div class="flex space-x-4">
                            <!-- Approve Form -->
                            <form action="{{ route('withdrawal-requests.approve', $withdrawalRequest) }}" method="POST" class="flex-1">
                                @csrf
                                <div class="flex space-x-2">
                                    <input type="text" name="comment" placeholder="Optional comment..." 
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <button type="submit" 
                                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Approve
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Reject Form -->
                            <form action="{{ route('withdrawal-requests.reject', $withdrawalRequest) }}" method="POST" class="flex-1">
                                @csrf
                                <div class="flex space-x-2">
                                    <input type="text" name="comment" placeholder="Reason for rejection..." 
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" required>
                                    <button type="submit" 
                                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center"
                                            onclick="return confirm('Are you sure you want to reject this request?')">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @elseif($userHasApproved)
                    <div class="mt-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-blue-800 font-medium">You have already acted on this request.</span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Approval History -->
            @if($withdrawalRequest->approvals->count() > 0)
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval History</h3>
                    
                    <div class="space-y-4">
                        @foreach($withdrawalRequest->approvals as $approval)
                            <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    @if($approval->action === 'approved')
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-semibold text-gray-900">{{ $approval->approver->name }}</h4>
                                        <span class="text-sm text-gray-500">{{ $approval->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        {{ ucfirst($approval->approver_level) }} - 
                                        {{ ucfirst($approval->action) }}
                                    </p>
                                    @if($approval->comment)
                                        <p class="text-sm text-gray-700 mt-1">{{ $approval->comment }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Rejection Reason -->
            @if($withdrawalRequest->rejection_reason)
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-red-900 mb-4">Rejection Reason</h3>
                    <p class="text-gray-700 bg-red-50 p-3 rounded-lg">{{ $withdrawalRequest->rejection_reason }}</p>
                    @if($withdrawalRequest->rejected_at)
                        <p class="text-sm text-gray-500 mt-2">Rejected on {{ $withdrawalRequest->rejected_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>

</x-app-layout>
