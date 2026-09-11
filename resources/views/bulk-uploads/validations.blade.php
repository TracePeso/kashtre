<x-app-layout>
    <div class="py-12 px-12" x-data="bulkUpload()" x-init="init()" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 bg-white p-6 rounded shadow">
            <h2 class="text-xl font-semibold">Bulk Upload Template Generator</h2>

            <!-- Select Business -->
            <div class="mb-4">
                <label class="block text-gray-700">Select Business</label>
                <select x-model="selectedBusiness" @change="filterBranches" class="mt-1 block w-full rounded border-gray-300">
                    <option value="">-- Select Business --</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}">{{ $business->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Select Branch -->
            <div class="mb-4">
                <label class="block text-gray-700">Select Branch</label>
                <select x-model="selectedBranch" class="mt-1 block w-full rounded border-gray-300">
                    <option value="">-- Select Branch --</option>
                    <template x-for="branch in filteredBranches" :key="branch.id">
                        <option :value="branch.id" x-text="branch.name"></option>
                    </template>
                </select>
            </div>

            <!-- Select Items to Include -->
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Select Items to Include in Template</label>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="(item, index) in items" :key="index">
                        <label class="inline-flex items-center">
                            <input type="checkbox" x-model="selectedItems" :value="item.value" class="form-checkbox">
                            <span class="ml-2" x-text="item.label"></span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Download Template Button -->
            <form :action="'{{ route('bulk.upload.template') }}'" method="GET" @submit.prevent="submitDownloadForm">
                <input type="hidden" name="business_id" :value="selectedBusiness">
                <input type="hidden" name="branch_id" :value="selectedBranch">
                <template x-for="item in selectedItems">
                    <input type="hidden" name="items[]" :value="item">
                </template>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Generate & Download Template
                </button>
            </form>

             <!--- Upload File -->
             <form action="{{ route('bulk.upload.import-validations') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-4" x-data="{ uploading: false }" @submit="uploading = true">
                @csrf
                <input type="hidden" name="business_id" :value="selectedBusiness">
                <input type="hidden" name="branch_id" :value="selectedBranch">
            
                <div>
                    <label class="block text-gray-700">Upload Filled Template</label>
                    <input type="file" name="template" accept=".xlsx" required class="mt-1 block w-full rounded border-gray-300">
                </div>
            
                <button type="submit" :disabled="uploading || !selectedBusiness || !selectedBranch" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!uploading">Upload Template</span>
                    <span x-show="uploading" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Uploading...
                    </span>
                </button>
                
                <!-- Validation Message -->
                <div x-show="!selectedBusiness || !selectedBranch" class="text-red-600 text-sm">
                    <span x-show="!selectedBusiness">Please select a business. </span>
                    <span x-show="!selectedBranch && selectedBusiness">Please select a branch.</span>
                </div>
            </form>
            
             <!--- Upload File -->

            
        </div>
    </div>

    <script>
        function bulkUpload() {
            return {
                selectedBusiness: '',
                selectedBranch: '',
                selectedItems: [],
                branches: @json($branches),
                filteredBranches: [],
                items: [
                    { label: 'Item Unit', value: 'item_unit' },
                    { label: 'Sub Group', value: 'sub_group' },
                    { label: 'Group', value: 'group' },
                    { label: 'Department', value: 'department' },
                    { label: 'Service Point', value: 'service_point' },
                    { label: 'Room', value: 'room' },
                    { label: 'Title', value: 'title' },
                    { label: 'Qualifications', value: 'qualifications' },
                    { label: 'Sections', value: 'sections' },
                    { label: 'Client Category', value: 'patient_category' },
                    { label: 'Insurance Company', value: 'insurance_company' },
                    { label: 'Supplier', value: 'supplier' },
                    { label: 'Store', value: 'store' },
                ],
                init() {
                    this.filteredBranches = [];
                },
                filterBranches() {
                    this.filteredBranches = this.branches.filter(branch => branch.business_id == this.selectedBusiness);
                    this.selectedBranch = '';
                },
                submitDownloadForm(e) {
                    if (!this.selectedBusiness || !this.selectedBranch || this.selectedItems.length === 0) {
                        alert("Please select Business, Branch and at least one item.");
                        return;
                    }
                    e.target.submit();
                }
            }
        }
    </script>
</x-app-layout>
