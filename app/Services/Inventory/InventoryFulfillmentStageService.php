<?php

namespace App\Services\Inventory;

use App\Models\ClientSpaceStoreAssignment;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InventoryFulfillmentStageService
{
    /**
     * Stage inpatient line(s) for a patient basket and issue a one-time handoff token.
     * No stock / Approved Pool / Main Module changes.
     *
     * @return array{token: InventoryHandoffToken, plaintext_code: string, lines: Collection<int, InventoryFulfillmentLine>}
     */
    public function stageBasket(InventoryFulfillmentLine $seedLine, User $user, bool $entireBasket = true): array
    {
        $seedLine->loadMissing(['store', 'client', 'clientSpace']);

        if (! $seedLine->isInpatient()) {
            throw ValidationException::withMessages([
                'fulfillment_strategy' => 'Staging is only for inpatient (batch & stage) lines.',
            ]);
        }

        if (! $seedLine->store || ! $seedLine->store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Staging is only allowed from an End Store.',
            ]);
        }

        $lines = $entireBasket
            ? $this->basketLines($seedLine)
            : collect([$seedLine])->filter(fn (InventoryFulfillmentLine $line) => $line->isStageable() || $line->isStaged());

        $stageable = $lines->filter(fn (InventoryFulfillmentLine $line) => $line->isStageable());
        $alreadyStaged = $lines->filter(fn (InventoryFulfillmentLine $line) => $line->isStaged());
        $toStage = $stageable->merge($alreadyStaged)->unique('id')->values();

        if ($toStage->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'No open inpatient lines available to stage for this basket.',
            ]);
        }

        return DB::transaction(function () use ($seedLine, $user, $toStage, $stageable) {
            $now = now();

            foreach ($stageable as $line) {
                $line->status = InventoryFulfillmentLine::STATUS_STAGED;
                $line->staged_at = $now;
                $line->save();
            }

            // Expire any prior unused tokens for this basket at this store.
            InventoryHandoffToken::query()
                ->where('store_id', $seedLine->store_id)
                ->where('basket_key', (string) $seedLine->basket_key)
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->update(['expires_at' => $now]);

            $plaintext = $this->generateCode();

            $token = InventoryHandoffToken::create([
                'business_id' => $seedLine->business_id,
                'store_id' => $seedLine->store_id,
                'client_space_id' => $seedLine->client_space_id,
                'basket_key' => (string) $seedLine->basket_key,
                'code_hash' => Hash::make($plaintext),
                'expires_at' => $now->copy()->addMinutes(InventoryHandoffToken::DEFAULT_TTL_MINUTES),
                'created_by' => $user->id,
                'fulfillment_line_ids' => $toStage->pluck('id')->values()->all(),
            ]);

            return [
                'token' => $token->fresh(['clientSpace', 'store']),
                'plaintext_code' => $plaintext,
                'lines' => $toStage->map->fresh(),
            ];
        });
    }

    /**
     * @return Collection<int, InventoryFulfillmentLine>
     */
    protected function basketLines(InventoryFulfillmentLine $seedLine): Collection
    {
        return InventoryFulfillmentLine::query()
            ->where('business_id', $seedLine->business_id)
            ->where('store_id', $seedLine->store_id)
            ->where('fulfillment_strategy', ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE)
            ->where('basket_key', (string) $seedLine->basket_key)
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
                InventoryFulfillmentLine::STATUS_STAGED,
            ])
            ->orderBy('id')
            ->get();
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
}
