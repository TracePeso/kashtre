<x-app-layout>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="mb-4 sm:mb-0">
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Register New Client</h1>
                            <div class="flex items-center space-x-4 text-sm text-gray-600">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm3 1h6v2H7V5zm6 4H7v2h6V9zm0 4H7v2h6v-2z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $business->name }}
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $currentBranch->name }}
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('clients.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Clients
                        </a>
                    </div>
                </div>

                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Tabs Navigation -->
                <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px" aria-label="Tabs">
                            <button type="button" onclick="switchTab('individual')" id="tab-individual" class="tab-button active flex-1 py-4 px-6 text-center text-sm font-medium border-b-2 border-blue-500 text-blue-600 hover:text-blue-700 transition-colors">
                                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Individual Repeat Client
                            </button>
                            <button type="button" onclick="switchTab('company')" id="tab-company" class="tab-button flex-1 py-4 px-6 text-center text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors">
                                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                Company Client
                            </button>
                            <button type="button" onclick="switchTab('walk_in')" id="tab-walk_in" class="tab-button flex-1 py-4 px-6 text-center text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors">
                                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Walk In
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content: Individual Repeat Client -->
                <div id="tab-content-individual" class="tab-content">
                    <form id="client-registration-form-individual" action="{{ route('clients.store') }}" method="POST" class="space-y-8">
                        @csrf
                        <input type="hidden" name="client_type" value="individual">
                        <input type="hidden" name="existing_client_id" id="existing_client_id" value="">
                        <input type="hidden" name="policy_verified" id="policy_verified" value="0">
                        <input type="hidden" name="physical_id_verified" id="physical_id_verified" value="0">
                        <input type="hidden" name="data_open_enrollment" id="data_open_enrollment" value="0">
                        
                        <!-- Personal Information Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
                            <h2 class="text-lg font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Personal Information
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Primary Information: Surname, First Name, Date of Birth -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="surname" class="block text-sm font-medium text-gray-700 mb-2">
                                        Surname <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="surname" id="surname" value="{{ old('surname') }}" required 
                                           placeholder="Enter surname"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        First Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required 
                                           placeholder="Enter first name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
                                        Date of Birth <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                            </div>

                            <!-- Additional Names and Demographics -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="other_names" class="block text-sm font-medium text-gray-700 mb-2">Other Names</label>
                                    <input type="text" name="other_names" id="other_names" value="{{ old('other_names') }}" 
                                           placeholder="Middle names (optional)"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="sex" class="block text-sm font-medium text-gray-700 mb-2">
                                        Gender <span class="text-red-500">*</span>
                                    </label>
                                    <select name="sex" id="sex" required 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('sex') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('sex') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('sex') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="marital_status" class="block text-sm font-medium text-gray-700 mb-2">
                                        Marital Status <span class="text-red-500">*</span>
                                    </label>
                                    <select name="marital_status" id="marital_status" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <option value="">Select Status</option>
                                        <option value="single" {{ old('marital_status') == 'single' ? 'selected' : '' }}>Single</option>
                                        <option value="married" {{ old('marital_status') == 'married' ? 'selected' : '' }}>Married</option>
                                        <option value="divorced" {{ old('marital_status') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                        <option value="widowed" {{ old('marital_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Identification -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="nin" class="block text-sm font-medium text-gray-700 mb-2">
                                        National ID (NIN) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nin" id="nin" value="{{ old('nin') }}" required
                                           placeholder="14-digit NIN number (leave empty to auto-generate)"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Leave empty to auto-generate temporary ID</p>
                                </div>
                                
                                <div>
                                    <label for="tin_number" class="block text-sm font-medium text-gray-700 mb-2">TIN Number</label>
                                    <div class="space-y-2">
                                        <input type="text" name="tin_number" id="tin_number" value="{{ old('tin_number') }}" 
                                               placeholder="Tax Identification Number"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <button type="button" id="same_as_nin_btn" 
                                                class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                            Same as NIN Number
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Occupation <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="occupation" id="occupation" value="{{ old('occupation') }}" required
                                       placeholder="e.g., Teacher, Engineer, Business Owner"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                            </div>

                            <div>
                                <label for="nationality" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nationality <span class="text-red-500">*</span>
                                </label>
                                <select name="nationality" id="nationality" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <option value="">-- Select Nationality --</option>
                                    @foreach($countries as $code => $country)
                                        <option value="{{ $code }}" {{ old('nationality') === $code ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600">
                            <h2 class="text-lg font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Contact Information
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Phone Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="phone_number" id="phone_number" value="{{ old('phone_number') }}" required 
                                           placeholder="e.g., 0770123456 or +256770123456"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">This is the primary contact number for communication</p>
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                           placeholder="e.g., client@example.com"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Use institution's default email if client has none</p>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="county" class="block text-sm font-medium text-gray-700 mb-2">
                                        County <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="county" id="county" value="{{ old('county') }}" required
                                           placeholder="Enter county name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="village" class="block text-sm font-medium text-gray-700 mb-2">
                                        Village <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="village" id="village" value="{{ old('village') }}" required
                                           placeholder="Enter village name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service & Payment Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600">
                            <h2 class="text-lg font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Service & Payment Information
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label for="services_category" class="block text-sm font-medium text-gray-700 mb-2">
                                    Services Category <span class="text-red-500">*</span>
                                </label>
                                <select name="services_category" id="services_category" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <option value="">Select Category</option>
                                    <option value="dental" {{ old('services_category') == 'dental' ? 'selected' : '' }}>Dental</option>
                                    <option value="optical" {{ old('services_category') == 'optical' ? 'selected' : '' }}>Optical</option>
                                    <option value="outpatient" {{ old('services_category') == 'outpatient' ? 'selected' : '' }}>Outpatient</option>
                                    <option value="inpatient" {{ old('services_category') == 'inpatient' ? 'selected' : '' }}>Inpatient</option>
                                    <option value="maternity" {{ old('services_category') == 'maternity' ? 'selected' : '' }}>Maternity</option>
                                    <option value="funeral" {{ old('services_category') == 'funeral' ? 'selected' : '' }}>Funeral</option>
                                </select>
                            </div>

                            <!-- Payment Methods -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    Payment Methods <span class="text-red-500">*</span>
                                </label>
                                <p class="text-sm text-gray-600 mb-4">Select all payment methods this client can use in order of preference</p>
                                
                                <!-- Fallback Payment Method Warning (shown when insurance is selected) -->
                                <div id="fallback_payment_warning" style="display: none;" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                                    <div class="flex">
                                        <svg class="w-5 h-5 text-amber-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div>
                                            <h3 class="text-sm font-semibold text-amber-800">Additional Payment Method Needed</h3>
                                            <p class="mt-1 text-sm text-amber-700">
                                                Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn't cover the full amount.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                @error('payment_methods')
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                        <p class="text-sm text-red-800">{{ $message }}</p>
                                    </div>
                                @enderror
                                
                                @if(empty($availablePaymentMethods))
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                        <div class="flex">
                                            <svg class="w-5 h-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <div>
                                                <h3 class="text-sm font-medium text-yellow-800">No Payment Methods Configured</h3>
                                                <p class="mt-1 text-sm text-yellow-700">
                                                    No payment methods have been set up for your business. Please contact the administrator to configure payment methods in 
                                                    <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="font-medium underline hover:text-yellow-900">Maturation Periods</a>.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($availablePaymentMethods as $index => $method)
                                            <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input type="checkbox" name="payment_methods[]" id="payment_{{ $method }}" value="{{ $method }}"
                                                       {{ in_array($method, old('payment_methods', [])) ? 'checked' : '' }}
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="payment_{{ $method }}" class="ml-3 text-sm font-medium text-gray-700">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-800 text-xs font-bold rounded-full mr-2">{{ $index + 1 }}</span>
                                                    {{ $paymentMethodNames[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    
                                    <div class="flex space-x-4 mt-4">
                                        <button type="button" id="select_all_payments" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                            Select All
                                        </button>
                                        <button type="button" id="clear_all_payments" class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                            Clear All
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <!-- Insurance Company Selection (shown when insurance payment method is selected) -->
                            <div id="insurance_company_section" style="display: none;" class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <h4 class="text-sm font-medium text-green-900 mb-3">Insurance Company Information</h4>
                                
                                <div class="space-y-4">
                                    <!-- Multiple Insurance Vendors Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            Select Insurance Companies <span class="text-red-500">*</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mb-3">You can select up to 3 insurance companies. Each will be managed separately.</p>
                                        
                                        <div id="insurance_selection_warning" style="display: none;" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                            <p class="text-sm text-red-800">Maximum 3 insurance companies can be selected.</p>
                                        </div>
                                        
                                        @if(isset($insuranceCompanies) && !empty($insuranceCompanies))
                                            <div class="space-y-2 mb-4">
                                                @foreach($insuranceCompanies as $company)
                                                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-green-50 cursor-pointer transition-colors">
                                                        <input 
                                                            type="checkbox" 
                                                            name="insurance_company_ids[]" 
                                                            value="{{ $company['id'] }}"
                                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded vendor-checkbox"
                                                            {{ in_array($company['id'], old('insurance_company_ids', [])) ? 'checked' : '' }}
                                                        >
                                                        <span class="ml-3 text-sm font-medium text-gray-700">
                                                            {{ $company['name'] }}@if($company['code']) ({{ $company['code'] }})@endif
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif(isset($connectedVendors) && !empty($connectedVendors))
                                            <div class="space-y-2 mb-4">
                                                @foreach($connectedVendors as $vendor)
                                                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-green-50 cursor-pointer transition-colors">
                                                        <input 
                                                            type="checkbox" 
                                                            name="insurance_company_ids[]" 
                                                            value="{{ $vendor['id'] }}"
                                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded vendor-checkbox"
                                                            {{ in_array($vendor['id'], old('insurance_company_ids', [])) ? 'checked' : '' }}
                                                        >
                                                        <span class="ml-3 text-sm font-medium text-gray-700">
                                                            {{ $vendor['name'] }} ({{ $vendor['code'] }})
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                        @error('insurance_company_ids')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Priority Assignment Section (shown when multiple companies are selected) -->
                                    <div id="insurance_priority_section" style="display: none;" class="bg-white p-4 rounded-lg border border-yellow-200">
                                        <h5 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            Assign Insurance Priority
                                        </h5>
                                        <p class="text-xs text-gray-600 mb-4">Since you've selected multiple insurance companies, assign them in order of preference. Primary insurance will be tried first.</p>
                                        
                                        <div id="priority_assignment_container" class="space-y-3">
                                            <!-- Priority assignments will be dynamically inserted here -->
                                        </div>
                                        
                                        <div id="priority_validation_error" style="display: none;" class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-800">
                                            Please assign all selected insurance companies to a priority level.
                                        </div>
                                    </div>

                                    <!-- Vendor-specific policy details -->
                                    <div id="vendor_policies_section" class="space-y-4" style="display: none;">
                                        <h5 class="text-sm font-semibold text-gray-900 mb-3">Policy Details for Each Vendor</h5>
                                        <div id="vendor_policies_container"></div>
                                    </div>

                                </div>
                            </div>

                            <!-- Payment Phone Number Section -->
                            <div id="payment_phone_section" style="display: none;" class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-blue-900 mb-3">Payment Phone Number</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="payment_phone_number" class="block text-sm font-medium text-gray-700 mb-2">Payment Phone Number</label>
                                        <input type="tel" name="payment_phone_number" id="payment_phone_number" value="{{ old('payment_phone_number') }}" 
                                               placeholder="e.g., 0770123456 or +256770123456"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">This number will be used specifically for mobile money payments</p>
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="button" id="same_as_phone_btn" 
                                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                            Same as Contact Phone
                                        </button>
                                    </div>
                                </div>
                                
                                <p class="text-sm text-blue-700 mt-2">This number is different from the contact phone number and is used exclusively for payment transactions</p>
                            </div>
                        </div>
                    </div>

                    <!-- Next of Kin Information Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-orange-600 to-red-600">
                            <h2 class="text-lg font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Next of Kin Information
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Names -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="nok_surname" class="block text-sm font-medium text-gray-700 mb-2">
                                        Surname <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nok_surname" id="nok_surname" value="{{ old('nok_surname') }}" required
                                           placeholder="Enter next of kin surname"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="nok_first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        First Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nok_first_name" id="nok_first_name" value="{{ old('nok_first_name') }}" required
                                           placeholder="Enter next of kin first name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="nok_other_names" class="block text-sm font-medium text-gray-700 mb-2">Other Names</label>
                                    <input type="text" name="nok_other_names" id="nok_other_names" value="{{ old('nok_other_names') }}" 
                                           placeholder="Middle names (optional)"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                            </div>
                            
                            <!-- Demographics -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div>
                                    <label for="nok_sex" class="block text-sm font-medium text-gray-700 mb-2">
                                        Gender <span class="text-red-500">*</span>
                                    </label>
                                    <select name="nok_sex" id="nok_sex" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('nok_sex') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('nok_sex') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('nok_sex') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="nok_marital_status" class="block text-sm font-medium text-gray-700 mb-2">
                                        Marital Status <span class="text-red-500">*</span>
                                    </label>
                                    <select name="nok_marital_status" id="nok_marital_status" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <option value="">Select Status</option>
                                        <option value="single" {{ old('nok_marital_status') == 'single' ? 'selected' : '' }}>Single</option>
                                        <option value="married" {{ old('nok_marital_status') == 'married' ? 'selected' : '' }}>Married</option>
                                        <option value="divorced" {{ old('nok_marital_status') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                        <option value="widowed" {{ old('nok_marital_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="nok_occupation" class="block text-sm font-medium text-gray-700 mb-2">
                                        Occupation <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nok_occupation" id="nok_occupation" value="{{ old('nok_occupation') }}" required
                                           placeholder="e.g., Teacher, Engineer"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="nok_phone_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Phone Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="nok_phone_number" id="nok_phone_number" value="{{ old('nok_phone_number') }}" required
                                           placeholder="e.g., 0770123456"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Contact number for next of kin</p>
                                </div>
                            </div>
                            
                            <!-- Address -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="nok_county" class="block text-sm font-medium text-gray-700 mb-2">
                                        County <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nok_county" id="nok_county" value="{{ old('nok_county') }}" required
                                           placeholder="Enter county name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                                
                                <div>
                                    <label for="nok_village" class="block text-sm font-medium text-gray-700 mb-2">
                                        Village <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nok_village" id="nok_village" value="{{ old('nok_village') }}" required
                                           placeholder="Enter village name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                            <a href="{{ route('clients.index') }}" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-gray-700 bg-white rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex justify-center items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-medium shadow-lg hover:shadow-xl">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Register Visit
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Content: Company Client -->
                <div id="tab-content-company" class="tab-content hidden">
                    <form id="client-registration-form-company" action="{{ route('clients.store') }}" method="POST" class="space-y-8" x-data="{ 
                        registerType: 'client_only', 
                        loadingCompany: false, 
                        companyLoaded: false,
                        fetchCompanyDetails(code) {
                            if (!code || code.length !== 8 || !/^[A-Z0-9]{8}$/.test(code)) return;
                            
                            this.loadingCompany = true;
                            this.companyLoaded = false;
                            
                            fetch(`/api/insurance-company/by-code/${code}`)
                                .then(response => response.json())
                                .then(data => {
                                    this.loadingCompany = false;
                                    
                                    if (data.success && data.data) {
                                        const companyData = data.data;
                                        
                                        // Auto-fill form fields only if they're empty
                                        const companyNameField = document.getElementById('company_name');
                                        if (companyNameField && !companyNameField.value) {
                                            companyNameField.value = companyData.name || '';
                                        }
                                        
                                        const companyEmailField = document.getElementById('company_email');
                                        if (companyEmailField && !companyEmailField.value) {
                                            companyEmailField.value = companyData.email || '';
                                        }
                                        
                                        const companyPhoneField = document.getElementById('company_phone');
                                        if (companyPhoneField && !companyPhoneField.value) {
                                            companyPhoneField.value = companyData.phone || '';
                                        }
                                        
                                        const companyAddressField = document.getElementById('company_address');
                                        if (companyAddressField && !companyAddressField.value) {
                                            companyAddressField.value = companyData.head_office_address || companyData.postal_address || '';
                                        }

                                        const companyTinField = document.getElementById('company_tin');
                                        if (companyTinField && !companyTinField.value) {
                                            companyTinField.value = companyData.tin || '';
                                        }

                                        this.companyLoaded = true;
                                        setTimeout(() => { this.companyLoaded = false; }, 3000);
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Company Not Found',
                                            text: data.message || 'Third party vendor not found with the provided code.',
                                            confirmButtonColor: '#3085d6',
                                        });
                                    }
                                })
                                .catch(error => {
                                    this.loadingCompany = false;
                                    console.error('Error fetching company details:', error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'An error occurred while fetching company details. Please try again.',
                                        confirmButtonColor: '#3085d6',
                                    });
                                });
                        }
                    }">
                        @csrf
                        <input type="hidden" name="client_type" value="company">
                        <input type="hidden" name="register_type" x-model="registerType">
                        
                        <!-- Third Party Vendor Code (First Field - Only for Third Party Payer) -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-show="registerType === 'client_and_payer'" x-transition>
                            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-teal-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                    </svg>
                                    Third Party Vendor Code
                                </h2>
                            </div>
                            <div class="p-6">
                                <div>
                                    <label for="insurance_company_code" class="block text-sm font-medium text-gray-700 mb-2">
                                        Enter 8-Character Third Party Vendor Code <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               name="insurance_company_code" 
                                               id="insurance_company_code" 
                                               value="{{ old('insurance_company_code') }}"
                                               placeholder="Enter 8-character code (e.g., A1B2C3D4)"
                                               pattern="[A-Z0-9]{8}"
                                               maxlength="8"
                                               minlength="8"
                                               style="text-transform: uppercase;"
                                               x-bind:required="registerType === 'client_and_payer'"
                                               x-on:input.debounce.500ms="fetchCompanyDetails($el.value.toUpperCase())"
                                               x-on:keyup="$el.value = $el.value.toUpperCase()"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-colors font-mono text-lg @error('insurance_company_code') border-red-300 @enderror">
                                        <div x-show="loadingCompany" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                            <svg class="animate-spin h-5 w-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Enter the 8-character alphanumeric code (uppercase letters and numbers) to auto-fill company information</p>
                                    <div x-show="companyLoaded" class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <p class="text-sm text-green-800 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Company information loaded successfully!
                                        </p>
                                    </div>
                                    @error('insurance_company_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Registration Type Radio Buttons -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Registration Type
                                </h2>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0">
                                    <label for="register_type_client_only" class="flex items-center p-4 rounded-lg border cursor-pointer transition-all duration-200 ease-in-out"
                                        :class="{ 'border-blue-500 bg-blue-50 shadow-md': registerType === 'client_only', 'border-gray-300 bg-white hover:bg-gray-50': registerType !== 'client_only' }">
                                        <input type="radio" name="register_type" id="register_type_client_only" value="client_only" class="form-radio h-4 w-4 text-blue-600" x-model="registerType" checked>
                                        <span class="ml-3 text-sm font-medium text-gray-700">Register as Client Only</span>
                                    </label>
                                    <label for="register_type_client_and_payer" class="flex items-center p-4 rounded-lg border cursor-pointer transition-all duration-200 ease-in-out"
                                        :class="{ 'border-green-500 bg-green-50 shadow-md': registerType === 'client_and_payer', 'border-gray-300 bg-white hover:bg-gray-50': registerType !== 'client_and_payer' }">
                                        <input type="radio" name="register_type" id="register_type_client_and_payer" value="client_and_payer" class="form-radio h-4 w-4 text-green-600" x-model="registerType">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Register as Client and Third Party Vendor</span>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    <span x-show="registerType === 'client_only'">This will register the company as a client only in Kashtre.</span>
                                    <span x-show="registerType === 'client_and_payer'">This will register the company as a client in Kashtre and create a business account in the third-party system.</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Company Information Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    Company Information
                                </h2>
                            </div>
                            <div class="p-6 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Company Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="company_name" id="company_name" value="{{ old('company_name') }}" required 
                                               placeholder="Enter company name"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    </div>
                                    
                                    <div>
                                        <label for="company_tin" class="block text-sm font-medium text-gray-700 mb-2">
                                            Company TIN Number <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="company_tin" id="company_tin" value="{{ old('company_tin') }}" required 
                                               placeholder="Enter TIN number"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                            Contact Phone Number <span class="text-red-500">*</span>
                                        </label>
                                        <input type="tel" name="company_phone" id="company_phone" value="{{ old('company_phone') }}" required 
                                               placeholder="e.g., 0770123456 or +256770123456"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    </div>
                                    
                                    <div>
                                        <label for="company_email" class="block text-sm font-medium text-gray-700 mb-2">
                                            Email Address <span class="text-red-500">*</span>
                                        </label>
                                        <input type="email" name="company_email" id="company_email" value="{{ old('company_email') }}" required
                                               placeholder="e.g., company@example.com"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                    </div>
                                </div>

                                <div>
                                    <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">
                                        Company Address <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="company_address" id="company_address" rows="3" required
                                              placeholder="Enter company address"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">{{ old('company_address') }}</textarea>
                                </div>

                            </div>
                        </div>

                        <!-- Payment Methods Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-teal-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Payment Methods
                                </h2>
                            </div>
                            <div class="p-6 space-y-6">
                                <!-- Payment Methods -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Payment Methods <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-sm text-gray-600 mb-4">Select all payment methods this client can use in order of preference</p>
                                    
                                    @if(empty($availablePaymentMethods))
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                            <div class="flex">
                                                <svg class="w-5 h-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                <div>
                                                    <h3 class="text-sm font-medium text-yellow-800">No Payment Methods Configured</h3>
                                                    <p class="mt-1 text-sm text-yellow-700">
                                                        No payment methods have been set up for your business. Please contact the administrator to configure payment methods in 
                                                        <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="font-medium underline hover:text-yellow-900">Maturation Periods</a>.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @php $companyIndex = 0; @endphp
                                            @foreach($availablePaymentMethods as $method)
                                                @if($method === 'insurance') @continue @endif
                                                @php $companyIndex++; @endphp
                                                <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <input type="checkbox" name="payment_methods[]" id="payment_company_{{ $method }}" value="{{ $method }}"
                                                           {{ in_array($method, old('payment_methods', [])) ? 'checked' : '' }}
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="payment_company_{{ $method }}" class="ml-3 text-sm font-medium text-gray-700">
                                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-800 text-xs font-bold rounded-full mr-2">{{ $companyIndex }}</span>
                                                        {{ $paymentMethodNames[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                        <div class="flex space-x-4 mt-4">
                                            <button type="button" id="select_all_payments_company" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                                Select All
                                            </button>
                                            <button type="button" id="clear_all_payments_company" class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                                Clear All
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Payment Phone Number Section -->
                                <div id="payment_phone_section_company" style="display: none;" class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-blue-900 mb-3">Payment Phone Number</h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="payment_phone_number_company" class="block text-sm font-medium text-gray-700 mb-2">Payment Phone Number</label>
                                            <input type="tel" name="payment_phone_number" id="payment_phone_number_company" value="{{ old('payment_phone_number') }}" 
                                                   placeholder="e.g., 0770123456 or +256770123456"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                            <p class="text-xs text-gray-500 mt-1">This number will be used specifically for mobile money payments</p>
                                        </div>
                                        
                                        <div class="flex items-end">
                                            <button type="button" id="same_as_phone_btn_company" 
                                                    class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                                Same as Contact Phone
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-blue-700 mt-2">This number is different from the contact phone number and is used exclusively for payment transactions</p>
                                </div>
                            </div>
                        </div>

  
                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                            <a href="{{ route('clients.index') }}" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-gray-700 bg-white rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex justify-center items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-medium shadow-lg hover:shadow-xl">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Register Company
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Content: Walk In -->
                <div id="tab-content-walk_in" class="tab-content hidden">
                    <form id="client-registration-form-walk_in" action="{{ route('clients.store') }}" method="POST" class="space-y-8">
                        @csrf
                        <input type="hidden" name="client_type" value="walk_in">
                        
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-orange-600 to-red-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Walk In Client
                                </h2>
                            </div>
                            <div class="p-6">
                                <p class="text-gray-700 mb-6">Walk-in clients do not require detailed information. A minimal client record will be created for transaction tracking.</p>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                    <div class="flex">
                                        <svg class="w-5 h-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div>
                                            <h3 class="text-sm font-medium text-blue-800">Note</h3>
                                            <p class="mt-1 text-sm text-blue-700">No personal details will be captured for walk-in clients. This is suitable for one-time transactions.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-teal-600">
                                <h2 class="text-lg font-semibold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Payment Methods
                                </h2>
                            </div>
                            <div class="p-6 space-y-6">
                                <!-- Payment Methods -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Payment Methods <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-sm text-gray-600 mb-4">Select all payment methods this client can use in order of preference</p>
                                    
                                    @if(empty($availablePaymentMethods))
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                            <div class="flex">
                                                <svg class="w-5 h-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                <div>
                                                    <h3 class="text-sm font-medium text-yellow-800">No Payment Methods Configured</h3>
                                                    <p class="mt-1 text-sm text-yellow-700">
                                                        No payment methods have been set up for your business. Please contact the administrator to configure payment methods in 
                                                        <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="font-medium underline hover:text-yellow-900">Maturation Periods</a>.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @foreach($availablePaymentMethods as $index => $method)
                                                <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <input type="checkbox" name="payment_methods[]" id="payment_walkin_{{ $method }}" value="{{ $method }}"
                                                           {{ in_array($method, old('payment_methods', [])) ? 'checked' : '' }}
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="payment_walkin_{{ $method }}" class="ml-3 text-sm font-medium text-gray-700">
                                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-800 text-xs font-bold rounded-full mr-2">{{ $index + 1 }}</span>
                                                        {{ $paymentMethodNames[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                        <div class="flex space-x-4 mt-4">
                                            <button type="button" id="select_all_payments_walkin" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                                Select All
                                            </button>
                                            <button type="button" id="clear_all_payments_walkin" class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                                Clear All
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Payment Phone Number Section -->
                                <div id="payment_phone_section_walkin" style="display: none;" class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-blue-900 mb-3">Payment Phone Number</h4>
                                    
                                    <div>
                                        <label for="payment_phone_number_walkin" class="block text-sm font-medium text-gray-700 mb-2">Payment Phone Number</label>
                                        <input type="tel" name="payment_phone_number" id="payment_phone_number_walkin" value="{{ old('payment_phone_number') }}" 
                                               placeholder="e.g., 0770123456 or +256770123456"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">This number will be used specifically for mobile money payments</p>
                                    </div>
                                    
                                    <p class="text-sm text-blue-700 mt-2">This number is used exclusively for payment transactions</p>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                            <a href="{{ route('clients.index') }}" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-gray-700 bg-white rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex justify-center items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-medium shadow-lg hover:shadow-xl">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Create Walk In Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentPhoneSection = document.getElementById('payment_phone_section');
            const paymentPhoneInput = document.getElementById('payment_phone_number');
            const sameAsPhoneBtn = document.getElementById('same_as_phone_btn');
            const clientPhoneInput = document.getElementById('phone_number');
            const mobileMoneyCheckbox = document.getElementById('payment_mobile_money');
            const selectAllBtn = document.getElementById('select_all_payments');
            const clearAllBtn = document.getElementById('clear_all_payments');
            const paymentCheckboxes = document.querySelectorAll('input[name="payment_methods[]"]');
            
            // TIN same as NIN functionality
            const sameAsNinBtn = document.getElementById('same_as_nin_btn');
            const ninInput = document.getElementById('nin');
            const tinInput = document.getElementById('tin_number');

            function togglePaymentPhoneSection() {
                if (mobileMoneyCheckbox.checked) {
                    paymentPhoneSection.style.display = 'block';
                    paymentPhoneInput.required = true;
                } else {
                    paymentPhoneSection.style.display = 'none';
                    paymentPhoneInput.required = false;
                    paymentPhoneInput.value = '';
                }
            }

            mobileMoneyCheckbox.addEventListener('change', togglePaymentPhoneSection);
            
            sameAsPhoneBtn.addEventListener('click', function() {
                paymentPhoneInput.value = clientPhoneInput.value;
            });

            sameAsNinBtn.addEventListener('click', function() {
                tinInput.value = ninInput.value;
            });

            selectAllBtn.addEventListener('click', function() {
                paymentCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                togglePaymentPhoneSection();
            });

            clearAllBtn.addEventListener('click', function() {
                paymentCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                togglePaymentPhoneSection();
            });

            // Initialize on page load
            if (mobileMoneyCheckbox.checked) {
                togglePaymentPhoneSection();
            }
        });

        // Company Payment Methods Handlers
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCompanyBtn = document.getElementById('select_all_payments_company');
            const clearAllCompanyBtn = document.getElementById('clear_all_payments_company');
            const paymentCompanyCheckboxes = document.querySelectorAll('#tab-content-company input[name="payment_methods[]"]');
            const paymentPhoneSectionCompany = document.getElementById('payment_phone_section_company');
            const paymentPhoneInputCompany = document.getElementById('payment_phone_number_company');
            const sameAsPhoneBtnCompany = document.getElementById('same_as_phone_btn_company');
            const companyPhoneInput = document.getElementById('company_phone');
            
            if (selectAllCompanyBtn && clearAllCompanyBtn) {
                function togglePaymentPhoneSectionCompany() {
                    const mobileMoneyChecked = Array.from(paymentCompanyCheckboxes).some(cb => cb.id === 'payment_company_mobile_money' && cb.checked);
                    if (mobileMoneyChecked) {
                        paymentPhoneSectionCompany.style.display = 'block';
                        if (paymentPhoneInputCompany) paymentPhoneInputCompany.required = true;
                    } else {
                        paymentPhoneSectionCompany.style.display = 'none';
                        if (paymentPhoneInputCompany) {
                            paymentPhoneInputCompany.required = false;
                            paymentPhoneInputCompany.value = '';
                        }
                    }
                }

                selectAllCompanyBtn.addEventListener('click', function() {
                    paymentCompanyCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    togglePaymentPhoneSectionCompany();
                });

                clearAllCompanyBtn.addEventListener('click', function() {
                    paymentCompanyCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    togglePaymentPhoneSectionCompany();
                });

                paymentCompanyCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', togglePaymentPhoneSectionCompany);
                });

                if (sameAsPhoneBtnCompany && companyPhoneInput && paymentPhoneInputCompany) {
                    sameAsPhoneBtnCompany.addEventListener('click', function() {
                        paymentPhoneInputCompany.value = companyPhoneInput.value;
                    });
                }

                // Initialize on page load
                togglePaymentPhoneSectionCompany();
            }
        });

        // Walk-in Payment Methods Handlers
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllWalkinBtn = document.getElementById('select_all_payments_walkin');
            const clearAllWalkinBtn = document.getElementById('clear_all_payments_walkin');
            const paymentWalkinCheckboxes = document.querySelectorAll('#tab-content-walk_in input[name="payment_methods[]"]');
            const paymentPhoneSectionWalkin = document.getElementById('payment_phone_section_walkin');
            const paymentPhoneInputWalkin = document.getElementById('payment_phone_number_walkin');
            
            if (selectAllWalkinBtn && clearAllWalkinBtn) {
                function togglePaymentPhoneSectionWalkin() {
                    const mobileMoneyChecked = Array.from(paymentWalkinCheckboxes).some(cb => cb.id === 'payment_walkin_mobile_money' && cb.checked);
                    if (mobileMoneyChecked) {
                        paymentPhoneSectionWalkin.style.display = 'block';
                        if (paymentPhoneInputWalkin) paymentPhoneInputWalkin.required = true;
                    } else {
                        paymentPhoneSectionWalkin.style.display = 'none';
                        if (paymentPhoneInputWalkin) {
                            paymentPhoneInputWalkin.required = false;
                            paymentPhoneInputWalkin.value = '';
                        }
                    }
                }

                selectAllWalkinBtn.addEventListener('click', function() {
                    paymentWalkinCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    togglePaymentPhoneSectionWalkin();
                });

                clearAllWalkinBtn.addEventListener('click', function() {
                    paymentWalkinCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    togglePaymentPhoneSectionWalkin();
                });

                paymentWalkinCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', togglePaymentPhoneSectionWalkin);
                });

                // Initialize on page load
                togglePaymentPhoneSectionWalkin();
            }
        });

        // Auto-fill form when surname, first name, and DOB match existing client
        (function() {
            const surnameInput = document.getElementById('surname');
            const firstNameInput = document.getElementById('first_name');
            const dobInput = document.getElementById('date_of_birth');
            let searchTimeout = null;
            let isAutoFilling = false;

            function checkAndSearch() {
                // Clear any existing timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }

                // Check if all three fields have values
                if (!surnameInput.value.trim() || !firstNameInput.value.trim() || !dobInput.value) {
                    return;
                }

                // Debounce the search - wait 500ms after user stops typing
                searchTimeout = setTimeout(() => {
                    if (isAutoFilling) return; // Prevent recursive calls

                    // Make AJAX request to search for existing client
                    fetch('{{ route("clients.search-existing") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            surname: surnameInput.value.trim(),
                            first_name: firstNameInput.value.trim(),
                            date_of_birth: dobInput.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.found && data.client) {
                            // Show SweetAlert asking if user wants to use existing client data
                            Swal.fire({
                                title: 'Existing Client Found!',
                                html: `
                                    <p class="text-gray-700 mb-3">We found a client with matching details:</p>
                                    <div class="text-left bg-gray-50 p-4 rounded-lg mb-3">
                                        <p><strong>Client ID:</strong> ${data.client.client_id}</p>
                                        <p><strong>Name:</strong> ${surnameInput.value} ${firstNameInput.value}</p>
                                    </div>
                                    <p class="text-gray-600">
                                        Would you like to auto-fill the form with their previous details?
                                        You will stay on this page so you can review and adjust details before continuing.
                                    </p>
                                `,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Yes, Auto-fill & Continue',
                                cancelButtonText: 'No, Continue Manually'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    isAutoFilling = true;
                                    autoFillForm(data.client);
                                    const existingIdInput = document.getElementById('existing_client_id');
                                    if (existingIdInput) {
                                        existingIdInput.value = data.client.id;
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Form auto-filled',
                                        text: 'Please review and edit any details, then submit to continue.',
                                        timer: 2000,
                                        showConfirmButton: false,
                                    });
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error searching for existing client:', error);
                        isAutoFilling = false;
                    });
                }, 500);
            }

            function autoFillForm(client) {
                // Fill text inputs
                if (client.other_names && document.getElementById('other_names')) {
                    document.getElementById('other_names').value = client.other_names || '';
                }
                if (client.nin && document.getElementById('nin')) {
                    document.getElementById('nin').value = client.nin || '';
                }
                if (client.tin_number && document.getElementById('tin_number')) {
                    document.getElementById('tin_number').value = client.tin_number || '';
                }
                if (client.sex && document.getElementById('sex')) {
                    document.getElementById('sex').value = client.sex;
                }
                if (client.marital_status && document.getElementById('marital_status')) {
                    document.getElementById('marital_status').value = client.marital_status;
                }
                if (client.occupation && document.getElementById('occupation')) {
                    document.getElementById('occupation').value = client.occupation || '';
                }
                if (client.phone_number && document.getElementById('phone_number')) {
                    document.getElementById('phone_number').value = client.phone_number || '';
                }
                if (client.village && document.getElementById('village')) {
                    document.getElementById('village').value = client.village || '';
                }
                if (client.county && document.getElementById('county')) {
                    document.getElementById('county').value = client.county || '';
                }
                if (client.email && document.getElementById('email')) {
                    document.getElementById('email').value = client.email || '';
                }
                if (client.services_category && document.getElementById('services_category')) {
                    document.getElementById('services_category').value = client.services_category;
                }
                if (client.payment_phone_number && document.getElementById('payment_phone_number')) {
                    document.getElementById('payment_phone_number').value = client.payment_phone_number || '';
                }

                // Fill payment methods checkboxes
                if (client.payment_methods && Array.isArray(client.payment_methods)) {
                    client.payment_methods.forEach(method => {
                        // Checkbox IDs are formatted as 'payment_' + method (e.g., 'payment_mobile_money')
                        const checkbox = document.getElementById('payment_' + method);
                        if (checkbox) {
                            checkbox.checked = true;
                            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    if (client.payment_methods.includes('mobile_money')) {
                        const mobileMoneyCheckbox = document.getElementById('payment_mobile_money');
                        if (mobileMoneyCheckbox) {
                            mobileMoneyCheckbox.dispatchEvent(new Event('change'));
                        }
                    }
                }

                // Fill Next of Kin fields
                if (client.nok_surname && document.getElementById('nok_surname')) {
                    document.getElementById('nok_surname').value = client.nok_surname || '';
                }
                if (client.nok_first_name && document.getElementById('nok_first_name')) {
                    document.getElementById('nok_first_name').value = client.nok_first_name || '';
                }
                if (client.nok_other_names && document.getElementById('nok_other_names')) {
                    document.getElementById('nok_other_names').value = client.nok_other_names || '';
                }
                if (client.nok_sex && document.getElementById('nok_sex')) {
                    document.getElementById('nok_sex').value = client.nok_sex;
                }
                if (client.nok_marital_status && document.getElementById('nok_marital_status')) {
                    document.getElementById('nok_marital_status').value = client.nok_marital_status;
                }
                if (client.nok_occupation && document.getElementById('nok_occupation')) {
                    document.getElementById('nok_occupation').value = client.nok_occupation || '';
                }
                if (client.nok_phone_number && document.getElementById('nok_phone_number')) {
                    document.getElementById('nok_phone_number').value = client.nok_phone_number || '';
                }
                if (client.nok_village && document.getElementById('nok_village')) {
                    document.getElementById('nok_village').value = client.nok_village || '';
                }
                if (client.nok_county && document.getElementById('nok_county')) {
                    document.getElementById('nok_county').value = client.nok_county || '';
                }
                
                isAutoFilling = false;
            }

            // Add event listeners to the three fields
            surnameInput.addEventListener('blur', checkAndSearch);
            firstNameInput.addEventListener('blur', checkAndSearch);
            dobInput.addEventListener('change', checkAndSearch);
        })();

        // Insurance Company and Policy Number Handling
        (function() {
            const insuranceCompanySection = document.getElementById('insurance_company_section');
            const policyNumberSection = document.getElementById('policy_number_section');
            const insuranceCompanySelect = document.getElementById('insurance_company_id');
            const policyNumberInput = document.getElementById('policy_number');
            const verifyPolicyBtn = document.getElementById('verify_policy_btn');
            const policyVerificationResult = document.getElementById('policy_verification_result');
            const policyVerifiedInput = document.getElementById('policy_verified');

            // Store registration settings (including show_policy_details_at_registration)
            let registrationSettings = {
                show_policy_details: true, // default to showing policy details
                fields_to_display: ['benefit_balance', 'deductible_amount', 'copay_amount', 'coinsurance_percentage'], // default all
                visit_authorization_period_days: 7
            };

            // Track verification state per insurance company to prevent data loss when switching
            let verificationStates = {};

            // Save current verification state before switching
            function saveCurrentVerificationState() {
                if (!insuranceCompanySelect || !insuranceCompanySelect.value) return;
                const companyId = insuranceCompanySelect.value;
                verificationStates[companyId] = {
                    policyNumber: policyNumberInput?.value || '',
                    isVerified: policyVerifiedInput?.value === '1',
                    resultHtml: policyVerificationResult?.innerHTML || '',
                    buttonDisabled: verifyPolicyBtn?.disabled || false,
                    buttonText: verifyPolicyBtn?.textContent || 'Verify',
                    greenBorder: policyNumberInput?.classList.contains('border-green-300') || false,
                    redBorder: policyNumberInput?.classList.contains('border-red-300') || false,
                };
            }

            // Restore verification state for a company
            function restoreVerificationState(companyId) {
                if (!verificationStates[companyId]) {
                    // No saved state, do a full reset
                    resetInsuranceVerificationUI();
                    return;
                }

                const state = verificationStates[companyId];
                if (policyNumberInput) policyNumberInput.value = state.policyNumber;
                if (policyVerifiedInput) policyVerifiedInput.value = state.isVerified ? '1' : '0';
                if (policyVerificationResult) policyVerificationResult.innerHTML = state.resultHtml;
                if (verifyPolicyBtn) {
                    verifyPolicyBtn.disabled = state.buttonDisabled;
                    verifyPolicyBtn.textContent = state.buttonText;
                }
                if (policyNumberInput) {
                    if (state.greenBorder) {
                        policyNumberInput.classList.add('border-green-300');
                        policyNumberInput.classList.remove('border-red-300');
                    } else if (state.redBorder) {
                        policyNumberInput.classList.add('border-red-300');
                        policyNumberInput.classList.remove('border-green-300');
                    } else {
                        policyNumberInput.classList.remove('border-green-300', 'border-red-300');
                    }
                }
            }

            function resetInsuranceVerificationUI() {
                // Reset policy verification UI when insurance company / policy context changes
                if (policyVerifiedInput) {
                    policyVerifiedInput.value = '0';
                }
                if (policyVerificationResult) {
                    policyVerificationResult.innerHTML = '';
                }
                if (policyNumberInput) {
                    policyNumberInput.classList.remove('border-green-300');
                    policyNumberInput.classList.remove('border-red-300');
                }
                if (verifyPolicyBtn) {
                    verifyPolicyBtn.disabled = false;
                    verifyPolicyBtn.textContent = 'Verify';
                }
            }

            // Function to check if insurance payment method is selected
            function isInsuranceSelected() {
                const insuranceCheckbox = document.getElementById('payment_insurance');
                return insuranceCheckbox && insuranceCheckbox.checked;
            }

            // Function to check if at least one fallback payment method is selected
            function hasFallbackPaymentMethod() {
                const paymentCheckboxes = document.querySelectorAll('input[name="payment_methods[]"]:checked');
                let hasNonInsurance = false;
                paymentCheckboxes.forEach(checkbox => {
                    if (checkbox.value !== 'insurance') {
                        hasNonInsurance = true;
                    }
                });
                return hasNonInsurance;
            }

            // Function to toggle fallback payment warning
            function toggleFallbackWarning() {
                const fallbackWarning = document.getElementById('fallback_payment_warning');
                if (isInsuranceSelected() && !hasFallbackPaymentMethod()) {
                    if (fallbackWarning) fallbackWarning.style.display = 'block';
                } else {
                    if (fallbackWarning) fallbackWarning.style.display = 'none';
                }
            }

            // Function to toggle insurance company section
            function toggleInsuranceSection() {
                if (isInsuranceSelected()) {
                    insuranceCompanySection.style.display = 'block';
                    resetInsuranceVerificationUI();
                    toggleFallbackWarning();
                } else {
                    insuranceCompanySection.style.display = 'none';
                    policyNumberSection.style.display = 'none';
                    if (insuranceCompanySelect) insuranceCompanySelect.value = '';
                    if (policyNumberInput) policyNumberInput.value = '';
                    resetInsuranceVerificationUI();
                    toggleFallbackWarning();
                }
            }

            // Add event listeners to all payment method checkboxes
            document.querySelectorAll('input[name="payment_methods[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    toggleInsuranceSection();
                    toggleFallbackWarning();
                });
            });

            // Fetch insurance company settings and show the right section
            async function togglePolicyNumberSection(shouldRestore = false) {
                if (!insuranceCompanySelect || !insuranceCompanySelect.value) {
                    policyNumberSection.style.display = 'none';
                    const oeCard = document.getElementById('open_enrollment_card');
                    if (oeCard) oeCard.remove();
                    if (policyNumberInput) policyNumberInput.value = '';
                    document.getElementById('policy_verified').value = '0';
                    if (!shouldRestore) {
                        resetInsuranceVerificationUI();
                    }
                    return;
                }

                const insuranceCompanyId = insuranceCompanySelect.value;

                // Fetch settings to check open enrollment and registration desk options
                // This single call returns merged settings: open_enrollment + show_policy_details + fields_to_display
                let settings = null;
                try {
                    const res = await fetch(`/api/insurance-settings/${insuranceCompanyId}`);
                    if (res.ok) {
                        settings = await res.json();
                        // Populate registrationSettings from the merged response
                        registrationSettings.show_policy_details = settings?.show_policy_details_at_registration ?? true;
                        registrationSettings.fields_to_display = Array.isArray(settings?.policy_details_to_display_at_registration)
                            ? settings.policy_details_to_display_at_registration
                            : ['benefit_balance', 'deductible_amount', 'copay_amount', 'coinsurance_percentage'];
                        registrationSettings.visit_authorization_period_days = settings?.visit_authorization_period_days ?? 7;
                    }
                } catch (e) { /* fall through to normal flow */ }

                const oeEnabled = settings?.open_enrollment?.enabled ?? false;

                // Remove any existing open enrollment card
                const existingCard = document.getElementById('open_enrollment_card');
                if (existingCard) existingCard.remove();

                if (oeEnabled) {
                    // Hide policy number input — not needed for open enrollment
                    policyNumberSection.style.display = 'none';
                    if (policyNumberInput) policyNumberInput.value = '';
                    document.getElementById('policy_verified').value = '0';

                    // Build criteria summary for display
                    const oe = settings.open_enrollment;
                    let criteriaLines = [];
                    if (oe.min_age !== null || oe.max_age !== null) {
                        const min = oe.min_age ?? '0';
                        const max = oe.max_age ?? '∞';
                        criteriaLines.push(`Age: ${min} – ${max}`);
                    }
                    if (oe.genders && oe.genders.length) criteriaLines.push(`Gender: ${oe.genders.join(', ')}`);
                    if (oe.service_categories && oe.service_categories.length) criteriaLines.push(`Categories: ${oe.service_categories.join(', ')}`);
                    if (oe.start_date || oe.end_date) criteriaLines.push(`Window: ${oe.start_date ?? '—'} to ${oe.end_date ?? '—'}`);
                    if (oe.nationalities && oe.nationalities.length) criteriaLines.push(`Nationality: ${oe.nationalities.join(', ')}`);
                    if (oe.marital_statuses && oe.marital_statuses.length) criteriaLines.push(`Marital status: ${oe.marital_statuses.join(', ')}`);

                    const criteriaHtml = criteriaLines.length
                        ? `<ul class="mt-2 space-y-0.5">${criteriaLines.map(l => `<li class="text-xs text-blue-700">• ${l}</li>`).join('')}</ul>`
                        : '<p class="text-xs text-blue-700 mt-1">No specific criteria — all clients qualify.</p>';

                    const card = document.createElement('div');
                    card.id = 'open_enrollment_card';
                    card.className = 'mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg';
                    card.innerHTML = `
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-blue-900">Open Enrollment</p>
                                <p class="text-xs text-blue-700 mt-0.5">No policy number required. Client will be confirmed based on eligibility criteria.</p>
                                ${criteriaHtml}
                            </div>
                        </div>
                        <div id="oe_check_result" class="mt-3"></div>
                        <button type="button" id="oe_check_btn"
                            class="mt-3 w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            onclick="checkOpenEnrollmentEligibility(${insuranceCompanyId})">
                            Check Eligibility
                        </button>
                    `;
                    policyNumberSection.insertAdjacentElement('afterend', card);
                } else {
                    policyNumberSection.style.display = 'block';
                    // Only reset if not restoring from previous state
                    if (!shouldRestore) {
                        resetInsuranceVerificationUI();
                    }
                }
            }

            // Check client eligibility under open enrollment (no policy number needed)
            window.checkOpenEnrollmentEligibility = async function(insuranceCompanyId) {
                const btn = document.getElementById('oe_check_btn');
                const resultDiv = document.getElementById('oe_check_result');
                if (!btn || !resultDiv) return;

                btn.disabled = true;
                btn.textContent = 'Checking...';
                resultDiv.innerHTML = '<p class="text-xs text-blue-600">Checking eligibility...</p>';

                // Gather available client data from the form
                const dobInput = document.querySelector('input[name="date_of_birth"]');
                const genderSelect = document.querySelector('select[name="sex"]') || document.querySelector('select[name="gender"]') || document.querySelector('input[name="gender"]');
                const nationalityInput = document.querySelector('select[name="nationality"]') || document.querySelector('input[name="nationality"]');
                const maritalSelect = document.querySelector('select[name="marital_status"]') || document.querySelector('input[name="marital_status"]');
                const servicesCategorySelect = document.getElementById('services_category');

                const params = new URLSearchParams();
                if (dobInput?.value) params.append('date_of_birth', dobInput.value);
                if (genderSelect?.value) {
                    // Capitalize gender value to match API expectations (Male/Female)
                    const genderValue = genderSelect.value.charAt(0).toUpperCase() + genderSelect.value.slice(1).toLowerCase();
                    params.append('gender', genderValue);
                }
                if (nationalityInput?.value) params.append('nationality', nationalityInput.value);
                if (maritalSelect?.value) params.append('marital_status', maritalSelect.value);
                if (servicesCategorySelect?.value) params.append('services_category', servicesCategorySelect.value);

                // Use a dummy policy number that won't exist — the third-party will fall back to open enrollment
                const url = `/api/policies/verify/${insuranceCompanyId}/__open_enrollment__?${params.toString()}`;

                try {
                    const response = await fetch(url);
                    const data = await response.json();

                    // For open enrollment, check if verification_method is 'open_enrollment' OR data.open_enrollment is true
                    const isOpenEnrollment = data.verification_method === 'open_enrollment' || data.data?.open_enrollment === true;
                    const isSuccess = data.success && data.exists;

                    if (isSuccess && isOpenEnrollment) {
                        // Confirmed — populate the hidden policy number with the generic one
                        if (policyNumberInput) policyNumberInput.value = data.data.policy_number;
                        document.getElementById('policy_verified').value = '1';
                        document.getElementById('data_open_enrollment').value = '1';  // Mark as open enrollment

                        // Build payment info html (reuse existing logic)
                        // Only show policy details if the setting allows it
                        const paymentInfo = data.data?.payment_responsibility;
                        let paymentInfoHtml = '';
                        if (paymentInfo && registrationSettings.show_policy_details) {
                            if (paymentInfo.has_deductible && paymentInfo.deductible_amount) {
                                paymentInfoHtml += `<p class="text-xs text-green-700 mt-1">Deductible: UGX ${parseFloat(paymentInfo.deductible_amount).toLocaleString()}</p>`;
                            }
                            if (paymentInfo.copay_amount) {
                                paymentInfoHtml += `<p class="text-xs text-green-700">Co-pay: UGX ${parseFloat(paymentInfo.copay_amount).toLocaleString()} per visit</p>`;
                            }
                            if (paymentInfo.coinsurance_percentage) {
                                paymentInfoHtml += `<p class="text-xs text-green-700">Co-insurance: ${parseFloat(paymentInfo.coinsurance_percentage).toFixed(2)}%</p>`;
                            }
                        }

                        resultDiv.innerHTML = `
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm font-semibold text-green-800">✓ Client is eligible</p>
                                <p class="text-xs text-green-700 mt-0.5">${data.message}</p>
                                ${paymentInfoHtml}
                            </div>
                        `;
                        btn.textContent = '✓ Eligible';
                        btn.classList.replace('bg-blue-600', 'bg-green-600');
                        btn.classList.replace('hover:bg-blue-700', 'hover:bg-green-700');
                    } else {
                        const msg = data.message || 'Client does not meet the open enrollment criteria.';
                        // Don't show generic "policy not found" language for open enrollment checks
                        const displayMsg = msg.toLowerCase().includes('not found') || msg.toLowerCase().includes('alternative verification')
                            ? 'Client does not meet the open enrollment criteria for this insurance company.'
                            : msg;
                        
                        // Log for debugging
                        console.log('Open enrollment check failed:', {
                            data: data,
                            isSuccess: data.success && data.exists,
                            isOpenEnrollment: data.verification_method === 'open_enrollment' || data.data?.open_enrollment === true,
                            verification_method: data.verification_method,
                            data_open_enrollment: data.data?.open_enrollment
                        });
                        
                        resultDiv.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm font-semibold text-red-800">✗ Not eligible</p>
                                <p class="text-xs text-red-700 mt-0.5">${displayMsg}</p>
                            </div>
                        `;
                        document.getElementById('policy_verified').value = '0';
                        btn.disabled = false;
                        btn.textContent = 'Check Eligibility';
                    }
                } catch (e) {
                    resultDiv.innerHTML = '<p class="text-xs text-red-600">Unable to check eligibility. Please try again.</p>';
                    btn.disabled = false;
                    btn.textContent = 'Check Eligibility';
                }
            };

            // Function to verify policy number (with automatic fallback to alternative methods)
            async function verifyPolicyNumber() {
                const insuranceCompanyId = insuranceCompanySelect?.value;
                const policyNumber = policyNumberInput?.value?.trim();

                if (!insuranceCompanyId) {
                    policyVerificationResult.innerHTML = '<p class="text-sm text-red-600">Please select an insurance company.</p>';
                    return;
                }

                if (!policyNumber) {
                    // If no policy number, show alternative verification options
                    showAlternativeVerificationOptions(insuranceCompanyId);
                    return;
                }

                verifyPolicyBtn.disabled = true;
                verifyPolicyBtn.textContent = 'Verifying...';
                policyVerificationResult.innerHTML = '<p class="text-sm text-blue-600">Verifying policy number...</p>';

                // Always refresh settings so toggled changes take effect immediately
                try {
                    const settingsRes = await fetch(`/api/insurance-settings/${insuranceCompanyId}`);
                    if (settingsRes.ok) {
                        const freshSettings = await settingsRes.json();
                        registrationSettings.show_policy_details = freshSettings?.show_policy_details_at_registration ?? true;
                        registrationSettings.fields_to_display = Array.isArray(freshSettings?.policy_details_to_display_at_registration)
                            ? freshSettings.policy_details_to_display_at_registration
                            : ['benefit_balance', 'deductible_amount', 'copay_amount', 'coinsurance_percentage'];
                    }
                } catch (e) { /* fall through with existing settings */ }

                try {
                // Collect name and DOB from form for tolerance-based verification
                const surnameInput = document.querySelector('input[name="surname"]');
                const firstNameInput = document.querySelector('input[name="first_name"]');
                const otherNamesInput = document.querySelector('input[name="other_names"]');
                const dobInput = document.querySelector('input[name="date_of_birth"]');
                
                // Build full name - clean any consultation/extra text but keep legitimate names
                const nameParts = [];
                if (surnameInput?.value?.trim()) {
                    let surname = surnameInput.value.trim();
                    // Remove common consultation text patterns
                    surname = surname.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max (to handle cases like "JOHN DOE" but avoid extra text)
                    const surnameWords = surname.split(/\s+/).slice(0, 2);
                    nameParts.push(...surnameWords);
                }
                if (firstNameInput?.value?.trim()) {
                    let firstName = firstNameInput.value.trim();
                    // Remove common consultation text patterns
                    firstName = firstName.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max
                    const firstNameWords = firstName.split(/\s+/).slice(0, 2);
                    nameParts.push(...firstNameWords);
                }
                if (otherNamesInput?.value?.trim()) {
                    let otherNames = otherNamesInput.value.trim();
                    // Remove common consultation text patterns
                    otherNames = otherNames.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max
                    const otherNamesWords = otherNames.split(/\s+/).slice(0, 2);
                    nameParts.push(...otherNamesWords);
                }
                
                const fullName = nameParts.filter(p => p && p.length > 0).join(' ').trim();
                const dateOfBirth = dobInput?.value || null;
                    
                    // Build URL with query parameters if name, DOB, or services category are available
                    let url = `/api/policies/verify/${insuranceCompanyId}/${encodeURIComponent(policyNumber)}`;
                    const params = new URLSearchParams();
                    if (fullName) {
                        params.append('name', fullName);
                    }
                    if (dateOfBirth) {
                        params.append('date_of_birth', dateOfBirth);
                    }
                    const servicesCategorySelect = document.getElementById('services_category');
                    const servicesCategory = servicesCategorySelect?.value || null;
                    if (servicesCategory) {
                        params.append('services_category', servicesCategory);
                    }
                    if (params.toString()) {
                        url += '?' + params.toString();
                    }
                    
                    // First try policy number verification
                    const response = await fetch(url);
                    const data = await response.json();

                    // Check if response indicates verification failure (policy found but rejected)
                    if ((response.status === 422 || (data.success === false && data.exists === true && data.verification_status === 'rejected'))) {
                        // Verification rejected due to name/DOB mismatch
                        let errorDetails = '';
                        if (data.errors && Array.isArray(data.errors)) {
                            errorDetails = '<div class="mt-2 pt-2 border-t border-red-300"><p class="text-xs font-semibold text-red-800 mb-1">Details:</p><ul class="list-disc list-inside space-y-1">' + 
                                data.errors.map(err => `<li class="text-xs text-red-700">${err}</li>`).join('') + 
                                '</ul></div>';
                        } else if (data.message) {
                            errorDetails = `<div class="mt-2 pt-2 border-t border-red-300"><p class="text-xs text-red-700">${data.message}</p></div>`;
                        }
                        
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg relative">
                                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Dismiss">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <p class="text-sm font-medium text-red-800">✗ Verification Failed</p>
                                <p class="text-xs text-red-700 mt-1 font-semibold">Policy number found, but verification failed.</p>
                                ${errorDetails}
                                <p class="text-xs text-red-600 mt-3 font-medium">Try alternative verification methods below:</p>
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-green-300');
                        policyNumberInput.classList.add('border-red-300');
                        document.getElementById('policy_verified').value = '0';
                        
                        // Show alternative verification options below the error
                        setTimeout(() => {
                            showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                        }, 100);
                    } else if (data.success && data.exists && data.verification_status !== 'rejected') {
                        const method = data.verification_method || 'policy_number';
                        const methodLabel = method === 'policy_number' ? 'Policy Number' : method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                        const verificationStatus = data.verification_status || 'verified';
                        
                        // Determine message color based on verification status
                        let statusColor = 'green';
                        let statusIcon = '✓';
                        if (verificationStatus === 'flagged') {
                            statusColor = 'yellow';
                            statusIcon = '⚠';
                        } else if (verificationStatus === 'rejected') {
                            statusColor = 'red';
                            statusIcon = '✗';
                        }
                        
                        // Build payment responsibility information display
                        // Only show benefit details if the setting allows it
                        const paymentInfo = data.data?.payment_responsibility;
                        let paymentInfoHtml = '';

                        if (paymentInfo && registrationSettings.show_policy_details) {
                            const fieldsToDisplay = registrationSettings.fields_to_display;
                            paymentInfoHtml = '<div class="mt-3 pt-3 border-t border-' + statusColor + '-300">';
                            paymentInfoHtml += '<p class="text-xs font-semibold text-' + statusColor + '-800 mb-2">Benefit Details:</p>';

                            if (fieldsToDisplay.includes('benefit_balance')) {
                                const balance = paymentInfo.benefit_balance;
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-${statusColor}-700">Benefit Balance: ${balance != null ? 'UGX ' + parseFloat(balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'N/A'}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Remaining insurance benefit for this client</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('deductible_amount') && paymentInfo.has_deductible && paymentInfo.deductible_amount) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-${statusColor}-700">Deductible: UGX ${parseFloat(paymentInfo.deductible_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Amount client must pay before insurance coverage begins</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('copay_amount') && paymentInfo.copay_amount) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-${statusColor}-700">Co-pay: UGX ${parseFloat(paymentInfo.copay_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} per visit</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Fixed amount payable at each visit</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('coinsurance_percentage') && paymentInfo.coinsurance_percentage) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-${statusColor}-700">Co-insurance: ${parseFloat(paymentInfo.coinsurance_percentage).toFixed(2)}%</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Percentage of invoice amount paid by client</p>
                                </div>`;
                            }

                            paymentInfoHtml += '</div>';
                        }

                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-${statusColor}-50 border border-${statusColor}-200 rounded-lg">
                                <p class="text-sm font-medium text-${statusColor}-800">${statusIcon} ${verificationStatus === 'rejected' ? 'Verification rejected' : verificationStatus === 'flagged' ? 'Verification flagged for review' : 'Verified successfully'} (${methodLabel})</p>
                                <p class="text-xs text-${statusColor}-700 mt-1">Policy holder: ${data.data?.principal_member_name || 'N/A'}</p>
                                <p class="text-xs text-${statusColor}-700">Status: ${data.data?.status || 'N/A'}</p>
                                ${data.warnings && data.warnings.length > 0 ? `<p class="text-xs text-yellow-700 mt-1">⚠ ${data.warnings.join(', ')}</p>` : ''}
                                ${paymentInfoHtml}
                            </div>
                        `;
                        
                        if (verificationStatus === 'rejected') {
                            policyNumberInput.classList.remove('border-green-300');
                            policyNumberInput.classList.add('border-red-300');
                            document.getElementById('policy_verified').value = '0';
                        } else {
                            policyNumberInput.classList.remove('border-red-300');
                            policyNumberInput.classList.add('border-green-300');
                            document.getElementById('policy_verified').value = '1';
                        }
                    } else if ((response.status === 422 || response.status === 400) && data.success === false && data.exists === true) {
                        // Policy found but verification rejected (name/DOB mismatch)
                        let errorMessage = data.message || 'Policy number found, but verification failed.';
                        let errorDetails = '';
                        
                        if (data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
                            errorDetails = '<div class="mt-3 pt-3 border-t border-red-300"><p class="text-xs font-semibold text-red-800 mb-2">Verification Errors:</p><ul class="list-disc list-inside space-y-1">' + 
                                data.errors.map(err => `<li class="text-xs text-red-700">${err}</li>`).join('') + 
                                '</ul></div>';
                        } else if (data.message && data.message !== errorMessage) {
                            errorDetails = `<div class="mt-3 pt-3 border-t border-red-300"><p class="text-xs text-red-700">${data.message}</p></div>`;
                        }
                        
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg relative">
                                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Dismiss">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <p class="text-sm font-medium text-red-800">✗ Verification Failed</p>
                                <p class="text-xs text-red-700 mt-1 font-semibold">Policy number found, but verification failed.</p>
                                ${errorDetails}
                                <p class="text-xs text-red-600 mt-3 font-medium">Try alternative verification methods below:</p>
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-green-300');
                        policyNumberInput.classList.add('border-red-300');
                        document.getElementById('policy_verified').value = '0';
                        
                        // Show alternative verification options below the error
                        setTimeout(() => {
                            showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                        }, 100);
                    } else if (data.success === false && data.exists === false) {
                        // Policy number not found - show alternative verification options
                        let errorMessage = data.message || 'Policy number not found or inactive.';
                        
                        // Automatically show alternative verification options
                        showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                    }
                } catch (error) {
                    console.error('Verification error:', error);
                    let errorMessage = 'Unable to verify. Please try again later.';
                    if (error.message) {
                        errorMessage = error.message;
                    }
                    
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg relative">
                            <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Dismiss">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                            <p class="text-sm font-medium text-red-800">✗ Verification Error</p>
                            <p class="text-xs text-red-700 mt-1 font-semibold">${errorMessage}</p>
                            <p class="text-xs text-red-600 mt-2">Please check your connection and try again. If the problem persists, contact support.</p>
                            <button type="button" onclick="showAlternativeVerificationOptions('${insuranceCompanyId}', '${policyNumber || ''}')" 
                                    class="mt-3 text-xs text-blue-600 hover:text-blue-800 underline">
                                Try alternative verification method
                            </button>
                        </div>
                    `;
                    policyNumberInput.classList.remove('border-green-300');
                    policyNumberInput.classList.add('border-red-300');
                    document.getElementById('policy_verified').value = '0';
                    // Scroll to error message
                    policyVerificationResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } finally {
                    verifyPolicyBtn.disabled = false;
                    verifyPolicyBtn.textContent = 'Verify';
                }
            }

            // Function to show alternative verification options
            function showAlternativeVerificationOptions(insuranceCompanyId, policyNumber = null) {
                // Collect available form data to check what's available
                const surnameInput = document.querySelector('input[name="surname"]');
                const firstNameInput = document.querySelector('input[name="first_name"]');
                const otherNamesInput = document.querySelector('input[name="other_names"]');
                const dobInput = document.querySelector('input[name="date_of_birth"]');
                const ninInput = document.querySelector('input[name="nin"]');
                const phoneInput = document.querySelector('input[name="phone_number"]');
                const emailInput = document.querySelector('input[name="email"]');

                // Check which methods have data available
                const hasName = (surnameInput?.value || firstNameInput?.value || otherNamesInput?.value) && dobInput?.value;
                const hasIdPassport = ninInput?.value;
                const hasPhone = phoneInput?.value;
                const hasEmail = emailInput?.value;

                let optionsHtml = `
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm font-medium text-yellow-800 mb-2">✗ Client not found please try other verification methods</p>
                        <p class="text-xs text-yellow-700 mb-3">Choose an option below:</p>
                        <div class="space-y-2">
                `;

                // Name & Date of Birth option
                if (hasName) {
                    optionsHtml += `
                        <button type="button" data-method="name_dob" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-yellow-300 rounded-lg hover:bg-yellow-100 transition-colors text-sm">
                            <span class="font-medium">Name & Date of Birth</span>
                            <span class="text-xs text-gray-600 block mt-1">Verify using full name and date of birth</span>
                        </button>
                    `;
                } else {
                    optionsHtml += `
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm opacity-60">
                            <span class="font-medium text-gray-600">Name & Date of Birth</span>
                            <span class="text-xs text-gray-500 block mt-1">Fill in name and date of birth fields first</span>
                        </div>
                    `;
                }

                // ID/Passport option
                if (hasIdPassport) {
                    optionsHtml += `
                        <button type="button" data-method="id_passport" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-yellow-300 rounded-lg hover:bg-yellow-100 transition-colors text-sm">
                            <span class="font-medium">ID/Passport Number</span>
                            <span class="text-xs text-gray-600 block mt-1">Verify using National ID or Passport number</span>
                        </button>
                    `;
                } else {
                    optionsHtml += `
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm opacity-60">
                            <span class="font-medium text-gray-600">ID/Passport Number</span>
                            <span class="text-xs text-gray-500 block mt-1">Fill in NIN/ID/Passport field first</span>
                        </div>
                    `;
                }

                // Phone option
                if (hasPhone) {
                    optionsHtml += `
                        <button type="button" data-method="phone" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-yellow-300 rounded-lg hover:bg-yellow-100 transition-colors text-sm">
                            <span class="font-medium">Phone Number</span>
                            <span class="text-xs text-gray-600 block mt-1">Verify using registered phone number</span>
                        </button>
                    `;
                } else {
                    optionsHtml += `
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm opacity-60">
                            <span class="font-medium text-gray-600">Phone Number</span>
                            <span class="text-xs text-gray-500 block mt-1">Fill in phone number field first</span>
                        </div>
                    `;
                }

                // Email option
                if (hasEmail) {
                    optionsHtml += `
                        <button type="button" data-method="email" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-yellow-300 rounded-lg hover:bg-yellow-100 transition-colors text-sm">
                            <span class="font-medium">Email Address</span>
                            <span class="text-xs text-gray-600 block mt-1">Verify using registered email address</span>
                        </button>
                    `;
                } else {
                    optionsHtml += `
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm opacity-60">
                            <span class="font-medium text-gray-600">Email Address</span>
                            <span class="text-xs text-gray-500 block mt-1">Fill in email field first</span>
                        </div>
                    `;
                }

                optionsHtml += `
                        </div>
                    </div>
                `;

                policyVerificationResult.innerHTML = optionsHtml;
                policyNumberInput.classList.remove('border-green-300');
                policyNumberInput.classList.add('border-red-300');
            }

            // Get third-party API URL from config
            const THIRD_PARTY_API_URL = '{{ config("services.third_party.api_url", "http://127.0.0.1:8001") }}';
            
            // Function to try a specific alternative verification method
            async function tryAlternativeMethod(method, insuranceCompanyId, policyNumber = null) {
                
                // Collect available form data for alternative verification
                const surnameInput = document.querySelector('input[name="surname"]');
                const firstNameInput = document.querySelector('input[name="first_name"]');
                const otherNamesInput = document.querySelector('input[name="other_names"]');
                const dobInput = document.querySelector('input[name="date_of_birth"]');
                const ninInput = document.querySelector('input[name="nin"]');
                const phoneInput = document.querySelector('input[name="phone_number"]');
                const emailInput = document.querySelector('input[name="email"]');
                const servicesCategorySelect = document.getElementById('services_category');


                const alternativeData = {};
                
                // Build full name (required) - clean consultation text but keep legitimate names
                const nameParts = [];
                if (surnameInput?.value?.trim()) {
                    let surname = surnameInput.value.trim();
                    // Remove common consultation text patterns
                    surname = surname.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max
                    const surnameWords = surname.split(/\s+/).slice(0, 2);
                    nameParts.push(...surnameWords);
                }
                if (firstNameInput?.value?.trim()) {
                    let firstName = firstNameInput.value.trim();
                    // Remove common consultation text patterns
                    firstName = firstName.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max
                    const firstNameWords = firstName.split(/\s+/).slice(0, 2);
                    nameParts.push(...firstNameWords);
                }
                if (otherNamesInput?.value?.trim()) {
                    let otherNames = otherNamesInput.value.trim();
                    // Remove common consultation text patterns
                    otherNames = otherNames.replace(/\s*(Consultation|Dr|KG|First|Post-op|review|\(0PU\)|\(.*?\))\s*/gi, '').trim();
                    // Take first 2 words max
                    const otherNamesWords = otherNames.split(/\s+/).slice(0, 2);
                    nameParts.push(...otherNamesWords);
                }
                
                const cleanNameParts = nameParts.filter(p => p && p.length > 0);
                if (cleanNameParts.length === 0) {
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm font-medium text-red-800">✗ Missing Information</p>
                            <p class="text-xs text-red-700 mt-1">Please fill in at least Surname or First Name.</p>
                        </div>
                    `;
                    return;
                }
                
                alternativeData.name = cleanNameParts.join(' ');

                // Date of birth (required)
                if (!dobInput?.value) {
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm font-medium text-red-800">✗ Missing Information</p>
                            <p class="text-xs text-red-700 mt-1">Please fill in Date of Birth.</p>
                        </div>
                    `;
                    return;
                }
                
                alternativeData.date_of_birth = dobInput.value;
                if (policyNumber) alternativeData.policy_number = policyNumber;
                if (servicesCategorySelect?.value) {
                    alternativeData.services_category = servicesCategorySelect.value;
                }

                // Show loading state
                policyVerificationResult.innerHTML = `
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm font-medium text-blue-800">Verifying using ${method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}...</p>
                    </div>
                `;

                try {
                    // Try alternative verification via POST
                    const response = await fetch(`/api/policies/verify/${insuranceCompanyId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify(alternativeData)
                    });

                    const data = await response.json();

                    if (data.success && data.exists) {
                        const methodLabel = data.verification_method ? data.verification_method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                        const statusLabel = data.verification_status === 'flagged' ? ' (Flagged for Review)' : '';
                        const statusColor = data.verification_status === 'flagged' ? 'yellow' : 'green';
                        
                        // Build payment responsibility information display
                        // Only show benefit details if the setting allows it
                        const paymentInfo = data.data?.payment_responsibility;
                        let paymentInfoHtml = '';

                        if (paymentInfo && registrationSettings.show_policy_details) {
                            const fieldsToDisplay = registrationSettings.fields_to_display;
                            paymentInfoHtml = '<div class="mt-3 pt-3 border-t border-green-300">';
                            paymentInfoHtml += '<p class="text-xs font-semibold text-green-800 mb-2">Benefit Details:</p>';

                            if (fieldsToDisplay.includes('benefit_balance')) {
                                const balance = paymentInfo.benefit_balance;
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-green-700">Benefit Balance: ${balance != null ? 'UGX ' + parseFloat(balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'N/A'}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Remaining insurance benefit for this client</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('deductible_amount') && paymentInfo.has_deductible && paymentInfo.deductible_amount) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-green-700">Deductible: UGX ${parseFloat(paymentInfo.deductible_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Amount client must pay before insurance coverage begins</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('copay_amount') && paymentInfo.copay_amount) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-green-700">Co-pay: UGX ${parseFloat(paymentInfo.copay_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} per visit</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Fixed amount payable at each visit</p>
                                </div>`;
                            }

                            if (fieldsToDisplay.includes('coinsurance_percentage') && paymentInfo.coinsurance_percentage) {
                                paymentInfoHtml += `<div class="mb-2">
                                    <p class="text-xs font-medium text-green-700">Co-insurance: ${parseFloat(paymentInfo.coinsurance_percentage).toFixed(2)}%</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Percentage of invoice amount paid by client</p>
                                </div>`;
                            }

                            paymentInfoHtml += '</div>';
                        }

                        policyVerificationResult.innerHTML = `
                            <div class="p-3 ${data.verification_status === 'flagged' ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'} border rounded-lg">
                                <p class="text-sm font-medium ${data.verification_status === 'flagged' ? 'text-yellow-800' : 'text-green-800'}">
                                    ✓ Verified using ${methodLabel}${statusLabel}
                                </p>
                                <p class="text-xs ${data.verification_status === 'flagged' ? 'text-yellow-700' : 'text-green-700'} mt-1">
                                    Policy holder: ${data.data?.principal_member_name || 'N/A'}
                                </p>
                                ${data.warnings && data.warnings.length > 0 ? `<p class="text-xs text-yellow-700 mt-1">⚠ ${data.warnings.join(', ')}</p>` : ''}
                                ${paymentInfoHtml}
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-red-300');
                        policyNumberInput.classList.add(data.verification_status === 'flagged' ? 'border-yellow-300' : 'border-green-300');
                        document.getElementById('policy_verified').value = '1';
                    } else {
                        // Verification failed - show detailed error
                        let errorMessage = data.message || 'Unable to verify using provided information.';
                        let errorDetails = '';
                        
                        if (data.mismatches && Array.isArray(data.mismatches)) {
                            errorDetails = '<p class="text-xs text-red-600 mt-2 font-semibold">Mismatches detected:</p><ul class="list-disc list-inside mt-1 space-y-1">' + 
                                data.mismatches.map(m => `<li class="text-xs text-red-600">${m}</li>`).join('') + 
                                '</ul>';
                        }
                        
                        if (data.details && Array.isArray(data.details)) {
                            errorDetails += '<div class="mt-2"><p class="text-xs text-red-600 font-semibold">Details:</p><ul class="list-disc list-inside mt-1 space-y-1">' + 
                                data.details.map(d => `<li class="text-xs text-red-600">${d}</li>`).join('') + 
                                '</ul></div>';
                        } else if (data.warnings && Array.isArray(data.warnings)) {
                            errorDetails += '<div class="mt-2"><p class="text-xs text-yellow-700 font-semibold">Warnings:</p><ul class="list-disc list-inside mt-1 space-y-1">' + 
                                data.warnings.map(w => `<li class="text-xs text-yellow-600">${w}</li>`).join('') + 
                                '</ul></div>';
                        }
                        
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg relative">
                                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Dismiss">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <p class="text-sm font-medium text-red-800">✗ Verification Failed</p>
                                <p class="text-xs text-red-700 mt-1 font-semibold">${errorMessage}</p>
                                ${errorDetails}
                                <button type="button" onclick="showAlternativeVerificationOptions('${insuranceCompanyId}', '${policyNumber || ''}')" 
                                        class="mt-3 text-xs text-blue-600 hover:text-blue-800 underline">
                                    Try another method
                                </button>
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-green-300');
                        policyNumberInput.classList.add('border-red-300');
                        document.getElementById('policy_verified').value = '0';
                        // Scroll to error message
                        policyVerificationResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                } catch (error) {
                    console.error('Alternative verification error:', error);
                    let errorMessage = 'Unable to verify. Please try again later.';
                    if (error.message) {
                        errorMessage = error.message;
                    }
                    
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg relative">
                            <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600" title="Dismiss">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                            <p class="text-sm font-medium text-red-800">✗ Verification Error</p>
                            <p class="text-xs text-red-700 mt-1 font-semibold">${errorMessage}</p>
                            <p class="text-xs text-red-600 mt-2">Please check your connection and try again. If the problem persists, contact support.</p>
                            <button type="button" onclick="showAlternativeVerificationOptions('${insuranceCompanyId}', '${policyNumber || ''}')" 
                                    class="mt-3 text-xs text-blue-600 hover:text-blue-800 underline">
                                Try another method
                            </button>
                        </div>
                    `;
                    policyNumberInput.classList.remove('border-green-300');
                    policyNumberInput.classList.add('border-red-300');
                    document.getElementById('policy_verified').value = '0';
                    // Scroll to error message
                    policyVerificationResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            
            // Function to send OTP for phone verification
            async function sendPhoneOtpForVerification(phone, insuranceCompanyId, policyNumber = null) {
                
                policyVerificationResult.innerHTML = `
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm font-medium text-blue-800">Sending OTP to ${phone}...</p>
                    </div>
                `;
                
                try {
                    const response = await fetch(THIRD_PARTY_API_URL + '/api/v1/clients/search-and-send-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ phone: phone })
                    });
                    
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        // If response is not JSON, create a simple error object
                        data = { success: false, message: `HTTP error! status: ${response.status}` };
                    }
                    
                    // Handle client not found (404 or success:false with "not found" message)
                    if (response.status === 404 || (!data.success && data.message && (data.message.toLowerCase().includes('not found') || data.message.toLowerCase().includes('client not found')))) {
                        // Client not found - ask for registered phone number
                        const { value: registeredPhone } = await Swal.fire({
                            icon: 'info',
                            title: 'Client Not Found',
                            html: `
                                <p class="mb-4">No client found with the phone number: <strong>${phone}</strong></p>
                                <p class="text-sm text-gray-600 mb-4">Please enter the phone number you registered with your insurance company:</p>
                            `,
                            input: 'tel',
                            inputLabel: 'Registered Phone Number',
                            inputPlaceholder: 'Enter your registered phone number',
                            showCancelButton: true,
                            confirmButtonText: 'Send OTP',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#3b82f6',
                            cancelButtonColor: '#6b7280',
                            inputValidator: (value) => {
                                if (!value) {
                                    return 'Please enter a phone number';
                                }
                                if (value.length < 9) {
                                    return 'Please enter a valid phone number';
                                }
                            }
                        });
                        
                        if (registeredPhone) {
                            await sendPhoneOtpForVerification(registeredPhone, insuranceCompanyId, policyNumber);
                        } else {
                            showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                        }
                        return;
                    }
                    
                    // Handle other errors
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    
                    if (data.success) {
                        // Escape phone and policyNumber for safe use in HTML
                        const safePhone = phone.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const safePolicyNumber = (policyNumber || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        
                        // Show OTP input
                        policyVerificationResult.innerHTML = `
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm font-medium text-blue-800 mb-2">✓ OTP sent to ${phone}</p>
                                <p class="text-xs text-blue-700 mb-3">Please check your SMS and enter the 6-digit OTP below:</p>
                                <div class="flex gap-2">
                                    <input type="text" id="policy_phone_otp" placeholder="Enter 6-digit OTP" maxlength="6"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <button type="button" id="verify_policy_phone_otp_btn" 
                                            data-phone="${safePhone}"
                                            data-insurance-id="${insuranceCompanyId}"
                                            data-policy-number="${safePolicyNumber}"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150">
                                        Verify OTP
                                    </button>
                                    <button type="button" id="resend_policy_phone_otp_btn" 
                                            data-phone="${safePhone}"
                                            data-insurance-id="${insuranceCompanyId}"
                                            data-policy-number="${safePolicyNumber}"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                                        Resend OTP
                                    </button>
                                </div>
                                <p id="policy_phone_otp_message" class="mt-2 text-xs"></p>
                            </div>
                        `;
                        
                        // Attach event listeners
                        document.getElementById('verify_policy_phone_otp_btn').addEventListener('click', function() {
                            const phone = this.getAttribute('data-phone');
                            const insuranceId = parseInt(this.getAttribute('data-insurance-id'));
                            const policyNum = this.getAttribute('data-policy-number') || null;
                            verifyPhoneOtpForPolicy(phone, insuranceId, policyNum);
                        });
                        
                        document.getElementById('resend_policy_phone_otp_btn').addEventListener('click', function() {
                            const phone = this.getAttribute('data-phone');
                            const insuranceId = parseInt(this.getAttribute('data-insurance-id'));
                            const policyNum = this.getAttribute('data-policy-number') || null;
                            resendPhoneOtpForVerification(phone, insuranceId, policyNum);
                        });
                        
                        document.getElementById('policy_phone_otp').focus();
                    }
                } catch (error) {
                    console.error('OTP send error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to Send OTP',
                        html: `
                            <p class="mb-2 text-sm">We encountered an issue while trying to send the OTP.</p>
                            <p class="text-xs text-gray-600 mt-2">${error.message}</p>
                            <p class="text-xs text-gray-500 mt-3">Please try again or use a different verification method.</p>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3b82f6'
                    });
                    showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                }
            }
            
            // Function to verify phone OTP and complete policy verification
            async function verifyPhoneOtpForPolicy(phone, insuranceCompanyId, policyNumber = null) {
                const otp = document.getElementById('policy_phone_otp').value.trim();
                const btn = document.getElementById('verify_policy_phone_otp_btn');
                const messageEl = document.getElementById('policy_phone_otp_message');
                
                if (!otp || otp.length !== 6) {
                    messageEl.textContent = 'Please enter a valid 6-digit OTP';
                    messageEl.className = 'mt-2 text-xs text-red-600';
                    return;
                }
                
                btn.disabled = true;
                btn.textContent = 'Verifying...';
                
                try {
                    // First verify the OTP
                    const otpResponse = await fetch(THIRD_PARTY_API_URL + '/api/v1/clients/verify-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ phone: phone, otp: otp })
                    });
                    
                    if (!otpResponse.ok) {
                        throw new Error(`HTTP error! status: ${otpResponse.status}`);
                    }
                    
                    const otpData = await otpResponse.json();
                    
                    if (!otpData.success) {
                        messageEl.textContent = otpData.message || 'Invalid OTP. Please try again.';
                        messageEl.className = 'mt-2 text-xs text-red-600';
                        if (otpData.attempts_remaining !== undefined) {
                            messageEl.textContent += ' (' + otpData.attempts_remaining + ' attempts remaining)';
                        }
                        btn.disabled = false;
                        btn.textContent = 'Verify OTP';
                        return;
                    }
                    
                    // OTP verified successfully, now verify policy
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">OTP verified. Verifying policy...</p>
                        </div>
                    `;
                    
                    const policyResponse = await fetch(`/api/policies/verify/${insuranceCompanyId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ phone: phone, policy_number: policyNumber })
                    });
                    
                    const policyData = await policyResponse.json();
                    
                    if (policyData.success && policyData.exists) {
                        const methodLabel = 'Phone Number';
                        const statusLabel = policyData.verification_status === 'flagged' ? ' (Flagged for Review)' : '';
                        
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 ${policyData.verification_status === 'flagged' ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'} border rounded-lg">
                                <p class="text-sm font-medium ${policyData.verification_status === 'flagged' ? 'text-yellow-800' : 'text-green-800'}">
                                    ✓ Verified using ${methodLabel}${statusLabel}
                                </p>
                                <p class="text-xs ${policyData.verification_status === 'flagged' ? 'text-yellow-700' : 'text-green-700'} mt-1">
                                    Policy holder: ${policyData.data?.principal_member_name || 'N/A'}
                                </p>
                                ${policyData.warnings && policyData.warnings.length > 0 ? `<p class="text-xs text-yellow-700 mt-1">⚠ ${policyData.warnings.join(', ')}</p>` : ''}
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-red-300');
                        policyNumberInput.classList.add(policyData.verification_status === 'flagged' ? 'border-yellow-300' : 'border-green-300');
                        document.getElementById('policy_verified').value = '1';
                    } else {
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm font-medium text-red-800">✗ Verification failed</p>
                                <p class="text-xs text-red-700 mt-1">${policyData.message || 'Unable to verify policy. Please try again.'}</p>
                                <button type="button" data-show-alternatives="true" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                        class="show-alternatives-btn mt-2 text-xs text-blue-600 hover:text-blue-800 underline">
                                    Try another method
                                </button>
                            </div>
                        `;
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Verification failed: ' + error.message,
                        confirmButtonColor: '#3b82f6'
                    });
                    showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Verify OTP';
                }
            }
            
            // Function to send OTP for email verification
            async function sendEmailOtpForVerification(email, insuranceCompanyId, policyNumber = null) {
                
                policyVerificationResult.innerHTML = `
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm font-medium text-blue-800">Sending OTP to ${email}...</p>
                    </div>
                `;
                
                try {
                    const response = await fetch(THIRD_PARTY_API_URL + '/api/v1/clients/search-and-send-otp-email', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ email: email })
                    });
                    
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        // If response is not JSON, create a simple error object
                        data = { success: false, message: `HTTP error! status: ${response.status}` };
                    }
                    
                    // Handle client not found (404 or success:false with "not found" message)
                    if (response.status === 404 || (!data.success && data.message && (data.message.toLowerCase().includes('not found') || data.message.toLowerCase().includes('client not found')))) {
                        // Client not found - ask for registered email
                        const { value: registeredEmail } = await Swal.fire({
                            icon: 'info',
                            title: 'Client Not Found',
                            html: `
                                <p class="mb-4">No client found with the email: <strong>${email}</strong></p>
                                <p class="text-sm text-gray-600 mb-4">Please enter the email address you registered with your insurance company:</p>
                            `,
                            input: 'email',
                            inputLabel: 'Registered Email Address',
                            inputPlaceholder: 'Enter your registered email address',
                            showCancelButton: true,
                            confirmButtonText: 'Send OTP',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#3b82f6',
                            cancelButtonColor: '#6b7280',
                            inputValidator: (value) => {
                                if (!value) {
                                    return 'Please enter an email address';
                                }
                                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                                    return 'Please enter a valid email address';
                                }
                            }
                        });
                        
                        if (registeredEmail) {
                            await sendEmailOtpForVerification(registeredEmail, insuranceCompanyId, policyNumber);
                        } else {
                            showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                        }
                        return;
                    }
                    
                    // Handle other errors
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    
                    if (data.success) {
                        // Escape email and policyNumber for safe use in HTML
                        const safeEmail = email.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const safePolicyNumber = (policyNumber || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        
                        // Show OTP input
                        policyVerificationResult.innerHTML = `
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm font-medium text-blue-800 mb-2">✓ OTP sent to ${email}</p>
                                <p class="text-xs text-blue-700 mb-3">Please check your inbox and enter the 6-digit OTP below:</p>
                                <div class="flex gap-2">
                                    <input type="text" id="policy_email_otp" placeholder="Enter 6-digit OTP" maxlength="6"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <button type="button" id="verify_policy_email_otp_btn" 
                                            data-email="${safeEmail}"
                                            data-insurance-id="${insuranceCompanyId}"
                                            data-policy-number="${safePolicyNumber}"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150">
                                        Verify OTP
                                    </button>
                                    <button type="button" id="resend_policy_email_otp_btn" 
                                            data-email="${safeEmail}"
                                            data-insurance-id="${insuranceCompanyId}"
                                            data-policy-number="${safePolicyNumber}"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                                        Resend OTP
                                    </button>
                                </div>
                                <p id="policy_email_otp_message" class="mt-2 text-xs"></p>
                            </div>
                        `;
                        
                        // Attach event listeners
                        document.getElementById('verify_policy_email_otp_btn').addEventListener('click', function() {
                            const email = this.getAttribute('data-email');
                            const insuranceId = parseInt(this.getAttribute('data-insurance-id'));
                            const policyNum = this.getAttribute('data-policy-number') || null;
                            verifyEmailOtpForPolicy(email, insuranceId, policyNum);
                        });
                        
                        document.getElementById('resend_policy_email_otp_btn').addEventListener('click', function() {
                            const email = this.getAttribute('data-email');
                            const insuranceId = parseInt(this.getAttribute('data-insurance-id'));
                            const policyNum = this.getAttribute('data-policy-number') || null;
                            resendEmailOtpForVerification(email, insuranceId, policyNum);
                        });
                        
                        document.getElementById('policy_email_otp').focus();
                    }
                } catch (error) {
                    console.error('OTP send error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to Send OTP',
                        html: `
                            <p class="mb-2 text-sm">We encountered an issue while trying to send the OTP.</p>
                            <p class="text-xs text-gray-600 mt-2">${error.message}</p>
                            <p class="text-xs text-gray-500 mt-3">Please try again or use a different verification method.</p>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3b82f6'
                    });
                    showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                }
            }
            
            // Function to resend phone OTP
            async function resendPhoneOtpForVerification(phone, insuranceCompanyId, policyNumber = null) {
                const btn = document.getElementById('resend_policy_phone_otp_btn');
                const messageEl = document.getElementById('policy_phone_otp_message');
                
                if (!btn) return;
                
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Sending...';
                if (messageEl) messageEl.textContent = '';
                
                try {
                    await sendPhoneOtpForVerification(phone, insuranceCompanyId, policyNumber);
                    
                    if (messageEl) {
                        messageEl.textContent = '✓ OTP resent successfully!';
                        messageEl.className = 'mt-2 text-xs text-green-600';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Resent',
                        text: 'A new OTP has been sent to your phone number.',
                        confirmButtonColor: '#3b82f6',
                        timer: 2000,
                        timerProgressBar: true
                    });
                } catch (error) {
                    console.error('Resend OTP error:', error);
                    if (messageEl) {
                        messageEl.textContent = 'Failed to resend OTP. Please try again.';
                        messageEl.className = 'mt-2 text-xs text-red-600';
                    }
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }
            
            // Function to verify email OTP and complete policy verification
            async function verifyEmailOtpForPolicy(email, insuranceCompanyId, policyNumber = null) {
                const otp = document.getElementById('policy_email_otp').value.trim();
                const btn = document.getElementById('verify_policy_email_otp_btn');
                const messageEl = document.getElementById('policy_email_otp_message');
                
                if (!otp || otp.length !== 6) {
                    messageEl.textContent = 'Please enter a valid 6-digit OTP';
                    messageEl.className = 'mt-2 text-xs text-red-600';
                    return;
                }
                
                btn.disabled = true;
                btn.textContent = 'Verifying...';
                
                try {
                    // First verify the OTP
                    const otpResponse = await fetch(THIRD_PARTY_API_URL + '/api/v1/clients/verify-otp-email', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ email: email, otp: otp })
                    });
                    
                    if (!otpResponse.ok) {
                        throw new Error(`HTTP error! status: ${otpResponse.status}`);
                    }
                    
                    const otpData = await otpResponse.json();
                    
                    if (!otpData.success) {
                        messageEl.textContent = otpData.message || 'Invalid OTP. Please try again.';
                        messageEl.className = 'mt-2 text-xs text-red-600';
                        if (otpData.attempts_remaining !== undefined) {
                            messageEl.textContent += ' (' + otpData.attempts_remaining + ' attempts remaining)';
                        }
                        btn.disabled = false;
                        btn.textContent = 'Verify OTP';
                        return;
                    }
                    
                    // OTP verified successfully, now verify policy
                    policyVerificationResult.innerHTML = `
                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">OTP verified. Verifying policy...</p>
                        </div>
                    `;
                    
                    const policyResponse = await fetch(`/api/policies/verify/${insuranceCompanyId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ email: email, policy_number: policyNumber })
                    });
                    
                    const policyData = await policyResponse.json();
                    
                    if (policyData.success && policyData.exists) {
                        const methodLabel = 'Email Address';
                        const statusLabel = policyData.verification_status === 'flagged' ? ' (Flagged for Review)' : '';
                        
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 ${policyData.verification_status === 'flagged' ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'} border rounded-lg">
                                <p class="text-sm font-medium ${policyData.verification_status === 'flagged' ? 'text-yellow-800' : 'text-green-800'}">
                                    ✓ Verified using ${methodLabel}${statusLabel}
                                </p>
                                <p class="text-xs ${policyData.verification_status === 'flagged' ? 'text-yellow-700' : 'text-green-700'} mt-1">
                                    Policy holder: ${policyData.data?.principal_member_name || 'N/A'}
                                </p>
                                ${policyData.warnings && policyData.warnings.length > 0 ? `<p class="text-xs text-yellow-700 mt-1">⚠ ${policyData.warnings.join(', ')}</p>` : ''}
                            </div>
                        `;
                        policyNumberInput.classList.remove('border-red-300');
                        policyNumberInput.classList.add(policyData.verification_status === 'flagged' ? 'border-yellow-300' : 'border-green-300');
                        document.getElementById('policy_verified').value = '1';
                    } else {
                        policyVerificationResult.innerHTML = `
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm font-medium text-red-800">✗ Verification failed</p>
                                <p class="text-xs text-red-700 mt-1">${policyData.message || 'Unable to verify policy. Please try again.'}</p>
                                <button type="button" data-show-alternatives="true" data-insurance-id="${insuranceCompanyId}" data-policy-number="${policyNumber || ''}" 
                                        class="show-alternatives-btn mt-2 text-xs text-blue-600 hover:text-blue-800 underline">
                                    Try another method
                                </button>
                            </div>
                        `;
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Verification failed: ' + error.message,
                        confirmButtonColor: '#3b82f6'
                    });
                    showAlternativeVerificationOptions(insuranceCompanyId, policyNumber);
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Verify OTP';
                }
            }
            
            // Function to resend email OTP
            async function resendEmailOtpForVerification(email, insuranceCompanyId, policyNumber = null) {
                const btn = document.getElementById('resend_policy_email_otp_btn');
                const messageEl = document.getElementById('policy_email_otp_message');
                
                if (!btn) return;
                
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Sending...';
                if (messageEl) messageEl.textContent = '';
                
                try {
                    await sendEmailOtpForVerification(email, insuranceCompanyId, policyNumber);
                    
                    if (messageEl) {
                        messageEl.textContent = '✓ OTP resent successfully!';
                        messageEl.className = 'mt-2 text-xs text-green-600';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Resent',
                        text: 'A new OTP has been sent to your email address.',
                        confirmButtonColor: '#3b82f6',
                        timer: 2000,
                        timerProgressBar: true
                    });
                } catch (error) {
                    console.error('Resend OTP error:', error);
                    if (messageEl) {
                        messageEl.textContent = 'Failed to resend OTP. Please try again.';
                        messageEl.className = 'mt-2 text-xs text-red-600';
                    }
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }

            // Event delegation for alternative verification buttons
            if (policyVerificationResult) {
                policyVerificationResult.addEventListener('click', function(e) {
                    // Handle alternative verification method buttons
                    if (e.target.closest('.alternative-verify-btn')) {
                        const btn = e.target.closest('.alternative-verify-btn');
                        const method = btn.getAttribute('data-method');
                        const insuranceId = btn.getAttribute('data-insurance-id');
                        const policyNum = btn.getAttribute('data-policy-number') || null;
                        tryAlternativeMethod(method, insuranceId, policyNum);
                    }
                    
                    // Handle "Try another method" buttons
                    if (e.target.closest('.show-alternatives-btn')) {
                        const btn = e.target.closest('.show-alternatives-btn');
                        const insuranceId = btn.getAttribute('data-insurance-id');
                        const policyNum = btn.getAttribute('data-policy-number') || null;
                        showAlternativeVerificationOptions(insuranceId, policyNum);
                    }
                });
            }

            // Add event listeners
            const insuranceCheckbox = document.getElementById('payment_insurance');
            if (insuranceCheckbox) {
                insuranceCheckbox.addEventListener('change', toggleInsuranceSection);
            }

            // Check on page load if insurance is already selected
            if (isInsuranceSelected()) {
                toggleInsuranceSection();
            }

            // Form submission validation for fallback payment method
            const individualForm = document.getElementById('client-registration-form-individual');
            if (individualForm) {
                individualForm.addEventListener('submit', function(e) {
                    if (isInsuranceSelected() && !hasFallbackPaymentMethod()) {
                        e.preventDefault();
                        const fallbackWarning = document.getElementById('fallback_payment_warning');
                        if (fallbackWarning) {
                            fallbackWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            fallbackWarning.style.display = 'block';
                        }
                        alert('Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn\'t cover the full amount.');
                        return false;
                    }
                });
            }

            if (insuranceCompanySelect) {
                insuranceCompanySelect.addEventListener('change', async function() {
                    saveCurrentVerificationState();  // Save current state before switching
                    const companyId = insuranceCompanySelect.value;
                    await togglePolicyNumberSection(true);  // Wait for toggle and pass true to skip reset
                    if (companyId) {
                        restoreVerificationState(companyId);  // Restore saved state after toggle completes
                    }
                });
            }

            if (verifyPolicyBtn) {
                verifyPolicyBtn.addEventListener('click', verifyPolicyNumber);
            }

            // Also verify on policy number input blur
            if (policyNumberInput) {
                policyNumberInput.addEventListener('input', function() {
                    // Any edit invalidates previous verification.
                    if (policyVerifiedInput) policyVerifiedInput.value = '0';
                    if (policyVerificationResult) policyVerificationResult.innerHTML = '';
                });

                policyNumberInput.addEventListener('blur', function() {
                    if (policyNumberInput.value.trim() && insuranceCompanySelect?.value) {
                        verifyPolicyNumber();
                    }
                });
            }
        })();

        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById('tab-content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
        }

        // Global fallback payment method validation for all forms
        function validateFallbackPaymentMethod(formId, warningId) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                const paymentCheckboxes = form.querySelectorAll('input[name="payment_methods[]"]:checked');
                let hasInsurance = false;
                let hasNonInsurance = false;

                paymentCheckboxes.forEach(checkbox => {
                    if (checkbox.value === 'insurance') {
                        hasInsurance = true;
                    } else {
                        hasNonInsurance = true;
                    }
                });

                if (hasInsurance && !hasNonInsurance) {
                    e.preventDefault();
                    const fallbackWarning = document.getElementById(warningId);
                    if (fallbackWarning) {
                        fallbackWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        fallbackWarning.style.display = 'block';
                    }
                    alert('Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn\'t cover the full amount.');
                    return false;
                }
            });
        }

        // Apply validation to all forms
        validateFallbackPaymentMethod('client-registration-form-individual', 'fallback_payment_warning');
        validateFallbackPaymentMethod('client-registration-form-company', 'fallback_payment_warning_company');
        validateFallbackPaymentMethod('client-registration-form-walk_in', 'fallback_payment_warning_walkin');

        // Multi-vendor insurance company handling
        (function() {
            const vendorCheckboxes = document.querySelectorAll('.vendor-checkbox');
            const vendorPoliciesSection = document.getElementById('vendor_policies_section');
            const vendorPoliciesContainer = document.getElementById('vendor_policies_container');
            const insuranceSection = document.getElementById('insurance_company_section');

            // Cache for insurance company settings
            const settingsCache = {};

            // Store verification state per vendor to prevent data loss during re-render
            const vendorVerificationStates = {};
            
            // Track vendors with invalid verifications (service category mismatch)
            const invalidVendors = new Set();

            // Get all available insurance companies from the checkboxes
            function getVendorInfo(vendorId) {
                const checkbox = document.querySelector(`.vendor-checkbox[value="${vendorId}"]`);
                if (!checkbox) return null;
                
                const label = checkbox.closest('label');
                const vendorName = label ? label.textContent.trim() : `Vendor ${vendorId}`;
                return { id: vendorId, name: vendorName };
            }

            // Fetch insurance company settings from API (merged: open enrollment + registration settings)
            async function fetchInsuranceSettings(insuranceCompanyId) {
                if (settingsCache[insuranceCompanyId]) {
                    return settingsCache[insuranceCompanyId];
                }
                try {
                    const response = await fetch(`/api/insurance-settings/${insuranceCompanyId}`);
                    if (response.ok) {
                        const data = await response.json();
                        settingsCache[insuranceCompanyId] = data;
                        return data;
                    }
                } catch (error) {
                    console.error('Error fetching insurance settings:', error);
                }
                return null;
            }

            // Save verification state for all vendors before re-render
            function saveAllVendorStates() {
                vendorCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const vendorId = checkbox.value;
                        const policyInput = document.querySelector(`[name="insurance_vendor_data[${vendorId}][policy_number]"]`);
                        const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);
                        
                        if (policyInput || resultDiv) {
                            // Save current service category used during verification
                            const currentServiceCategory = document.getElementById('services_category')?.value || '';
                            
                            vendorVerificationStates[vendorId] = {
                                policyNumber: policyInput?.value || '',
                                resultHtml: resultDiv?.innerHTML || '',
                                inputClass: policyInput?.className || '',
                                verifiedServiceCategory: currentServiceCategory,  // ← Store verified category
                            };
                        }
                    }
                });
            }

            // Invalidate all vendor verifications if service category changes
            function invalidateVerificationsIfCategoryChanged() {
                const currentCategory = document.getElementById('services_category')?.value || '';
                let hasInvalidated = false;

                // Check each vendor's verified category
                Object.keys(vendorVerificationStates).forEach(vendorId => {
                    const state = vendorVerificationStates[vendorId];
                    
                    // If service category has changed since verification
                    if (state.verifiedServiceCategory && state.verifiedServiceCategory !== currentCategory) {
                        const policyInput = document.querySelector(`[name="insurance_vendor_data[${vendorId}][policy_number]"]`);
                        const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);
                        
                        if (policyInput) {
                            policyInput.classList.remove('border-green-400');
                            policyInput.classList.add('border-red-400');
                        }
                        
                        if (resultDiv) {
                            resultDiv.innerHTML = `
                                <div class="p-2 bg-red-50 border border-red-200 rounded">
                                    <p class="text-sm text-red-700 font-medium">⚠ Verification invalid</p>
                                    <p class="text-xs text-red-600 mt-1">Service category has changed. Please verify this policy again with the new category.</p>
                                </div>`;
                        }
                        
                        // Add to invalid set and disable submit button
                        invalidVendors.add(vendorId);
                        updateSubmitButtonState();
                        
                        hasInvalidated = true;
                    }
                });

                return hasInvalidated;
            }

            // Update submit button state based on invalid vendors
            function updateSubmitButtonState() {
                const individualForm = document.getElementById('client-registration-form-individual');
                const submitBtn = individualForm?.querySelector('button[type="submit"]');
                
                if (submitBtn) {
                    if (invalidVendors.size > 0) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        submitBtn.title = `${invalidVendors.size} vendor verification(s) need to be re-verified with the current service category`;
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        submitBtn.title = '';
                    }
                }
            }

            // Restore verification state for a specific vendor after re-render
            function restoreVendorState(vendorId) {
                if (!vendorVerificationStates[vendorId]) return;

                const state = vendorVerificationStates[vendorId];
                const policyInput = document.querySelector(`[name="insurance_vendor_data[${vendorId}][policy_number]"]`);
                const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);

                if (policyInput) {
                    policyInput.value = state.policyNumber;
                    // Restore styling (border colors for verified/failed states)
                    if (state.inputClass.includes('border-green')) {
                        policyInput.classList.add('border-green-400');
                        policyInput.classList.remove('border-red-400');
                    } else if (state.inputClass.includes('border-red')) {
                        policyInput.classList.add('border-red-400');
                        policyInput.classList.remove('border-green-400');
                    }
                }

                if (resultDiv) {
                    resultDiv.innerHTML = state.resultHtml;
                }
            }

            // Get currently selected vendors
            function getSelectedVendors() {
                const selected = [];
                vendorCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        selected.push({
                            id: checkbox.value,
                            name: getVendorInfo(checkbox.value).name
                        });
                    }
                });
                return selected;
            }

            // Build HTML for an open-enrollment vendor card
            function buildOEVendorCard(vendor, index, settings) {
                const oe = settings?.open_enrollment ?? {};
                const criteria = [];
                if (oe.min_age != null || oe.max_age != null) criteria.push(`Age: ${oe.min_age ?? 0}–${oe.max_age ?? '∞'}`);
                if (oe.genders?.length) criteria.push(`Gender: ${oe.genders.join(', ')}`);
                if (oe.service_categories?.length) criteria.push(`Categories: ${oe.service_categories.join(', ')}`);
                const criteriaHtml = criteria.length
                    ? criteria.map(c => `<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full mr-1 mb-1">${c}</span>`).join('')
                    : '<span class="text-xs text-gray-500">No specific criteria — all eligible</span>';
                return `
                    <div class="bg-white rounded-lg border-2 border-blue-300 p-4">
                        <h6 class="text-sm font-semibold text-gray-900 mb-3">
                            <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-600 text-white text-xs rounded-full mr-2">${index + 1}</span>
                            ${vendor.name}
                            <span class="ml-2 text-xs font-normal text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Open Enrollment</span>
                        </h6>
                        <div class="space-y-3">
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-xs font-medium text-blue-800 mb-2">Eligibility Criteria</p>
                                <div>${criteriaHtml}</div>
                            </div>
                            <input type="hidden" name="insurance_vendor_data[${vendor.id}][policy_number]" value="" data-vendor-id="${vendor.id}">
                            <input type="hidden" name="insurance_vendor_data[${vendor.id}][is_open_enrollment]" value="1">
                            <button type="button" class="oe-enroll-btn w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium" data-vendor-id="${vendor.id}">
                                Check Eligibility &amp; Enroll
                            </button>
                            <div class="oe-result-div mt-2" data-vendor-id="${vendor.id}"></div>
                            <div class="border-t pt-3">
                                <label class="flex items-start p-3 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100 transition-colors">
                                    <input type="hidden" name="insurance_vendor_data[${vendor.id}][physical_insurance_card_verified]" value="0">
                                    <input type="checkbox" name="insurance_vendor_data[${vendor.id}][physical_insurance_card_verified]" value="1" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-0.5">
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-900">Physical Insurance Card</p>
                                        <p class="text-xs text-green-700 mt-1">I confirm that the client has presented their physical insurance card for verification and I have verified it matches the client.</p>
                                    </div>
                                </label>
                                <!-- Hidden fields for policy details (for consistency, even if not used for OE) -->
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][deductible_amount]" value="">
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][copay_amount]" value="">
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][coinsurance_percentage]" value="">
                            </div>
                        </div>
                    </div>`;
            }

            // Build HTML for a regular (policy-number) vendor card
            function buildRegularVendorCard(vendor, index) {
                return `
                    <div class="bg-white rounded-lg border-2 border-green-300 p-4">
                        <h6 class="text-sm font-semibold text-gray-900 mb-3">
                            <span class="inline-flex items-center justify-center w-5 h-5 bg-green-600 text-white text-xs rounded-full mr-2">${index + 1}</span>
                            ${vendor.name}
                        </h6>
                        <div class="space-y-3">
                            <div>
                                <label for="vendor_${vendor.id}_policy_number" class="block text-sm font-medium text-gray-700 mb-2">
                                    Policy Number <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <input type="text" name="insurance_vendor_data[${vendor.id}][policy_number]" id="vendor_${vendor.id}_policy_number"
                                        placeholder="Enter policy number for ${vendor.name}"
                                        class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                                        required data-vendor-id="${vendor.id}">
                                    <button type="button" class="verify-policy-btn px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium" data-vendor-id="${vendor.id}">Verify</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Policy details (deductible, copay, etc.) will be auto-filled after verification</p>
                                <div class="policy-verification-result mt-2" data-vendor-id="${vendor.id}"></div>
                            </div>
                            <div class="border-t pt-3 space-y-2">
                                <p class="text-xs font-medium text-gray-600">Or verify using alternative methods:</p>
                                <button type="button" class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-yellow-300 rounded-lg hover:bg-yellow-100 transition-colors text-sm" data-vendor-id="${vendor.id}" data-method="name-dob">
                                    <span class="font-medium">By Full Name &amp; Date of Birth</span>
                                    <span class="text-xs text-gray-600 block mt-1">Verify using full name and date of birth</span>
                                </button>
                                <button type="button" class="alternative-verify-btn w-full text-left px-3 py-2 bg-white border border-orange-300 rounded-lg hover:bg-orange-100 transition-colors text-sm" data-vendor-id="${vendor.id}" data-method="member-id">
                                    <span class="font-medium">By Member ID &amp; Phone</span>
                                    <span class="text-xs text-gray-600 block mt-1">Verify using member ID and phone number</span>
                                </button>
                            </div>
                            <div class="border-t pt-3">
                                <label class="flex items-start p-3 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100 transition-colors">
                                    <input type="hidden" name="insurance_vendor_data[${vendor.id}][physical_insurance_card_verified]" value="0">
                                    <input type="checkbox" name="insurance_vendor_data[${vendor.id}][physical_insurance_card_verified]" value="1" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-0.5">
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-900">Physical Insurance Card</p>
                                        <p class="text-xs text-green-700 mt-1">I confirm that the client has presented their physical insurance card for verification and I have verified it matches the client.</p>
                                    </div>
                                </label>
                                <!-- Hidden fields for policy details populated after verification -->
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][deductible_amount]" value="">
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][copay_amount]" value="">
                                <input type="hidden" name="insurance_vendor_data[${vendor.id}][coinsurance_percentage]" value="">
                            </div>
                        </div>
                    </div>`;
            }

            // Render vendor-specific policy forms (async: fetches OE settings per vendor)
            async function renderVendorPolicyForms() {
                // Save all verification states BEFORE clearing the container
                saveAllVendorStates();

                const selectedVendors = getSelectedVendors();
                
                console.log('DEBUG: renderVendorPolicyForms called', {
                    selectedVendorCount: selectedVendors.length,
                    selectedVendorIds: selectedVendors.map(v => v.id),
                    checkedCheckboxes: Array.from(vendorCheckboxes).filter(cb => cb.checked).map(cb => cb.value)
                });

                if (selectedVendors.length === 0) {
                    vendorPoliciesSection.style.display = 'none';
                    vendorPoliciesContainer.innerHTML = '';
                    // Clear invalid vendors when insurance is unchecked
                    invalidVendors.clear();
                    updateSubmitButtonState();
                    return;
                }

                // Clear invalid vendors for vendors being re-rendered
                // This allows users to re-verify when they re-check vendors
                selectedVendors.forEach(vendor => {
                    invalidVendors.delete(vendor.id);
                });

                vendorPoliciesSection.style.display = 'block';

                // Fetch settings for all selected vendors in parallel
                await Promise.all(selectedVendors.map(v => fetchInsuranceSettings(v.id)));

                // Build cards based on OE status
                let html = '';
                selectedVendors.forEach((vendor, index) => {
                    const settings = settingsCache[vendor.id];
                    const isOE = settings?.open_enrollment?.enabled ?? false;
                    html += isOE ? buildOEVendorCard(vendor, index, settings) : buildRegularVendorCard(vendor, index);
                });

                vendorPoliciesContainer.innerHTML = html;

                // Restore verification states for each vendor
                selectedVendors.forEach(vendor => {
                    restoreVendorState(vendor.id);
                });

                // Attach event listeners to open-enrollment enroll buttons
                document.querySelectorAll('.oe-enroll-btn').forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const vendorId = this.getAttribute('data-vendor-id');
                        const resultDiv = document.querySelector(`.oe-result-div[data-vendor-id="${vendorId}"]`);
                        const policyInput = document.querySelector(`[name="insurance_vendor_data[${vendorId}][policy_number]"]`);

                        this.disabled = true;
                        this.textContent = 'Checking...';

                        // Gather client details already entered in the form
                        const surname = document.getElementById('surname')?.value?.trim() || '';
                        const firstName = document.getElementById('first_name')?.value?.trim() || '';
                        const name = [surname, firstName].filter(Boolean).join(' ');
                        const dob = document.getElementById('date_of_birth')?.value || '';
                        const sex = document.getElementById('sex')?.value || '';
                        const servicesCategory = document.getElementById('services_category')?.value || '';

                        const params = new URLSearchParams();
                        if (name) params.set('name', name);
                        if (dob)  params.set('date_of_birth', dob);
                        if (sex)  params.set('gender', sex);
                        if (servicesCategory) params.set('services_category', servicesCategory);

                        try {
                            const response = await fetch(`/api/policies/verify/${vendorId}/__open_enrollment__?${params}`);
                            const data = await response.json();

                            if (data.success && data.exists) {
                                if (policyInput) policyInput.value = data.data?.policy_number || '__open_enrollment__';
                                const policyVerifiedInput = document.getElementById('policy_verified');
                                if (policyVerifiedInput) policyVerifiedInput.value = '1';

                                resultDiv.innerHTML = `
                                    <div class="p-2 bg-green-50 border border-green-200 rounded">
                                        <p class="text-sm text-green-700 font-medium">✓ Eligible — enrolled via open enrollment</p>
                                        <p class="text-xs text-green-600">${data.message || ''}</p>
                                    </div>`;
                                this.textContent = '✓ Enrolled';
                                this.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                                this.classList.add('bg-green-600', 'hover:bg-green-700');
                            } else {
                                resultDiv.innerHTML = `<p class="text-sm text-red-600">${data.message || 'Client does not meet eligibility criteria.'}</p>`;
                                this.disabled = false;
                                this.textContent = 'Check Eligibility & Enroll';
                            }
                        } catch (err) {
                            resultDiv.innerHTML = '<p class="text-sm text-red-600">Error checking eligibility. Please try again.</p>';
                            this.disabled = false;
                            this.textContent = 'Check Eligibility & Enroll';
                        }
                    });
                });

                // Attach event listeners to verify buttons
                document.querySelectorAll('.verify-policy-btn').forEach(btn => {
                    btn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        const vendorId = this.getAttribute('data-vendor-id');
                        const policyInput = document.querySelector(`[name="insurance_vendor_data[${vendorId}][policy_number]"]`);
                        const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);
                        
                        if (!policyInput.value.trim()) {
                            resultDiv.innerHTML = '<p class="text-sm text-red-600">Please enter a policy number</p>';
                            return;
                        }
                        
                        this.disabled = true;
                        this.textContent = 'Verifying...';

                        try {
                            const verifyParams = new URLSearchParams();
                            const servCat = document.getElementById('services_category')?.value || '';
                            if (servCat) verifyParams.set('services_category', servCat);
                            const verifyUrl = `/api/policies/verify/${vendorId}/${encodeURIComponent(policyInput.value)}?${verifyParams}`;
                            const response = await fetch(verifyUrl);
                            const data = await response.json();

                            if (data.success && data.exists) {
                                // Mark policy as verified so the form can be submitted
                                const policyVerifiedInput = document.getElementById('policy_verified');
                                if (policyVerifiedInput) policyVerifiedInput.value = '1';

                                // Auto-populate financial details from payment_responsibility
                                const paymentResp = data.data?.payment_responsibility;
                                if (paymentResp) {
                                    if (paymentResp.deductible_amount) {
                                        document.querySelector(`[name="insurance_vendor_data[${vendorId}][deductible_amount]"]`)?.setAttribute('value', paymentResp.deductible_amount);
                                    }
                                    if (paymentResp.copay_amount) {
                                        document.querySelector(`[name="insurance_vendor_data[${vendorId}][copay_amount]"]`)?.setAttribute('value', paymentResp.copay_amount);
                                    }
                                    if (paymentResp.coinsurance_percentage) {
                                        document.querySelector(`[name="insurance_vendor_data[${vendorId}][coinsurance_percentage]"]`)?.setAttribute('value', paymentResp.coinsurance_percentage);
                                    }
                                }

                                const memberName = data.data?.principal_member_name || '';
                                const policyStatus = data.data?.status || '';

                                // Build payment detail lines based on third-party settings (always fresh)
                                delete settingsCache[vendorId];
                                const vendorSettings = await fetchInsuranceSettings(vendorId);
                                const showDetails = vendorSettings?.show_policy_details_at_registration ?? true;
                                const fieldsToDisplay = Array.isArray(vendorSettings?.policy_details_to_display_at_registration)
                                    ? vendorSettings.policy_details_to_display_at_registration
                                    : ['benefit_balance', 'deductible_amount', 'copay_amount', 'coinsurance_percentage'];

                                let detailLines = '';
                                if (showDetails && paymentResp) {
                                    const fmt = n => parseFloat(n).toLocaleString();
                                    if (fieldsToDisplay.includes('benefit_balance') && paymentResp.benefit_balance != null)
                                        detailLines += `<p class="text-xs text-green-700">Benefit Balance: UGX ${fmt(paymentResp.benefit_balance)}</p>`;
                                    if (fieldsToDisplay.includes('deductible_amount') && paymentResp.deductible_amount)
                                        detailLines += `<p class="text-xs text-green-700">Deductible: UGX ${fmt(paymentResp.deductible_amount)}</p>`;
                                    if (fieldsToDisplay.includes('copay_amount') && paymentResp.copay_amount)
                                        detailLines += `<p class="text-xs text-green-700">Co-pay: UGX ${fmt(paymentResp.copay_amount)}</p>`;
                                    if (fieldsToDisplay.includes('coinsurance_percentage') && paymentResp.coinsurance_percentage)
                                        detailLines += `<p class="text-xs text-green-700">Co-insurance: ${paymentResp.coinsurance_percentage}%</p>`;
                                }

                                resultDiv.innerHTML = `
                                    <div class="p-2 bg-green-50 border border-green-200 rounded">
                                        <p class="text-sm text-green-700 font-medium">Policy verified successfully</p>
                                        ${memberName ? `<p class="text-xs text-green-600">Policy holder: ${memberName}</p>` : ''}
                                        ${detailLines}
                                    </div>`;
                                policyInput.classList.add('border-green-400');
                                policyInput.classList.remove('border-red-400');
                                
                                // Store verified state including the service category used
                                const currentServiceCategory = document.getElementById('services_category')?.value || '';
                                vendorVerificationStates[vendorId] = {
                                    policyNumber: policyInput.value,
                                    resultHtml: resultDiv.innerHTML,
                                    inputClass: 'border-green-400',
                                    verifiedServiceCategory: currentServiceCategory,
                                };
                                
                                // Remove from invalid set if it was there, and update button
                                if (invalidVendors.has(vendorId)) {
                                    invalidVendors.delete(vendorId);
                                    updateSubmitButtonState();
                                }
                            } else {
                                const policyVerifiedInput = document.getElementById('policy_verified');
                                if (policyVerifiedInput) policyVerifiedInput.value = '0';
                                const msg = data.message || 'Policy not found. Try alternative methods below.';
                                resultDiv.innerHTML = `<p class="text-sm text-red-600">${msg}</p>`;
                                policyInput.classList.add('border-red-400');
                                policyInput.classList.remove('border-green-400');
                            }
                        } catch (error) {
                            resultDiv.innerHTML = '<p class="text-sm text-red-600">Error verifying policy. Please try again.</p>';
                        }
                        
                        this.disabled = false;
                        this.textContent = 'Verify';
                    });
                });
                
                // Attach event listeners to alternative verification buttons
                document.querySelectorAll('.alternative-verify-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const vendorId = this.getAttribute('data-vendor-id');
                        const method = this.getAttribute('data-method');
                        const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);
                        
                        if (method === 'name-dob') {
                            resultDiv.innerHTML = `
                                <div class="bg-yellow-50 border border-yellow-300 rounded p-3 mt-2">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Verify by Name & Date of Birth</p>
                                    <input type="text" placeholder="Full name" class="w-full text-xs px-2 py-1 border border-yellow-300 rounded mb-1" data-field="name">
                                    <input type="date" class="w-full text-xs px-2 py-1 border border-yellow-300 rounded mb-2" data-field="dob">
                                    <button type="button" class="complete-alt-verify w-full text-xs bg-yellow-600 text-white px-2 py-1 rounded hover:bg-yellow-700" data-vendor-id="${vendorId}" data-method="name-dob">Verify</button>
                                </div>
                            `;
                        } else if (method === 'member-id') {
                            resultDiv.innerHTML = `
                                <div class="bg-orange-50 border border-orange-300 rounded p-3 mt-2">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Verify by Member ID & Phone</p>
                                    <input type="text" placeholder="Member ID" class="w-full text-xs px-2 py-1 border border-orange-300 rounded mb-1" data-field="member-id">
                                    <input type="tel" placeholder="Phone number" class="w-full text-xs px-2 py-1 border border-orange-300 rounded mb-2" data-field="phone">
                                    <button type="button" class="complete-alt-verify w-full text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700" data-vendor-id="${vendorId}" data-method="member-id">Verify</button>
                                </div>
                            `;
                            
                            // Add listener for completing alt verification
                            setTimeout(() => {
                                document.querySelector('.complete-alt-verify')?.addEventListener('click', async function() {
                                    const vendorId = this.getAttribute('data-vendor-id');
                                    const method = this.getAttribute('data-method');
                                    const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${vendorId}"]`);
                                    
                                    if (method === 'name-dob') {
                                        const name = resultDiv.querySelector('[data-field="name"]')?.value;
                                        const dob = resultDiv.querySelector('[data-field="dob"]')?.value;
                                        if (!name || !dob) {
                                            resultDiv.innerHTML = '<p class="text-xs text-red-600">Please fill all fields</p>';
                                            return;
                                        }
                                    } else if (method === 'member-id') {
                                        const memberId = resultDiv.querySelector('[data-field="member-id"]')?.value;
                                        const phone = resultDiv.querySelector('[data-field="phone"]')?.value;
                                        if (!memberId || !phone) {
                                            resultDiv.innerHTML = '<p class="text-xs text-red-600">Please fill all fields</p>';
                                            return;
                                        }
                                    }
                                    
                                    resultDiv.innerHTML = '<p class="text-xs text-green-600">✓ Alternative verification completed</p>';
                                });
                            }, 0);
                        }
                    });
                });
                
                // Attach event listeners to policy number inputs to trigger preview update
                // Also reset policy_verified when user edits a policy number field
                document.querySelectorAll('[name*="insurance_vendor_data"][name*="policy_number"]').forEach(input => {
                    input.addEventListener('input', function() {
                        const policyVerifiedInput = document.getElementById('policy_verified');
                        if (policyVerifiedInput) policyVerifiedInput.value = '0';
                        const resultDiv = document.querySelector(`.policy-verification-result[data-vendor-id="${this.getAttribute('data-vendor-id')}"]`);
                        if (resultDiv) resultDiv.innerHTML = '';
                        this.classList.remove('border-green-400', 'border-red-400');
                    });
                    input.addEventListener('blur', function() {});
                });

                // Update submit button state after rendering vendors
                updateSubmitButtonState();
            }

            // Get selected vendors
            function getSelectedVendorsList() {
                return Array.from(vendorCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => ({
                        id: cb.value,
                        name: getVendorInfo(cb.value)?.name || `Vendor ${cb.value}`
                    }));
            }

            // Update priority assignment UI
            function updatePriorityAssignment() {
                const prioritySection = document.getElementById('insurance_priority_section');
                const priorityContainer = document.getElementById('priority_assignment_container');
                const selectedVendors = getSelectedVendorsList();
                const warningDiv = document.getElementById('insurance_selection_warning');

                // Enforce max 3 insurance companies limit
                if (selectedVendors.length > 3) {
                    // Uncheck the last checked vendor (revert to 3)
                    const lastChecked = Array.from(vendorCheckboxes).find(cb => cb.checked);
                    if (lastChecked) {
                        lastChecked.checked = false;
                    }
                    warningDiv.style.display = 'block';
                    // Recompute after unchecking
                    updatePriorityAssignment();
                    return;
                } else {
                    warningDiv.style.display = 'none';
                }

                // Show/hide priority section based on selection count
                if (selectedVendors.length > 1) {
                    prioritySection.style.display = 'block';
                    
                    // Generate priority assignment UI
                    let priorityHtml = '';
                    const priorityLabels = ['🥇 Primary (1st)', '🥈 Secondary (2nd)', '🥉 Tertiary (3rd)'];
                    
                    selectedVendors.forEach((vendor, index) => {
                        priorityHtml += `
                            <div class="p-3 bg-white border border-gray-200 rounded-lg">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ${vendor.name}
                                </label>
                                <select name="insurance_priority[${vendor.id}]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent insurance-priority-select" required data-vendor-id="${vendor.id}">
                                    <option value="">Select priority...</option>
                                    ${priorityLabels.map((label, i) => `<option value="${i + 1}">${label}</option>`).join('')}
                                </select>
                            </div>
                        `;
                    });
                    
                    priorityContainer.innerHTML = priorityHtml;
                } else {
                    prioritySection.style.display = 'none';
                    // Clear priority assignments if reverting to single or no vendor
                    vendorCheckboxes.forEach(checkbox => {
                        const input = document.querySelector(`input[name="insurance_priority[${checkbox.value}]"]`);
                        if (input) input.remove();
                    });
                }
            }

            // Validate priority assignments before form submission
            function validatePriorityAssignments() {
                const selectedVendors = getSelectedVendorsList();
                
                // Skip validation if 1 or fewer vendors selected
                if (selectedVendors.length <= 1) {
                    return true;
                }

                // Check if all vendors have a priority assigned
                const assignedPriorities = new Set();
                let allAssigned = true;
                let priorityError = false;

                selectedVendors.forEach(vendor => {
                    const prioritySelect = document.querySelector(`select[name="insurance_priority[${vendor.id}]"]`);
                    if (prioritySelect) {
                        if (!prioritySelect.value) {
                            allAssigned = false;
                        } else {
                            if (assignedPriorities.has(prioritySelect.value)) {
                                priorityError = true; // Duplicate priority
                            }
                            assignedPriorities.add(prioritySelect.value);
                        }
                    }
                });

                const validationDiv = document.getElementById('priority_validation_error');
                
                if (!allAssigned) {
                    validationDiv.textContent = 'Please assign all selected insurance companies to a priority level.';
                    validationDiv.style.display = 'block';
                    return false;
                }

                if (priorityError) {
                    validationDiv.textContent = 'Each insurance company must have a unique priority level.';
                    validationDiv.style.display = 'block';
                    return false;
                }

                validationDiv.style.display = 'none';
                return true;
            }

            // Handle vendor checkbox changes
            vendorCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', async function() {
                    const vendorId = this.value;
                    console.log('DEBUG: Vendor checkbox changed', {
                        vendorId: vendorId,
                        isNowChecked: this.checked,
                        allCheckedVendors: Array.from(vendorCheckboxes).filter(cb => cb.checked).map(cb => cb.value),
                        totalChecked: Array.from(vendorCheckboxes).filter(cb => cb.checked).length
                    });
                    updatePriorityAssignment();
                    await renderVendorPolicyForms();
                });
            });

            // Handle service category changes - invalidate verifications if changed
            const servicesCategorySelect = document.getElementById('services_category');
            if (servicesCategorySelect) {
                servicesCategorySelect.addEventListener('change', function() {
                    const hasInvalidated = invalidateVerificationsIfCategoryChanged();
                    if (hasInvalidated) {
                        // Show warning toast/notification
                        const warningDiv = document.createElement('div');
                        warningDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg shadow-lg z-50';
                        warningDiv.innerHTML = `
                            <p class="font-medium">⚠ Service Category Changed</p>
                            <p class="text-sm mt-1">Policy verifications are invalid. Please re-verify them with the new service category.</p>
                            <p class="text-sm mt-2 font-semibold">The Register button has been disabled until verifications are re-done.</p>
                        `;
                        document.body.appendChild(warningDiv);
                        
                        // Auto-remove after 7 seconds
                        setTimeout(() => warningDiv.remove(), 7000);
                    }
                });
            }

            // Initialize on page load
            if (getSelectedVendors().length > 0) {
                renderVendorPolicyForms();
            }

            // Also handle when insurance section becomes visible/hidden
            const insuranceCheckbox = document.getElementById('payment_insurance');
            if (insuranceCheckbox) {
                insuranceCheckbox.addEventListener('change', function() {
                    if (!this.checked) {
                        vendorCheckboxes.forEach(checkbox => checkbox.checked = false);
                        renderVendorPolicyForms();
                    }
                });
            }

            // Debug: Log vendor selection before form submission
            const individualForm = document.getElementById('client-registration-form-individual');
            if (individualForm) {
                individualForm.addEventListener('submit', function(e) {
                    // Validate priority assignments if multiple vendors selected
                    if (!validatePriorityAssignments()) {
                        e.preventDefault();
                        // Scroll to priority section to show error
                        document.getElementById('insurance_priority_section')?.scrollIntoView({ behavior: 'smooth' });
                        return false;
                    }

                    const checkedVendors = [];
                    const vendorWithoutData = [];
                    
                    vendorCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            checkedVendors.push({
                                id: checkbox.value,
                                name: getVendorInfo(checkbox.value)?.name || 'Unknown'
                            });
                            
                            // Check if vendor has the required form fields
                            const policyNumberField = document.querySelector(`[name="insurance_vendor_data[${checkbox.value}][policy_number]"]`);
                            if (!policyNumberField) {
                                vendorWithoutData.push(checkbox.value);
                            }
                        }
                    });
                    
                    console.log('DEBUG: Form submission check', {
                        checkedVendors: checkedVendors,
                        checkedVendorIds: checkedVendors.map(v => v.id),
                        checkedCount: checkedVendors.length,
                        vendorsWithoutFormData: vendorWithoutData,
                        insuranceSelected: document.querySelector('input[name="payment_methods[]"][value="insurance"]')?.checked,
                        insurancePaymentMethodChecked: Array.from(document.querySelectorAll('input[name="payment_methods[]"]:checked')).map(el => el.value)
                    });
                });
            }
        })();


    </script>
</x-app-layout>