<div>
    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <nav class="-mb-px flex space-x-8" aria-label="Staff tabs">
            <button
                type="button"
                wire:click="setActiveTab('staff')"
                class="py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'staff' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Staff
                <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $activeTab === 'staff' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $this->staffCount() }}
                </span>
            </button>
            <button
                type="button"
                wire:click="setActiveTab('contractors')"
                class="py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'contractors' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Contractors
                <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $activeTab === 'contractors' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $this->contractorCount() }}
                </span>
            </button>
        </nav>
    </div>

    {{ $this->table }}
</div>
