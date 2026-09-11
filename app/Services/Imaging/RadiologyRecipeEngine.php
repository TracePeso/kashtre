<?php

namespace App\Services\Imaging;

use App\Models\ImagingConsumption;
use App\Models\ImagingConsumptionException;
use App\Models\ImagingServicePointConfig;
use App\Models\ImagingStudy;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\ProtocolWorkflowStep;
use App\Models\User;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Illuminate\Support\Facades\Log;

/**
 * Pillar 12.1: Material Consumable Tracking. Mirrors
 * InventorySaleConsumptionService's shape — same module-active gate, same
 * store-resolution fallback, same InventoryStockAnalyticsService call —
 * but reads its recipe from ImagingProtocol::consumables_recipe instead of
 * a sale line.
 */
class RadiologyRecipeEngine
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics
    ) {}

    public function depleteForStudy(ImagingStudy $study, ?int $userId = null, ?ProtocolWorkflowStep $step = null): void
    {
        $protocol = $study->protocol();
        $recipe = $protocol?->consumables_recipe ?? [];

        if (empty($recipe)) {
            return;
        }

        $businessId = (int) $study->business_id;
        $inventoryActive = InventoryModuleConfig::query()->forBusiness($businessId)->active()->exists();

        foreach ($recipe as $line) {
            $sku = $line['sku'] ?? null;
            $quantity = (float) ($line['qty'] ?? 0);

            if (! $sku || $quantity <= 0) {
                continue;
            }

            // Imaging keeps its own local record regardless of whether the
            // real Inventory module is active/configured for this business.
            $item = Item::where('business_id', $businessId)->where('code', $sku)->first();

            ImagingConsumption::create([
                'imaging_study_id' => $study->id,
                'item_id' => $item?->id,
                'inventory_sku' => $sku,
                'quantity_used' => $quantity,
                'consumption_type' => ImagingConsumption::TYPE_PATIENT_PROCEDURE,
            ]);

            if (! $inventoryActive || ! $item || $item->type !== 'good') {
                continue;
            }

            $this->depleteRealStock($study, $item, $quantity, $userId, $step);
        }
    }

    private function depleteRealStock(ImagingStudy $study, Item $item, float $quantity, ?int $userId, ?ProtocolWorkflowStep $step = null): void
    {
        $businessId = (int) $study->business_id;
        $storeId = $this->resolveStoreId($businessId, $item->id, $userId, $study->main_room_id);

        if (! $storeId) {
            Log::info('Imaging consumption skipped: no store resolved', [
                'imaging_study_id' => $study->id,
                'item_id' => $item->id,
            ]);

            $this->recordConsumptionException(
                $study, $step, "No inventory store could be resolved for {$item->name}."
            );

            return;
        }

        $existing = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        if ($existing === null || (float) $existing <= 0) {
            $this->recordConsumptionException(
                $study, $step, "Resolved store has no available stock of {$item->name}."
            );

            return;
        }

        $this->analytics->recordConsumption(
            $businessId,
            $storeId,
            (int) $item->id,
            now()->toDateString(),
            $quantity,
            InventoryDailyConsumption::SOURCE_IMAGING,
            $userId,
            "Auto from imaging study {$study->accession_number}: {$item->name}",
            now()
        );
    }

    /**
     * RIS Amendment v2.6, Chunk 6: the "no store resolved" case was
     * already logged (never actioned further); the zero-stock case wasn't
     * even logged. Both now leave a resolvable record instead of a purely
     * silent skip. Created directly here rather than through
     * ConsumptionAttributionService, which wraps this engine the other way
     * around — routing through it would be a circular dependency.
     */
    private function recordConsumptionException(ImagingStudy $study, ?ProtocolWorkflowStep $step, string $reason): void
    {
        ImagingConsumptionException::create([
            'imaging_study_id' => $study->id,
            'imaging_protocol_workflow_step_id' => $step?->id,
            'exception_reason' => $reason,
        ]);
    }

    public function resolveStoreId(int $businessId, int $itemId, ?int $userId, ?int $mainRoomId = null): ?int
    {
        if ($mainRoomId) {
            $roomStore = ImagingServicePointConfig::query()
                ->where('business_id', $businessId)
                ->where('main_room_id', $mainRoomId)
                ->value('inventory_store_id');

            if ($roomStore) {
                return (int) $roomStore;
            }
        }

        if ($userId) {
            $userStore = User::query()->whereKey($userId)->value('default_store_id');

            if ($userStore) {
                return (int) $userStore;
            }
        }

        $stockStore = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('item_id', $itemId)
            ->where('quantity_suom', '>', 0)
            ->orderByDesc('quantity_suom')
            ->value('store_id');

        return $stockStore ? (int) $stockStore : null;
    }
}
