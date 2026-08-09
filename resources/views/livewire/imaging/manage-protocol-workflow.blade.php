<div>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                Save Workflow
            </button>
        </div>
    </form>
</div>
