<?php

namespace App\Services\Inventory;

use App\Models\InventoryForensicAuditLog;
use App\Models\InventoryStockLevel;
use Illuminate\Support\Facades\DB;

class InventoryForensicAuditService
{
    public function record(
        int $businessId,
        string $context,
        ?int $actorUserId,
        ?int $storeId,
        ?int $itemId,
        ?float $oldQty,
        ?float $newQty,
        ?int $clientId = null,
        array $meta = []
    ): InventoryForensicAuditLog {
        return DB::transaction(function () use ($businessId, $context, $actorUserId, $storeId, $itemId, $oldQty, $newQty, $clientId, $meta) {
            $prev = InventoryForensicAuditLog::query()
                ->where('business_id', $businessId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $prevHash = $prev?->row_hash;
            $committedAt = now();
            $payload = [
                'business_id' => $businessId,
                'actor_user_id' => $actorUserId,
                'context' => $context,
                'store_id' => $storeId,
                'client_id' => $clientId,
                'item_id' => $itemId,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'meta' => $meta,
                'prev_hash' => $prevHash,
                'committed_at' => $committedAt->toIso8601String(),
            ];

            $rowHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));

            return InventoryForensicAuditLog::query()->create([
                'business_id' => $businessId,
                'actor_user_id' => $actorUserId,
                'context' => $context,
                'store_id' => $storeId,
                'client_id' => $clientId,
                'item_id' => $itemId,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'meta' => $meta,
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
                'committed_at' => $committedAt,
            ]);
        });
    }

    public function recordStockDelta(
        InventoryStockLevel $level,
        string $context,
        float $oldQty,
        float $newQty,
        ?int $actorUserId,
        ?int $clientId = null,
        array $meta = []
    ): void {
        $this->record(
            (int) $level->business_id,
            $context,
            $actorUserId,
            (int) $level->store_id,
            (int) $level->item_id,
            $oldQty,
            $newQty,
            $clientId,
            $meta
        );
    }

    /**
     * @return array{ok: bool, checked: int, first_break_id: ?int}
     */
    public function verifyChain(int $businessId): array
    {
        $rows = InventoryForensicAuditLog::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get();

        $prevHash = null;
        foreach ($rows as $row) {
            if ($row->prev_hash !== $prevHash) {
                return ['ok' => false, 'checked' => (int) $row->id, 'first_break_id' => (int) $row->id];
            }

            $payload = [
                'business_id' => $row->business_id,
                'actor_user_id' => $row->actor_user_id,
                'context' => $row->context,
                'store_id' => $row->store_id,
                'client_id' => $row->client_id,
                'item_id' => $row->item_id,
                'old_qty' => $row->old_qty !== null ? (float) $row->old_qty : null,
                'new_qty' => $row->new_qty !== null ? (float) $row->new_qty : null,
                'meta' => $row->meta ?? [],
                'prev_hash' => $row->prev_hash,
                'committed_at' => $row->committed_at?->toIso8601String(),
            ];

            $expected = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
            if (! hash_equals($expected, (string) $row->row_hash)) {
                return ['ok' => false, 'checked' => (int) $row->id, 'first_break_id' => (int) $row->id];
            }

            $prevHash = $row->row_hash;
        }

        return ['ok' => true, 'checked' => $rows->count(), 'first_break_id' => null];
    }
}
