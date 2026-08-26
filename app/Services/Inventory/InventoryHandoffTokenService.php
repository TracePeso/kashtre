<?php

namespace App\Services\Inventory;

use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use App\Services\ClinicalModuleIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryHandoffTokenService
{
    public function __construct(
        private readonly InventoryFulfillmentDispenseService $dispense,
        private readonly ClinicalModuleIntegrationService $clinical,
    ) {}

    /**
     * Validate a Clinical-issued 5-digit handoff code and dispense accepted staged lines.
     *
     * @param  list<int>  $flaggedLineIds  Lines to roll back to Pending (partial acceptance)
     * @return array{
     *     token: InventoryHandoffToken,
     *     completed: list<InventoryFulfillmentLine>,
     *     flagged: list<InventoryFulfillmentLine>,
     *     failed: list<array{line: InventoryFulfillmentLine, message: string}>
     * }
     */
    public function release(
        Store $store,
        string $code,
        User $user,
        ?InventoryHandoffToken $session = null,
        array $flaggedLineIds = [],
        array $traceabilityByLineId = [],
    ): array {
        if (! $store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Handoff release is only allowed at an End Store.',
            ]);
        }

        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 5) {
            throw ValidationException::withMessages([
                'code' => 'Enter the 5-digit handoff token from the ward nurse (Clinical Module).',
            ]);
        }

        $matched = $session;
        if ($matched === null) {
            $matched = InventoryHandoffToken::query()
                ->where('store_id', $store->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->first();
        }

        if (! $matched || ! $matched->isActive() || (int) $matched->store_id !== (int) $store->id) {
            throw ValidationException::withMessages([
                'code' => 'No active staged handoff session for this End Store.',
            ]);
        }

        $matched->loadMissing('store');

        $validation = $this->clinical->validateHandoffCode($code, $matched);
        if (! $validation['valid']) {
            throw ValidationException::withMessages([
                'code' => $validation['message'] ?? 'Clinical Module rejected the handoff code.',
            ]);
        }

        if (! empty($validation['clinical_session_id'])) {
            $matched->clinical_session_id = $validation['clinical_session_id'];
            $matched->save();
        }

        $flaggedLineIds = array_values(array_unique(array_map('intval', $flaggedLineIds)));

        return DB::transaction(function () use ($matched, $user, $flaggedLineIds, $traceabilityByLineId) {
            $lineIds = array_values(array_map('intval', $matched->fulfillment_line_ids ?? []));

            $lines = InventoryFulfillmentLine::query()
                ->whereIn('id', $lineIds)
                ->get()
                ->keyBy('id');

            $flagged = [];
            $completed = [];
            $failed = [];

            foreach ($lineIds as $lineId) {
                $line = $lines->get($lineId);
                if (! $line) {
                    continue;
                }

                if (in_array($lineId, $flaggedLineIds, true)) {
                    $flagged[] = $this->rollbackFlaggedLine($line, $matched);
                    continue;
                }

                if ($line->status !== InventoryFulfillmentLine::STATUS_STAGED) {
                    $failed[] = [
                        'line' => $line,
                        'message' => 'Line is '.$line->statusLabel().', expected Staged.',
                    ];
                    continue;
                }

                try {
                    $trace = $traceabilityByLineId[$lineId]
                        ?? $traceabilityByLineId[(string) $lineId]
                        ?? null;
                    $completed[] = $this->dispense->complete(
                        $line,
                        $user,
                        null,
                        is_array($trace) ? $trace : null
                    );
                } catch (ValidationException $e) {
                    $failed[] = [
                        'line' => $line->fresh(),
                        'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                    ];
                }
            }

            if ($completed === [] && $flagged === [] && $failed !== []) {
                throw ValidationException::withMessages([
                    'code' => 'Handoff could not release any lines: '.($failed[0]['message'] ?? 'unknown error'),
                ]);
            }

            if ($completed === [] && $flagged === []) {
                throw ValidationException::withMessages([
                    'code' => 'No staged lines available to release for this handoff.',
                ]);
            }

            // Keep failed staged lines on a refreshed session requirement; mark used when we released or flagged.
            $matched->fulfillment_line_ids = array_values(array_diff($lineIds, $flaggedLineIds));
            $matched->used_at = now();
            $matched->used_by = $user->id;
            $matched->save();

            return [
                'token' => $matched->fresh(),
                'completed' => $completed,
                'flagged' => $flagged,
                'failed' => $failed,
            ];
        });
    }

    /**
     * Resolve the active handoff session for a staged line's basket.
     */
    public function activeSessionForLine(InventoryFulfillmentLine $line): ?InventoryHandoffToken
    {
        if ($line->handoff_token_id) {
            $token = InventoryHandoffToken::query()->find($line->handoff_token_id);
            if ($token && $token->isActive()) {
                return $token;
            }
        }

        return InventoryHandoffToken::query()
            ->where('store_id', $line->store_id)
            ->where('basket_key', (string) $line->basket_key)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Active (unused, unexpired) handoffs for an End Store.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryHandoffToken>
     */
    public function activeForStore(int $storeId)
    {
        $tokens = InventoryHandoffToken::query()
            ->where('store_id', $storeId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get([
                'id',
                'uuid',
                'store_id',
                'client_space_id',
                'basket_key',
                'expires_at',
                'fulfillment_line_ids',
                'clinical_session_id',
                'clinical_notified_at',
            ]);

        $clientIds = $tokens
            ->pluck('basket_key')
            ->filter(fn ($key) => $key !== null && ctype_digit((string) $key))
            ->map(fn ($key) => (int) $key)
            ->unique()
            ->values();

        if ($clientIds->isNotEmpty()) {
            $names = Client::query()
                ->whereIn('id', $clientIds)
                ->pluck('name', 'id');

            $tokens->each(function (InventoryHandoffToken $token) use ($names) {
                if ($token->basket_key && ctype_digit((string) $token->basket_key)) {
                    $token->setAttribute('basket_client_name', $names[(int) $token->basket_key] ?? null);
                }
            });
        }

        return $tokens;
    }

    protected function rollbackFlaggedLine(
        InventoryFulfillmentLine $line,
        InventoryHandoffToken $token
    ): InventoryFulfillmentLine {
        $meta = $line->metadata ?? [];
        $meta['flagged_from_handoff'] = [
            'handoff_ref' => $token->uuid,
            'flagged_at' => now()->toIso8601String(),
        ];

        $line->status = InventoryFulfillmentLine::STATUS_PENDING;
        $line->staged_at = null;
        $line->handoff_token_id = null;
        $line->metadata = $meta;
        $line->notes = trim(($line->notes ? $line->notes."\n" : '').'Flagged at handoff; rolled back for urgent correction.');
        $line->save();

        return $line->fresh();
    }
}
