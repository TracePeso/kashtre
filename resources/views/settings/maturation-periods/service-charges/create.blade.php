<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('maturation-periods.index', ['tab' => 'service-charges']) }}" class="text-sm text-blue-600 hover:text-blue-800">← Back to maturation periods</a>
            <h2 class="mt-2 text-2xl font-bold text-gray-900">Create service charge maturation</h2>
            <p class="mt-1 text-sm text-gray-500">Days before service fee amounts are treated as matured, per business entity and payment method.</p>
        </div>

        @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white shadow sm:rounded-lg">
            <form action="{{ route('service-charge-maturation-periods.store') }}" method="POST" class="px-4 py-5 sm:p-6 space-y-6">
                @csrf
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700">Entity (business) <span class="text-red-500">*</span></label>
                    <select name="business_id" id="business_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Select entity…</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" @selected(old('business_id') == $business->id)>{{ $business->name }}</option>
                        @endforeach
                    </select>
                    @error('business_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment method <span class="text-red-500">*</span></label>
                    <select name="payment_method" id="payment_method" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Select…</option>
                        @foreach($paymentMethods as $method)
                            @php
                                $labels = [
                                    'insurance' => 'Insurance',
                                    'credit_arrangement' => 'Credit Arrangement',
                                    'mobile_money' => 'Mobile Money',
                                    'v_card' => 'V Card (Virtual Card)',
                                    'p_card' => 'P Card (Physical Card)',
                                    'bank_transfer' => 'Bank Transfer',
                                    'cash' => 'Cash',
                                ];
                            @endphp
                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $labels[$method] ?? $method }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="maturation_days" class="block text-sm font-medium text-gray-700">Maturation period (days) <span class="text-red-500">*</span></label>
                    <input type="number" name="maturation_days" id="maturation_days" min="0" max="365" required value="{{ old('maturation_days') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @error('maturation_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-500">0–365 days.</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" @checked(old('is_active', true))>
                    <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save</button>
                    <a href="{{ route('maturation-periods.index', ['tab' => 'service-charges']) }}" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
