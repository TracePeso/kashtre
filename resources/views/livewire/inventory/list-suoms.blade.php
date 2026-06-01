<div>
    <button
        id="suom-create-trigger"
        type="button"
        wire:click="openCreateModal"
        class="hidden"
        aria-hidden="true"
        tabindex="-1"
    ></button>

    {{ $this->table }}
</div>
