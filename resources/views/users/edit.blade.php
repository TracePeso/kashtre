@php
    use App\Models\Business;
    use App\Models\Qualification;
    use App\Models\Department;
    use App\Models\Title;
    use App\Models\StaffCategory;
    use App\Models\ServicePoint;

    $businesses = Business::with('branches')->where('id', '!=', 1)->get()->keyBy('id');

    $businessBranchData = $businesses->map(function ($b) {
        return [
            'id' => $b->id,
            'branches' => $b->branches->map(function ($br) {
                return [
                    'id' => $br->id,
                    'name' => $br->name,
                ];
            })->values()->all(),
        ];
    })->values()->all();

    $qualifications = Qualification::all();
    $departments = Department::all();
    $titles = Title::all();
    $staffCategories = StaffCategory::all();
    // Group by business_id for Alpine.js
    $qualificationsByBusiness = $qualifications->groupBy('business_id')->map(function($items) {
        return $items->map(function($item) {
            return ['id' => $item->id, 'name' => $item->name];
        })->values();
    });
    $departmentsByBusiness = $departments->groupBy('business_id')->map(function($items) {
        return $items->map(function($item) {
            return ['id' => $item->id, 'name' => $item->name];
        })->values();
    });
    $titlesByBusiness = $titles->groupBy('business_id')->map(function($items) {
        return $items->map(function($item) {
            return ['id' => $item->id, 'name' => $item->name];
        })->values();
    });
    $staffCategoriesByBusiness = $staffCategories->groupBy('business_id')->map(function($items) {
        return $items->map(function($item) {
            return ['id' => $item->id, 'name' => $item->name];
        })->values();
    });
    $servicePoints = ServicePoint::all();
    // Group service points by business_id for Alpine.js
    $servicePointsByBusiness = $servicePoints->groupBy('business_id')->map(function($sps) {
        return $sps->map(function($sp) {
            return ['id' => $sp->id, 'name' => $sp->name];
        })->values();
    });
@endphp

<x-app-layout>
<div class="py-12" x-data="userForm()" x-init="init()">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Edit User</h2>
            <form action="{{ route('users.update', $user->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6" @submit="handleFormSubmit">
                @csrf
                @method('PUT')

                <!-- Bio Section -->
                <div x-data="{ open: true }" class="mb-4 border rounded">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                        <span>Bio</span>
                        <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" class="p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="surname">Surname <span class="text-gray-500 text-sm">(optional)</span></label>
                                <input type="text" name="surname" id="surname" placeholder="Enter surname" class="form-input w-full" value="{{ old('surname', $surname) }}">
                            </div>
                            <div>
                                <label for="first_name">First name <span class="text-gray-500 text-sm">(optional)</span></label>
                                <input type="text" name="first_name" id="first_name" placeholder="Enter first name" class="form-input w-full" value="{{ old('first_name', $first_name) }}">
                            </div>
                            <div>
                                <label for="middle_name">Middle name <span class="text-gray-500 text-sm">(optional)</span></label>
                                <input type="text" name="middle_name" id="middle_name" placeholder="Enter middle name" class="form-input w-full" value="{{ old('middle_name', $middle_name) }}">
                            </div>
                        </div>
                        <div>
                            <label for="email">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required placeholder="Enter email address" class="form-input w-full" value="{{ old('email', $user->email) }}">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="phone">Phone <span class="text-red-500">*</span></label>
                                <input type="text" name="phone" id="phone" required placeholder="Enter phone number" class="form-input w-full" value="{{ old('phone', $user->phone) }}">
                            </div>
                            <div>
                                <label for="nin">NIN <span class="text-red-500">*</span></label>
                                <input type="text" name="nin" id="nin" required placeholder="Enter NIN" class="form-input w-full" value="{{ old('nin', $user->nin) }}">
                            </div>
                        </div>
                        <div>
                            <label for="gender">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" required class="form-select w-full">
                                <option value="" disabled>Select Gender</option>
                                <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('gender', $user->gender) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="profile_photo_path">Profile Photo <span class="text-red-500">*</span></label>
                            <input type="file" name="profile_photo_path" id="profile_photo_path" accept="image/*" class="form-input w-full">
                            @if($user->profile_photo_path)
                                <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="Profile Photo" class="h-24 w-24 rounded-full object-cover mt-2">
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Business Info Section -->
                <div x-data="{ open: true }" class="mb-4 border rounded">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                        <span>Business Info</span>
                        <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" class="p-4 space-y-4">
                        <div>
                            <label for="business_id">Business <span class="text-red-500" x-show="userBusinessId === 1">*</span></label>
                            <select name="business_id" id="business_id" :required="userBusinessId === 1" class="form-select w-full" x-model="selectedBusinessId" @change="updateBranches" :disabled="userBusinessId !== 1">
                                <option value="" disabled>Select Business</option>
                                @foreach($businesses as $id => $business)
                                    @if(Auth::user()->business_id == 1 || Auth::user()->business_id == $id)
                                        <option value="{{ $id }}" {{ old('business_id', $user->business_id) == $id ? 'selected' : '' }}>{{ $business->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="branch_id">Branch <span class="text-gray-500 text-sm">(optional)</span></label>
                            <select name="branch_id" id="branch_id" class="form-select w-full" :disabled="!branches.length">
                                <option value="">Default branch</option>
                                <template x-for="branch in branches" :key="branch.id">
                                    <option :value="branch.id" x-text="branch.name" :selected="branch.id == {{ old('branch_id', $user->branch_id) }}"></option>
                                </template>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="qualification_id">Qualification <span class="text-gray-500 text-sm">(optional)</span></label>
                                <select name="qualification_id" id="qualification_id" class="form-select w-full">
                                    <option value="">Not specified</option>
                                    <template x-for="q in filteredQualifications" :key="q.id">
                                        <option :value="q.id" x-text="q.name" :selected="q.id == {{ old('qualification_id', $user->qualification_id) }}"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="staff_category_id">Staff category <span class="text-gray-500 text-sm">(optional)</span></label>
                                <select name="staff_category_id" id="staff_category_id" class="form-select w-full">
                                    <option value="">Not specified</option>
                                    <template x-for="c in filteredStaffCategories" :key="c.id">
                                        <option :value="c.id" x-text="c.name" :selected="c.id == {{ old('staff_category_id', $user->staff_category_id) }}"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="title_id">Job title <span class="text-gray-500 text-sm">(optional)</span></label>
                                <select name="title_id" id="title_id" class="form-select w-full">
                                    <option value="">Not specified</option>
                                    <template x-for="t in filteredTitles" :key="t.id">
                                        <option :value="t.id" x-text="t.name" :selected="t.id == {{ old('title_id', $user->title_id) }}"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="department_id">Department <span class="text-gray-500 text-sm">(optional)</span></label>
                                <select name="department_id" id="department_id" class="form-select w-full">
                                    <option value="">Not specified</option>
                                    <template x-for="d in filteredDepartments" :key="d.id">
                                        <option :value="d.id" x-text="d.name" :selected="d.id == {{ old('department_id', $user->department_id) }}"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-select w-full">
                                <option value="active" {{ old('status', $user->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $user->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ old('status', $user->status) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label for="hr_role">HR module role <span class="text-gray-500 text-sm">(optional)</span></label>
                            @php $hrRole = old('hr_role', $user->hr_role); @endphp
                            <select name="hr_role" id="hr_role" class="form-select w-full">
                                <option value="" {{ !$hrRole ? 'selected' : '' }}>Default (Staff)</option>
                                <option value="staff" {{ $hrRole == 'staff' ? 'selected' : '' }}>Staff</option>
                                <option value="supervisor" {{ $hrRole == 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                                <option value="roster_manager" {{ $hrRole == 'roster_manager' ? 'selected' : '' }}>Roster Manager</option>
                                <option value="hr_manager" {{ $hrRole == 'hr_manager' ? 'selected' : '' }}>HR Manager</option>
                                <option value="admin" {{ $hrRole == 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="super_admin" {{ $hrRole == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Access & Permissions Section -->
                <div x-data="{ open: true }" class="mb-4 border rounded">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                        <span>Access & Permissions</span>
                        <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" class="p-4 space-y-6">
                        <!-- Service Points -->
                        <div>
                            <label for="service_points" class="block text-sm font-medium text-gray-700">
                                Service points <span class="text-gray-500 text-sm">(optional)</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                                <template x-for="sp in filteredServicePoints" :key="sp.id">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="service_points[]" :value="sp.id" class="form-checkbox" :checked="userServicePoints.includes(parseInt(sp.id)) || userServicePoints.includes(sp.id.toString())">
                                        <span class="ml-2" x-text="sp.name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <!-- Allowed Branches -->
                        <div>
                            <label for="allowed_branches" class="block text-sm font-medium text-gray-700">
                                Allowed branches <span class="text-gray-500 text-sm">(optional)</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                                <template x-for="branch in filteredBranches" :key="branch.id">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="allowed_branches[]" :value="branch.id" class="form-checkbox" :checked="{{ json_encode($user->allowed_branches ?? []) }}.includes(branch.id)">
                                        <span class="ml-2" x-text="branch.name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Permissions <span class="text-red-500">*</span>
                            </label>

                            @foreach($app_permissions as $group => $categories)
                            <div class="mb-6 border p-4 rounded mt-2">
                                {{-- Group Checkbox --}}
                                <label class="inline-flex items-center mb-3">
                                    <input type="checkbox" name="permissions_menu[]" value="{{ $group }}" class="module-checkbox form-checkbox h-5 w-5 text-indigo-600" {{ in_array($group, old('permissions_menu', (array) ($user->permissions ?? []))) ? 'checked' : '' }}>
                                    <span class="ml-2 font-bold text-lg text-gray-800">{{ $group }}</span>
                                </label>

                                {{-- Categories under Group --}}
                                @foreach ($categories as $category => $perms)
                                <div class="pl-8 mb-4 border-l-2 border-gray-300">
                                    {{-- Category Checkbox --}}
                                    <label class="inline-flex items-center mb-2">
                                        <input type="checkbox" name="permissions_menu[]" value="{{ $category }}" class="submodule-checkbox form-checkbox h-4 w-4 text-indigo-600" {{ in_array($category, old('permissions_menu', (array) ($user->permissions ?? []))) ? 'checked' : '' }}>
                                        <span class="ml-2 font-semibold text-gray-700">{{ $category }}</span>
                                    </label>

                                    {{-- Permission checkboxes --}}
                                    <div class="ml-6 space-y-1">
                                        @foreach ($perms as $permission)
                                        <label class="inline-flex items-center space-x-2">
                                            <input type="checkbox" name="permissions_menu[]" value="{{ $permission }}" class="action-checkbox form-checkbox h-3 w-3 text-indigo-600" {{ in_array($permission, old('permissions_menu', (array) ($user->permissions ?? []))) ? 'checked' : '' }}>
                                            <span>{{ $permission }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endforeach

                            @error('permissions_menu')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Contractor Profile Section -->
                <div x-data="{ open: true }" x-show="isContractorSelected" class="mb-4 border rounded">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                        <span>Contractor Profile</span>
                        <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" class="p-4 space-y-4">
                        <div>
                            <label for="bank_name">Bank Name <span class="text-red-500" x-show="isContractorSelected">*</span></label>
                            <input type="text" name="bank_name" id="bank_name" :required="isContractorSelected" placeholder="Enter bank name" class="form-input w-full" value="{{ old('bank_name', optional($contractorProfile)->bank_name) }}">
                        </div>
                        <div>
                            <label for="account_name">Account Name <span class="text-red-500" x-show="isContractorSelected">*</span></label>
                            <input type="text" name="account_name" id="account_name" :required="isContractorSelected" placeholder="Enter account name" class="form-input w-full" value="{{ old('account_name', optional($contractorProfile)->account_name) }}">
                        </div>
                        <div>
                            <label for="account_number">Account Number <span class="text-red-500" x-show="isContractorSelected">*</span></label>
                            <input type="text" name="account_number" id="account_number" :required="isContractorSelected" placeholder="Enter account number" class="form-input w-full" value="{{ old('account_number', optional($contractorProfile)->account_number) }}">
                        </div>
                    </div>
                </div>

                <!-- Validation Errors -->
                @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="flex justify-end space-x-4 pt-4">
                    <a href="{{ route('users.index') }}" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-[#011478] text-white rounded-md hover:bg-[#011478]/90">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery logic reused -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Module checkbox: Check/uncheck all submodules and actions
            $('.module-checkbox').on('change', function() {
                $(this).closest('div').find('input[type="checkbox"]').prop('checked', this.checked);
            });
    
            // Submodule checkbox: Check/uncheck all actions and update module checkbox
            $('.submodule-checkbox').on('change', function() {
                $(this).closest('div').find('.action-checkbox').prop('checked', this.checked);
                updateModuleCheckbox($(this));
            });
    
            // Action checkbox: Update submodule and module checkboxes
            $('.action-checkbox').on('change', function() {
                var submoduleCheckbox = $(this).closest('div').closest('div').find('.submodule-checkbox');
                var allActions = submoduleCheckbox.closest('div').find('.action-checkbox');
                var allChecked = allActions.length === allActions.filter(':checked').length;
                var anyChecked = allActions.filter(':checked').length > 0;
    
                submoduleCheckbox.prop('checked', allChecked);
                updateModuleCheckbox(submoduleCheckbox);
            });
        });
    </script>


        <script>
            function userForm() {
                return {
                    selectedBusinessId: '{{ $user->business_id }}',
                    userBusinessId: {{ Auth::user()->business_id }},
                    branches: [],
                    businessData: @json($businessBranchData),
                    servicePointsByBusiness: @json($servicePointsByBusiness),
                    allBranches: @json($businesses->map->branches),
                    qualificationsByBusiness: @json($qualificationsByBusiness),
                    departmentsByBusiness: @json($departmentsByBusiness),
                    titlesByBusiness: @json($titlesByBusiness),
                    staffCategoriesByBusiness: @json($staffCategoriesByBusiness),
                    userServicePoints: @json($user->service_points ?? []),
                    filteredServicePoints: [],
                    filteredBranches: [],
                    filteredQualifications: [],
                    filteredDepartments: [],
                    filteredTitles: [],
                    filteredStaffCategories: [],
                    isContractorSelected: false,
                    init() {
                        // If user is not from business 1, force their business ID
                        if (this.userBusinessId !== 1) {
                            this.selectedBusinessId = this.userBusinessId.toString();
                        }
                        this.updateBranches();
                        this.updateServicePointsAndBranches();
                        this.$nextTick(() => {
                            this.watchContractorPermission();
                        });
                    },
                    watchContractorPermission() {
                        const self = this;
                        function checkContractor() {
                            const checked = Array.from(document.querySelectorAll('input[name=\'permissions_menu[]\']:checked')).map(cb => cb.value);
                            self.isContractorSelected = checked.includes('Contractor');
                        }
                        checkContractor();
                        document.querySelectorAll('input[name=\'permissions_menu[]\']').forEach(cb => {
                            cb.addEventListener('change', checkContractor);
                        });
                    },
                    updateBranches() {
                        const biz = this.businessData.find(b => b.id == this.selectedBusinessId);
                        this.branches = biz ? biz.branches : [];
                        this.updateServicePointsAndBranches();
                    },
                    updateServicePointsAndBranches() {
                        this.filteredServicePoints = this.selectedBusinessId && this.servicePointsByBusiness[this.selectedBusinessId]
                            ? this.servicePointsByBusiness[this.selectedBusinessId]
                            : [];
                        
                        // Debug logging
                        console.log('Selected Business ID:', this.selectedBusinessId);
                        console.log('Service Points by Business:', this.servicePointsByBusiness);
                        console.log('Filtered Service Points:', this.filteredServicePoints);
                        console.log('User Service Points:', this.userServicePoints);
                        console.log('User Service Points type:', typeof this.userServicePoints[0]);
                        console.log('Filtered Service Points type:', typeof this.filteredServicePoints[0]?.id);
                        
                        const biz = this.businessData.find(b => b.id == this.selectedBusinessId);
                        this.filteredBranches = biz ? biz.branches : [];
                        this.filteredQualifications = this.selectedBusinessId && this.qualificationsByBusiness[this.selectedBusinessId]
                            ? this.qualificationsByBusiness[this.selectedBusinessId]
                            : [];
                        this.filteredDepartments = this.selectedBusinessId && this.departmentsByBusiness[this.selectedBusinessId]
                            ? this.departmentsByBusiness[this.selectedBusinessId]
                            : [];
                        this.filteredTitles = this.selectedBusinessId && this.titlesByBusiness[this.selectedBusinessId]
                            ? this.titlesByBusiness[this.selectedBusinessId]
                            : [];
                        this.filteredStaffCategories = this.selectedBusinessId && this.staffCategoriesByBusiness[this.selectedBusinessId]
                            ? this.staffCategoriesByBusiness[this.selectedBusinessId]
                            : [];
                    },
                    handleFormSubmit(event) {
                        // If user is not from super business, ensure business_id is set
                        if (this.userBusinessId !== 1) {
                            const businessSelect = document.getElementById('business_id');
                            if (businessSelect) {
                                businessSelect.value = this.userBusinessId;
                            }
                        }
                    }
                }
            }
        </script>
</x-app-layout>

