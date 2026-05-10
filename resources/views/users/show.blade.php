<x-app-layout>
<div class="py-12" x-data="{ bioOpen: true, businessOpen: true, permissionsOpen: true, contractorOpen: true }">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">User Details</h2>

            <!-- Bio Section -->
            <div x-data="{ open: true }" class="mb-4 border rounded">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                    <span>Bio</span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="open" class="p-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label>Surname</label>
                            <div class="form-input w-full bg-gray-100">{{ explode(' ', $user->name)[0] ?? '' }}</div>
                        </div>
                        <div>
                            <label>First Name</label>
                            <div class="form-input w-full bg-gray-100">{{ explode(' ', $user->name)[1] ?? '' }}</div>
                        </div>
                        <div>
                            <label>Middle Name</label>
                            <div class="form-input w-full bg-gray-100">{{ explode(' ', $user->name)[2] ?? '' }}</div>
                        </div>
                    </div>
                    <div>
                        <label>Email</label>
                        <div class="form-input w-full bg-gray-100">{{ $user->email }}</div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Phone</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->phone }}</div>
                        </div>
                        <div>
                            <label>National ID (NIN)</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->nin ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Gender</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->gender ? ucfirst($user->gender) : '—' }}</div>
                        </div>
                        <div>
                            <label>Marital status</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->marital_status ? ucfirst(str_replace('_', ' ', $user->marital_status)) : '—' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Date of birth</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->birth_date ? $user->birth_date->format('M j, Y') : '—' }}</div>
                        </div>
                        <div>
                            <label>Age (from date of birth)</label>
                            <div class="form-input w-full bg-gray-100">{{ $user->hrAge() !== null ? $user->hrAge().' years' : '—' }}</div>
                        </div>
                    </div>
                    <div>
                        <label>Profile Photo</label>
                        @if($user->profile_photo_path)
                            <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="Profile Photo" class="h-24 w-24 rounded-full object-cover">
                        @else
                            <span class="form-input w-full bg-gray-100">No photo</span>
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
                        <label>Business</label>
                        <div class="form-input w-full bg-gray-100">{{ $user->business->name ?? '' }}</div>
                    </div>
                    <div>
                        <label>Branch</label>
                        <div class="form-input w-full bg-gray-100">{{ $user->branch->name ?? '' }}</div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Qualification</label>
                            <div class="form-input w-full bg-gray-100">{{ optional($user->qualification)->name }}</div>
                        </div>
                        <div>
                            <label>Title</label>
                            <div class="form-input w-full bg-gray-100">{{ optional($user->title)->name }}</div>
                        </div>
                        <div>
                            <label>Department</label>
                            <div class="form-input w-full bg-gray-100">{{ optional($user->department)->name }}</div>
                        </div>
                    </div>
                    <div>
                        <label>Status</label>
                        <div class="form-input w-full bg-gray-100">{{ ucfirst($user->status) }}</div>
                    </div>
                </div>
            </div>

            <!-- Permissions Section -->
            <div x-data="{ open: true }" class="mb-4 border rounded">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                    <span>Permissions</span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="open" class="p-4 space-y-4">
                    <div>
                        <label>Service Points</label>
                        <ul class="list-disc ml-6">
                            @if(!empty($user->service_points))
                                @foreach((array) $user->service_points as $spId)
                                    <li>{{ optional(\App\Models\ServicePoint::find($spId))->name ?? 'N/A' }}</li>
                                @endforeach
                            @else
                                <li>None assigned</li>
                            @endif
                        </ul>
                    </div>
                    <div>
                        <label>Allowed Branches</label>
                        <ul class="list-disc ml-6">
                            @foreach((array) $user->allowed_branches as $branchId)
                                <li>{{ optional(\App\Models\Branch::find($branchId))->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <label>Permissions</label>
                        <ul class="list-disc ml-6">
                            @foreach((array) $user->permissions as $perm)
                                <li>{{ $perm }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Contractor Profile Section -->
            @if($contractorProfile)
            <div x-data="{ open: true }" class="mb-4 border rounded">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-700 text-left text-lg font-semibold focus:outline-none">
                    <span>Contractor Profile</span>
                    <svg :class="{'rotate-180': open}" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="open" class="p-4 space-y-4">
                    <div>
                        <label>Bank Name</label>
                        <div class="form-input w-full bg-gray-100">{{ $contractorProfile->bank_name }}</div>
                    </div>
                    <div>
                        <label>Account Name</label>
                        <div class="form-input w-full bg-gray-100">{{ $contractorProfile->account_name }}</div>
                    </div>
                    <div>
                        <label>Account Number</label>
                        <div class="form-input w-full bg-gray-100">{{ $contractorProfile->account_number }}</div>
                    </div>
                </div>
            </div>
            @endif

            <div class="flex justify-end space-x-4 pt-4">
                <a href="{{ route('users.index') }}" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Back</a>
                <a href="{{ route('users.edit', $user->id) }}" class="px-4 py-2 bg-[#011478] text-white rounded-md hover:bg-[#011478]/90">Edit User</a>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
