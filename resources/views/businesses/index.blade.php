<x-app-layout>
    <div class="py-12" x-data="{ showModal: false, showBulkUploadModal: false }" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Manage Businesses</h2>

                    <div class="flex space-x-2">
                        @if (Auth::check() && Auth::user()->business_id == 1)
                            <a href="{{ route('businesses.bulk.template') }}" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 transition duration-150">
                                📥 Download Template
                            </a>
                            
                            <button @click="showBulkUploadModal = true"
                                class="inline-flex items-center px-4 py-2 bg-orange-600 text-white text-sm font-semibold rounded-md hover:bg-orange-700 transition duration-150">
                                📤 Bulk Upload
                            </button>
                            
                            <button @click="showModal = true"
                                class="inline-flex items-center px-4 py-2 bg-[#011478] text-white text-sm font-semibold rounded-md hover:bg-[#011478]/90 transition duration-150">
                                ➕ Create Business
                            </button>
                        @endif
                    </div>
                </div>

                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show"
                        class="relative bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 transition"
                        role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                        <button @click="show = false"
                            class="absolute top-1 right-2 text-xl font-semibold text-green-700">
                            &times;
                        </button>
                    </div>
                @endif


                @livewire('list-business')
            </div>
        </div>

        <!-- Modal -->
        <div x-show="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Create New Business</h3>
                    <button @click="showModal = false"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-100">
                        ✕
                    </button>
                </div>

                @php
                    $countries = \App\Models\Country::with('currency')->orderedDefaultUsFirst()->get();
                @endphp

                <form action="{{ route('businesses.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" required placeholder="Enter business name"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @error('name')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required placeholder="Enter business email"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @error('email')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone <span class="text-red-500">*</span></label>
                            <input type="tel" name="phone" id="phone" required placeholder="Enter business phone"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @error('phone')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address <span class="text-red-500">*</span></label>
                            <input type="text" name="address" id="address" required placeholder="Enter business address"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @error('address')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country <span class="text-red-500">*</span></label>
                            <select
                                name="country_id"
                                id="country_id"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">Select country</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ $country->iso_code === 'US' ? 'selected' : '' }}>
                                        {{ $country->name }} ({{ $country->currency_code ?? ($country->currency?->code ?? 'USD') }})
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Logo <span class="text-red-500">*</span></label>
                            <input type="file" name="logo" id="logo" accept="image/*" required placeholder="Upload business logo"
                                class="mt-1 block w-full text-gray-700 dark:text-gray-300">
                            @error('logo')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 border-t border-gray-200 dark:border-gray-600 pt-4">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-3">Financial year</p>
                        @include('partials.financial-year-fields', ['idPrefix' => 'create-'])
                    </div>


                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="showModal = false"
                            class="mr-4 px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-[#011478] text-white rounded-md hover:bg-[#011478]/90">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Upload Modal -->
        <div x-show="showBulkUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Bulk Upload Businesses</h3>
                    <button @click="showBulkUploadModal = false"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-100">
                        ✕
                    </button>
                </div>

                <form action="{{ route('businesses.bulk.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{ uploading: false }" @submit="uploading = true">
                    @csrf
                    
                    <div>
                        <label for="template" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Template <span class="text-red-500">*</span></label>
                        <input type="file" name="template" id="template" accept=".xlsx,.xls" required
                            class="mt-1 block w-full text-gray-700 dark:text-gray-300">
                        @error('template')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showBulkUploadModal = false" :disabled="uploading"
                            class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 disabled:opacity-50 disabled:cursor-not-allowed">
                            Cancel
                        </button>
                        <button type="submit" :disabled="uploading"
                            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!uploading">Upload Template</span>
                            <span x-show="uploading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
