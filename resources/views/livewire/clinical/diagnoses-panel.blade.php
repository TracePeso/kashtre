<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Diagnoses</h4>

    <div class="flex gap-2 items-end mb-4">
        <div class="w-32">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">ICD-11 Code</label>
            <input type="text" wire:model="icd11Code" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Description</label>
            <input type="text" wire:model="description" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('description') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <button wire:click="addCondition" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Add
        </button>
    </div>

    @if ($conditions->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No diagnoses recorded.</p>
    @else
        <ul class="text-sm space-y-1">
            @foreach ($conditions as $condition)
                <li wire:key="cond-{{ $condition->id }}" class="text-gray-900 dark:text-gray-100">
                    @if ($condition->icd11_code)<span class="font-mono text-xs text-gray-500">{{ $condition->icd11_code }}</span> — @endif
                    {{ $condition->description }}
                    <span class="text-[10px] text-gray-400">({{ $condition->clinical_status }})</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
