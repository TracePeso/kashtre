<?php

namespace App\Services\Inventory;

use App\Jobs\NotifyClinicalToteStaged;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\InventoryFulfillmentStrategy;

class InventoryFulfillmentStageService
{
    /**
     * Stage inpatient line(s) for a patient basket and open a Clinical-owned handoff session.
     * No stock / Approved Pool / Main Module changes. Does not generate the nurse 5-digit code.
     *
     * @return array{token: InventoryHandoffToken, lines: Collection<int, InventoryFulfillmentLine>}
     */
    public function stageBasket(
        InventoryFulfillmentLine $seedLine,
        User $user,
        bool $entireBasket = true,
        ?string $toteBarcode = null,
        bool $notifyClinical = true,
    ): array {
        $seedLine->loadMissing(['store', 'client']);

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

        $tote = $toteBarcode !== null ? trim($toteBarcode) : '';
        if ($tote === '') {
            throw ValidationException::withMessages([
                'tote_barcode' => 'Enter the tote barcode or ID on the physical bin before staging.',
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

        $result = DB::transaction(function () use ($seedLine, $user, $toStage, $stageable, $alreadyStaged, $tote) {
            $now = now();

            // Expire any prior unused sessions for this basket at this store.
            InventoryHandoffToken::query()
                ->where('store_id', $seedLine->store_id)
                ->where('basket_key', (string) $seedLine->basket_key)
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->update(['expires_at' => $now]);

            $token = InventoryHandoffToken::create([
                'business_id' => $seedLine->business_id,
                'store_id' => $seedLine->store_id,
                'client_space_id' => $seedLine->client_space_id,
                'basket_key' => (string) $seedLine->basket_key,
                'tote_barcode' => $tote,
                'code_hash' => null,
                'expires_at' => $now->copy()->addMinutes(InventoryHandoffToken::DEFAULT_TTL_MINUTES),
                'created_by' => $user->id,
                'fulfillment_line_ids' => $toStage->pluck('id')->values()->all(),
            ]);

            foreach ($stageable as $line) {
                $line->status = InventoryFulfillmentLine::STATUS_STAGED;
                $line->staged_at = $now;
                $line->handoff_token_id = $token->id;
                $line->save();
            }

            foreach ($alreadyStaged as $line) {
                $line->handoff_token_id = $token->id;
                $line->save();
            }

            return [
                'token' => $token->fresh(['store', 'clientSpace']),
                'lines' => $toStage->map->fresh(),
            ];
        });

        if ($notifyClinical) {
            NotifyClinicalToteStaged::dispatch($result['token']->id)->afterResponse();
        }

        return $result;
    }

    /**
     * Stage every open inpatient basket in a ward collection into one tote.
     * Stock is not reduced. Each patient envelope keeps its own handoff.
     *
     * @return list<array{token: InventoryHandoffToken, lines: Collection<int, InventoryFulfillmentLine>}>
     */
    public function stageReservoir(
        Store $store,
        User $user,
        string $toteBarcode,
        ?int $clientSpaceId = null,
        ?string $visitId = null,
    ): array {
        if (! $store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Staging is only allowed from an End Store.',
            ]);
        }

        $tote = trim($toteBarcode);
        if ($tote === '') {
            throw ValidationException::withMessages([
                'tote_barcode' => 'Scan the ward tote barcode before staging.',
            ]);
        }

        $seeds = InventoryFulfillmentLine::query()
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->when($clientSpaceId, fn ($q) => $q->where('client_space_id', $clientSpaceId))
            ->when($visitId !== null && $visitId !== '', fn ($q) => $q->where('visit_id', $visitId))
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
            ])
            ->orderBy('id')
            ->get()
            ->unique('basket_key')
            ->values();

        if ($seeds->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Nothing left to stage on this pick route.',
            ]);
        }

        $staged = DB::transaction(function () use ($seeds, $user, $tote) {
            $out = [];
            foreach ($seeds as $seed) {
                $out[] = $this->stageBasket($seed, $user, true, $tote, false);
            }

            return $out;
        });

        foreach ($staged as $result) {
            NotifyClinicalToteStaged::dispatch($result['token']->id)->afterResponse();
        }

        return $staged;
    }

    /**
     * @return Collection<int, InventoryFulfillmentLine>
     */
    protected function basketLines(InventoryFulfillmentLine $seedLine): Collection
    {
        return InventoryFulfillmentLine::query()
            ->where('business_id', $seedLine->business_id)
            ->where('store_id', $seedLine->store_id)
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
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
}
