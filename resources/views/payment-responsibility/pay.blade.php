<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Pay ') }}{{ ucfirst($type) }}
            </h2>
            <a href="{{ route('pos.item-selection', $client) }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-150">
                ← Back to Client
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
            @endif

            <!-- Payment Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        @if($type === 'deductible')
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        @else
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        @endif
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ ucfirst($type) }} Payment</h3>
                            <p class="text-sm text-gray-600">
                                @if($type === 'deductible')
                                    Amount client must pay before insurance coverage begins
                                @else
                                    Fixed amount payable at each visit
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">Total Amount</p>
                            <p class="text-lg font-bold text-gray-900">UGX {{ number_format($amount, 2) }}</p>
                        </div>
                        @if($type === 'deductible')
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">Amount Used</p>
                            <p class="text-lg font-bold text-gray-900">UGX {{ number_format($used, 2) }}</p>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-200">
                            <p class="text-xs text-yellow-600 mb-1">Remaining</p>
                            <p class="text-lg font-bold text-yellow-900">UGX {{ number_format($remaining, 2) }}</p>
                        </div>
                        @else
                        <div class="bg-blue-50 p-4 rounded-lg border-2 border-blue-200">
                            <p class="text-xs text-blue-600 mb-1">Amount Due</p>
                            <p class="text-lg font-bold text-blue-900">UGX {{ number_format($remaining, 2) }}</p>
                        </div>
                        @endif
                    </div>

                    <!-- Payment Form -->
                    <form action="{{ route('payment-responsibility.process', $client) }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">

                        <!-- Payment Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Amount <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                name="amount" 
                                id="amount" 
                                step="0.01"
                                min="0.01"
                                max="{{ $remaining }}"
                                value="{{ old('amount', $remaining) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">Maximum: UGX {{ number_format($remaining, 2) }}</p>
                            @error('amount')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select 
                                name="payment_method" 
                                id="payment_method" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="">Select payment method</option>
                                @foreach($availablePaymentMethods as $method)
                                <option value="{{ $method }}" {{ old('payment_method', 'cash') === $method ? 'selected' : '' }}>
                                    {{ ucwords(str_replace('_', ' ', $method)) }}
                                </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <strong>Cash:</strong> Payment is processed immediately. 
                                <strong>Mobile Money:</strong> Payment request sent to customer's phone for approval.
                            </p>
                            @error('payment_method')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Phone (for mobile money) -->
                        <div id="payment-phone-section" style="display: none;">
                            <label for="payment_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Phone Number
                            </label>
                            <input 
                                type="tel" 
                                name="payment_phone" 
                                id="payment_phone" 
                                value="{{ old('payment_phone', $client->payment_phone_number ?? '') }}"
                                placeholder="e.g., +256701234567"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                            <p class="text-xs text-gray-500 mt-1">Required for mobile money payments</p>
                            @error('payment_phone')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Reference -->
                        <div>
                            <label for="payment_reference" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Reference (Optional)
                            </label>
                            <input 
                                type="text" 
                                name="payment_reference" 
                                id="payment_reference" 
                                value="{{ old('payment_reference') }}"
                                placeholder="Transaction reference number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                            <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes (Optional)
                            </label>
                            <textarea 
                                name="notes" 
                                id="notes" 
                                rows="3"
                                placeholder="Additional notes about this payment..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >{{ old('notes') }}</textarea>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                            <a href="{{ route('pos.item-selection', $client) }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-150">
                                Cancel
                            </a>
                            <button 
                                type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150"
                            >
                                Process Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethodSelect = document.getElementById('payment_method');
            const paymentPhoneSection = document.getElementById('payment-phone-section');
            const paymentPhoneInput = document.getElementById('payment_phone');

            paymentMethodSelect.addEventListener('change', function() {
                const selectedMethod = this.value;
                
                // Show phone input for mobile money
                if (selectedMethod === 'mobile_money') {
                    paymentPhoneSection.style.display = 'block';
                    paymentPhoneInput.setAttribute('required', 'required');
                } else {
                    paymentPhoneSection.style.display = 'none';
                    paymentPhoneInput.removeAttribute('required');
                }
            });

            // Trigger on page load if mobile_money is already selected
            if (paymentMethodSelect.value === 'mobile_money') {
                paymentMethodSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</x-app-layout>
