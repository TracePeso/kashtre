<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\InventoryAiAdvisor;
use App\Support\InventoryBusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiAdvicePanel extends Component
{
    public string $useCase = 'stockout';

    public bool $allowAsk = false;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public string $question = '';

    /** @var array<string, mixed>|null */
    public ?array $advice = null;

    public function check(): void
    {
        $this->run($this->useCase);
    }

    public function ask(): void
    {
        $this->run('ask');
    }

    public function render(): View
    {
        $advisor = app(InventoryAiAdvisor::class);
        $meta = InventoryAiAdvisor::USE_CASES[$this->useCase] ?? InventoryAiAdvisor::USE_CASES['stockout'];

        return view('livewire.inventory.ai-advice-panel', [
            'configured' => $advisor->isConfigured(),
            'gatewayUrl' => $advisor->gatewayUrl(),
            'actionLabel' => $meta['label'],
        ]);
    }

    private function run(string $useCase): void
    {
        $this->advice = app(InventoryAiAdvisor::class)->advise(
            $useCase,
            (int) InventoryBusinessContext::effectiveBusinessId(),
            $this->storeId ?: null,
            $this->itemId ?: null,
            trim($this->question) !== '' ? trim($this->question) : null,
        );
    }
}
