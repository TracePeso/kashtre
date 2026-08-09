<?php

namespace App\Services\Inventory;

use App\Models\Client;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryModuleConfig;
use Illuminate\Support\Facades\Log;

class InventoryVisitReactivationService
{
    /**
     * When a client receives a new visitor/visit_id, reattach historical open fulfillment lines (SRD §8.2).
     */
    public function reactivateForClient(Client $client, ?string $previousVisitId = null): int
    {
        if (! filled($client->visit_id) || $client->visit_id === $previousVisitId) {
            return 0;
        }

        $lookback = (int) (InventoryModuleConfig::query()
            ->where('business_id', $client->business_id)
            ->value('visit_reactivation_lookback_days') ?? 30);

        $since = now()->subDays(max(1, $lookback));

        $updated = InventoryFulfillmentLine::query()
            ->where('business_id', $client->business_id)
            ->where('client_id', $client->id)
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
                InventoryFulfillmentLine::STATUS_STAGED,
            ])
            ->where('queued_at', '>=', $since)
            ->where(function ($q) use ($client) {
                $q->whereNull('visit_id')
                    ->orWhere('visit_id', '!=', $client->visit_id);
            })
            ->update(['visit_id' => $client->visit_id]);

        if ($updated > 0) {
            Log::info('Inventory fulfillment re-activated for new visit', [
                'client_id' => $client->id,
                'visit_id' => $client->visit_id,
                'lines' => $updated,
            ]);
        }

        return $updated;
    }
}
