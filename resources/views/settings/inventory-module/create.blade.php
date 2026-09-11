<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="{
    businessId: '{{ old('business_id') }}',
    usersByBusiness: @js($usersByBusiness->map(fn ($users) => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values())->toArray()),
    approver1: '{{ old('approver_1') }}',
    approver2: '{{ old('approver_2') }}',
    get businessUsers() {
        return this.businessId ? (this.usersByBusiness[this.businessId] || []) : [];
    }
}">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory-module-configs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Inventory Module Configurations</a>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Enable Inventory Module for a Business</h3>
                <p class="mt-1 text-sm text-gray-500">Once enabled, the business will see the Inventory section and can manage stock for their organisation.</p>
            </div>

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('inventory-module-configs.store') }}" method="POST" class="px-6 py-5 space-y-5">
                @csrf

                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700">Business <span class="text-red-500">*</span></label>
                    @if($businesses->isEmpty())
                        <p class="mt-1 text-sm text-gray-500 italic">All businesses already have the inventory module configured.</p>
                    @else
                        <select name="business_id" id="business_id" x-model="businessId" @change="approver1 = ''; approver2 = ''"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md @error('business_id') border-red-300 @enderror">
                            <option value="">— Select a business —</option>
                            @foreach($businesses as $biz)
                                <option value="{{ $biz->id }}" {{ old('business_id') == $biz->id ? 'selected' : '' }}>
                                    {{ $biz->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('business_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                              placeholder="Any notes about this configuration">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @include('settings.inventory-module._stock-settings-fields')

                <div class="border border-gray-200 rounded-lg p-4 space-y-4" x-show="businessId" x-cloak>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Goods receive note approvers</p>
                        <p class="text-xs text-gray-500 mt-0.5">Assign 1–2 staff who approve RFQs and goods receive notes. Technical supervisor is configured by the organisation after enablement.</p>
                    </div>

                    <div>
                        <label for="approver_1" class="block text-sm font-medium text-gray-700">Approver 1 <span class="text-red-500">*</span></label>
                        <select name="approver_1" id="approver_1" x-model="approver1" :required="!!businessId"
                                class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md @error('approver_1') border-red-300 @enderror">
                            <option value="">— Select approver —</option>
                            <template x-for="user in businessUsers" :key="user.id">
                                <option :value="user.id" x-text="user.name + ' (' + user.email + ')'"></option>
                            </template>
                        </select>
                        @error('approver_1')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="approver_2" class="block text-sm font-medium text-gray-700">Approver 2 <span class="text-gray-400">(optional)</span></label>
                        <select name="approver_2" id="approver_2" x-model="approver2"
                                class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md @error('approver_2') border-red-300 @enderror">
                            <option value="">— None —</option>
                            <template x-for="user in businessUsers" :key="'a2-' + user.id">
                                <option :value="user.id" x-text="user.name + ' (' + user.email + ')'" x-show="String(user.id) !== String(approver1)"></option>
                            </template>
                        </select>
                        @error('approver_2')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                               {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">Enable inventory module immediately</label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="{{ route('inventory-module-configs.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" @if($businesses->isEmpty()) disabled @endif
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Enable Inventory Module
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
