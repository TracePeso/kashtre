<?php

namespace App\Services\Inventory;

use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InventoryHandoffTokenService
{
    public function __construct(
        private readonly InventoryFulfillmentDispenseService $dispense,
    ) {}

    /**
     * Validate a 5-digit handoff code for an End Store and dispense all linked staged lines.
     *
     * @return array{
     *     token: InventoryHandoffToken,
     *     completed: list<InventoryFulfillmentLine>,
     *     failed: list<array{line: InventoryFulfillmentLine, message: string}>
     * }
     */
    public function release(Store $store, string $code, User $user): array
    {
        if (! $store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Handoff release is only allowed at an End Store.',
            ]);
        }

        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 5) {
            throw ValidationException::withMessages([
                'code' => 'Enter the 5-digit handoff token.',
            ]);
        }

        $candidates = InventoryHandoffToken::query()
            ->where('store_id', $store->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get();

        $matched = null;
        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code_hash)) {
                $matched = $candidate;
                break;
            }
        }

        if (! $matched) {
            throw ValidationException::withMessages([
                'code' => 'Invalid or expired handoff token for this End Store.',
            ]);
        }

        return DB::transaction(function () use ($matched, $user) {
            $lineIds = array_values(array_map('intval', $matched->fulfillment_line_ids ?? []));

            $lines = InventoryFulfillmentLine::query()
                ->whereIn('id', $lineIds)
                ->get()
                ->keyBy('id');

            $completed = [];
            $failed = [];

            foreach ($lineIds as $lineId) {
                $line = $lines->get($lineId);
                if (! $line) {
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
                    $completed[] = $this->dispense->complete($line, $user);
                } catch (ValidationException $e) {
                    $failed[] = [
                        'line' => $line->fresh(),
                        'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                    ];
                }
            }

            if ($completed === [] && $failed !== []) {
                throw ValidationException::withMessages([
                    'code' => 'Handoff could not release any lines: '.($failed[0]['message'] ?? 'unknown error'),
                ]);
            }

            // Mark used when at least one line released (partial accept allowed).
            $matched->used_at = now();
            $matched->used_by = $user->id;
            $matched->save();

            return [
                'token' => $matched->fresh(),
                'completed' => $completed,
                'failed' => $failed,
            ];
        });
    }

    /**
     * Active (unused, unexpired) handoffs for an End Store.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryHandoffToken>
     */
    public function activeForStore(int $storeId)
    {
        $tokens = InventoryHandoffToken::query()
            ->with(['clientSpace:id,name'])
            ->where('store_id', $storeId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get(['id', 'uuid', 'store_id', 'client_space_id', 'basket_key', 'expires_at', 'fulfillment_line_ids']);

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
}
