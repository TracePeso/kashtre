<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-4">
                        <li>
                            <div>
                                <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="text-gray-400 hover:text-gray-500">
                                    <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                    </svg>
                                    <span class="sr-only">Maturation Periods</span>
                                </a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-4 text-sm font-medium text-gray-500">Edit</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Edit Maturation Period
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $maturationPeriod->business->name }} - {{ $maturationPeriod->payment_method_name }}
                </p>
            </div>
        </div>

        <!-- Alert Messages -->
        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Form -->
        <div class="mt-8">
            <div class="bg-white shadow sm:rounded-lg">
                <form action="{{ route('maturation-periods.update', $maturationPeriod) }}" method="POST" class="px-4 py-5 sm:p-6">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Business Selection -->
                        <div>
                            <label for="business_id" class="block text-sm font-medium text-gray-700">
                                Entity (Business) <span class="text-red-500">*</span>
                            </label>
                            <select name="business_id" id="business_id" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('business_id') border-red-300 @enderror">
                                <option value="">Select an entity...</option>
                                @foreach($businesses as $business)
                                    <option value="{{ $business->id }}" {{ (old('business_id', $maturationPeriod->business_id) == $business->id) ? 'selected' : '' }}>
                                        {{ $business->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('business_id')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select name="payment_method" id="payment_method" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('payment_method') border-red-300 @enderror">
                                <option value="">Select payment method...</option>
                                @foreach($paymentMethods as $method)
                                    @php
                                        $methodNames = [
                                            'insurance' => 'Insurance',
                                            'credit_arrangement' => 'Credit Arrangement',
                                            'mobile_money' => 'Mobile Money',
                                            'v_card' => 'V Card (Virtual Card)',
                                            'p_card' => 'P Card (Physical Card)',
                                            'bank_transfer' => 'Bank Transfer',
                                            'cash' => 'Cash',
                                        ];
                                        $displayName = $methodNames[$method] ?? ucfirst(str_replace('_', ' ', $method));
                                    @endphp
                                    <option value="{{ $method }}" {{ (old('payment_method', $maturationPeriod->payment_method) == $method) ? 'selected' : '' }}>
                                        {{ $displayName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Maturation Days -->
                        <div>
                            <label for="maturation_days" class="block text-sm font-medium text-gray-700">
                                Maturation Period (Days) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="maturation_days" id="maturation_days" 
                                   value="{{ old('maturation_days', $maturationPeriod->maturation_days) }}" min="0" max="365" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('maturation_days') border-red-300 @enderror"
                                   placeholder="Enter number of days (0-365)">
                            @error('maturation_days')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-sm text-gray-500">Number of days before the payment is considered matured (0-365 days)</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">
                                Description
                            </label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                                      placeholder="Optional description for this maturation period setting">{{ old('description', $maturationPeriod->description) }}</textarea>
                            @error('description')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Account Information - Only for mobile_money and bank_transfer -->
                        <div id="account-section" style="{{ in_array(old('payment_method', $maturationPeriod->payment_method), ['mobile_money', 'bank_transfer']) ? 'display: block;' : 'display: none;' }}">
                            <div class="border-t border-gray-200 pt-4 mt-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Method Account</h3>
                                <p class="text-sm text-gray-600 mb-4">A payment method account will be automatically created or used for this payment method. When clients pay, the system will debit this account and credit the client account.</p>
                                
                                <!-- Existing Account Display (if exists) -->
                                <div id="existing_account_display" style="{{ $maturationPeriod->paymentMethodAccount ? 'display: block;' : 'display: none;' }}" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                                    <p class="text-sm font-medium text-gray-900 mb-1">Account:</p>
                                    <p class="text-sm text-gray-700" id="existing_account_info">
                                        @if($maturationPeriod->paymentMethodAccount)
                                            {{ $maturationPeriod->paymentMethodAccount->name }}
                                            @if($maturationPeriod->paymentMethodAccount->provider) ({{ $maturationPeriod->paymentMethodAccount->provider }}) @endif
                                            @if($maturationPeriod->paymentMethodAccount->balance != 0)
                                                - Balance: {{ number_format($maturationPeriod->paymentMethodAccount->balance, 2) }} {{ $maturationPeriod->paymentMethodAccount->currency ?? 'UGX' }}
                                            @endif
                                        @endif
                                    </p>
                                    <input type="hidden" name="payment_method_account_id" id="payment_method_account_id" value="{{ old('payment_method_account_id', $maturationPeriod->payment_method_account_id) }}">
                                </div>
                                
                                <!-- New Account Fields (if will be created) -->
                                <div id="new_account_fields" style="display: none;" class="space-y-4">
                                    <p class="text-sm font-medium text-gray-900 mb-2">Account Details:</p>
                                    
                                    <div>
                                        <label for="account_provider" class="block text-sm font-medium text-gray-700 mb-1">
                                            Provider <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="account_provider" id="account_provider" 
                                               value="{{ old('account_provider', $maturationPeriod->paymentMethodAccount->provider ?? '') }}"
                                               placeholder="e.g., Yo Uganda, MTN Mobile Money, Bank Name"
                                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('account_provider') border-red-300 @enderror">
                                        @error('account_provider')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="text-xs text-gray-500 mt-1">Enter the payment provider name</p>
                                    </div>
                                    
                                    <div>
                                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">
                                            Account Number
                                        </label>
                                        <input type="text" name="account_number" id="account_number" 
                                               value="{{ old('account_number', $maturationPeriod->paymentMethodAccount->account_number ?? '') }}"
                                               placeholder="e.g., 0772345678 or account number"
                                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('account_number') border-red-300 @enderror">
                                        @error('account_number')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    
                                    <div>
                                        <label for="account_holder_name" class="block text-sm font-medium text-gray-700 mb-1">
                                            Account Holder Name
                                        </label>
                                        <input type="text" name="account_holder_name" id="account_holder_name" 
                                               value="{{ old('account_holder_name', $maturationPeriod->paymentMethodAccount->account_holder_name ?? '') }}"
                                               placeholder="e.g., Business Name or Account Holder"
                                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('account_holder_name') border-red-300 @enderror">
                                        @error('account_holder_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   {{ old('is_active', $maturationPeriod->is_active) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Active
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex items-center justify-end space-x-3">
                        <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" 
                           class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Maturation Period
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Load accounts when business is selected
document.getElementById('business_id').addEventListener('change', function() {
    // Check for existing account when both business and payment method are selected
    checkForExistingAccount();
});

// Show account section for payment methods that require accounts (mobile_money, bank_transfer, etc.)
document.getElementById('payment_method').addEventListener('change', function() {
    const paymentMethod = this.value;
    const accountSection = document.getElementById('account-section');
    
    // Show account section for payment methods that need accounts
    const methodsNeedingAccounts = ['mobile_money', 'bank_transfer'];
    
    if (methodsNeedingAccounts.includes(paymentMethod)) {
        accountSection.style.display = 'block';
        checkForExistingAccount();
    } else {
        accountSection.style.display = 'none';
        // Reset account fields
        document.getElementById('existing_account_display').style.display = 'none';
        document.getElementById('new_account_fields').style.display = 'none';
        document.getElementById('payment_method_account_id').value = '';
    }
});

// Function to check for existing account
function checkForExistingAccount() {
    const businessId = document.getElementById('business_id').value;
    const paymentMethod = document.getElementById('payment_method').value;
    const accountSection = document.getElementById('account-section');
    const methodsNeedingAccounts = ['mobile_money', 'bank_transfer'];
    
    if (!businessId || !paymentMethod || !methodsNeedingAccounts.includes(paymentMethod)) {
        return;
    }
    
    // Hide both sections initially
    const existingAccountDisplay = document.getElementById('existing_account_display');
    const newAccountFields = document.getElementById('new_account_fields');
    existingAccountDisplay.style.display = 'none';
    newAccountFields.style.display = 'none';
    
    // Check for existing account via AJAX
    fetch('{{ route("maturation-periods.check-account") }}?business_id=' + businessId + '&payment_method=' + paymentMethod)
        .then(response => response.json())
        .then(data => {
            // Get business and payment method names
            const businessSelect = document.getElementById('business_id');
            const paymentMethodSelect = document.getElementById('payment_method');
            const selectedBusiness = businessSelect.options[businessSelect.selectedIndex]?.text || '';
            const selectedPaymentMethod = paymentMethodSelect.options[paymentMethodSelect.selectedIndex]?.text || '';
            
            if (data.exists) {
                // Show existing account
                const account = data.account;
                let accountInfo = account.name;
                if (account.provider) {
                    accountInfo += ' (' + account.provider + ')';
                }
                if (account.balance !== '0.00') {
                    accountInfo += ' - Balance: ' + account.balance + ' ' + account.currency;
                }
                
                document.getElementById('existing_account_info').textContent = accountInfo;
                document.getElementById('payment_method_account_id').value = account.id;
                existingAccountDisplay.style.display = 'block';
            } else {
                // Show account creation fields
                document.getElementById('payment_method_account_id').value = '';
                newAccountFields.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error checking for account:', error);
            // On error, show account creation fields
            document.getElementById('payment_method_account_id').value = '';
            document.getElementById('new_account_fields').style.display = 'block';
        });
}

// Check on page load if account section should be shown
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.getElementById('payment_method').value;
    const methodsNeedingAccounts = ['mobile_money', 'bank_transfer'];
    
    if (methodsNeedingAccounts.includes(paymentMethod)) {
        checkForExistingAccount();
    }
});
</script>
</x-app-layout>









