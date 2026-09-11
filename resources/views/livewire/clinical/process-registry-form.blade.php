<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-4">
    <div>
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Create a process</h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Clinical's API has no way to edit or delete a process once created — get it right before
            submitting, or ask Clinical to remove it if you need to start over.
        </p>
    </div>

    @if ($statusMessage)
        <div class="rounded border border-green-300 bg-green-50 dark:bg-green-900/30 dark:border-green-700 p-3 text-sm text-green-800 dark:text-green-200">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded border border-red-300 bg-red-50 dark:bg-red-900/30 dark:border-red-700 p-3 text-sm text-red-800 dark:text-red-200">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                Process code <span class="text-red-600">*</span>
            </label>
            <input type="text" wire:model="processCode" placeholder="ADMISSION"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 font-mono uppercase">
            @if (isset($fieldErrors['processCode']))
                <div class="text-[10px] text-red-600 mt-0.5">{{ $fieldErrors['processCode'] }}</div>
            @endif
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                Process name <span class="text-red-600">*</span>
            </label>
            <input type="text" wire:model="processName" placeholder="Admission Workflow"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @if (isset($fieldErrors['processName']))
                <div class="text-[10px] text-red-600 mt-0.5">{{ $fieldErrors['processName'] }}</div>
            @endif
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Description</label>
            <input type="text" wire:model="description"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                Active
            </label>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <h5 class="text-xs font-medium text-gray-600 dark:text-gray-300 uppercase">Steps, in order</h5>
            <button type="button" wire:click="addStep" class="text-xs text-blue-700 dark:text-blue-300 hover:underline">
                + Add step
            </button>
        </div>

        <div class="space-y-3">
            @foreach ($steps as $i => $step)
                <div wire:key="step-{{ $i }}"
                    class="border border-gray-200 dark:border-gray-600 rounded p-3 grid grid-cols-1 sm:grid-cols-12 gap-2 items-start">
                    <div class="sm:col-span-1 text-xs text-gray-400 pt-2">#{{ $step['step_order'] }}</div>

                    <div class="sm:col-span-3">
                        <input type="text" wire:model="steps.{{ $i }}.step_code" placeholder="STEP_CODE"
                            class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 font-mono uppercase">
                        @if (isset($fieldErrors["steps.{$i}.step_code"]))
                            <div class="text-[10px] text-red-600">{{ $fieldErrors["steps.{$i}.step_code"] }}</div>
                        @endif
                    </div>

                    <div class="sm:col-span-4">
                        <input type="text" wire:model="steps.{{ $i }}.step_name" placeholder="Step name"
                            class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        @if (isset($fieldErrors["steps.{$i}.step_name"]))
                            <div class="text-[10px] text-red-600">{{ $fieldErrors["steps.{$i}.step_name"] }}</div>
                        @endif
                    </div>

                    <div class="sm:col-span-3">
                        <input type="text" wire:model="steps.{{ $i }}.required_role" list="known-clinical-roles" placeholder="WARD_NURSE"
                            class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 font-mono uppercase">
                        @if (isset($fieldErrors["steps.{$i}.required_role"]))
                            <div class="text-[10px] text-red-600">{{ $fieldErrors["steps.{$i}.required_role"] }}</div>
                        @endif
                    </div>

                    <div class="sm:col-span-1 pt-2">
                        <label class="inline-flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400" title="Mandatory step">
                            <input type="checkbox" wire:model="steps.{{ $i }}.is_mandatory" class="rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                            Req'd
                        </label>
                    </div>

                    <div class="sm:col-span-12 sm:text-right">
                        <button type="button" wire:click="removeStep({{ $i }})" class="text-[10px] text-gray-400 dark:text-gray-500 hover:text-red-600">
                            Remove step
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <datalist id="known-clinical-roles">
            @foreach (\App\Livewire\Clinical\ProcessRegistryForm::KNOWN_ROLES as $role)
                <option value="{{ $role }}"></option>
            @endforeach
        </datalist>
        <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
            Known roles: {{ implode(', ', \App\Livewire\Clinical\ProcessRegistryForm::KNOWN_ROLES) }} — a role
            outside this list is one Main can never satisfy, so the step can never be completed.
        </p>
    </div>

    <div>
        <button wire:click="create" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Create process
        </button>
    </div>
</div>
