<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\PatientApprovedPoolLine;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRecordUsageService
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics,
        private readonly InventoryMainModuleSyncService $mainModule,
        private readonly InventoryForensicAuditService $audit,
        private readonly InventoryExpiredEscrowService $escrow,
    ) {}

    /**
     * Record bedside / floor usage (SRD §5.2).
     *
     * Patient: use Approved Pool first; any shortfall comes from selected floor stock
     * (End / Satellite) and is billed to Main Module asynchronously.
     * Crash cart with client_id: physical stock ↓ + Main Module billing + replenishment signal.
     * Administrative / wastage: deduct physical stock only (no Main billing).
     * Expired wastage is excluded from moving-average demand maths.
     *
     * @param  array{
     *     business_id: int,
     *     context: string,
     *     client_id?: int|null,
     *     item_id: int,
     *     store_id?: int|null,
     *     quantity: float|int|string,
     *     notes?: string|null,
     *     occurred_at?: \DateTimeInterface|string|null
     * }  $data
     * @return Collection<int, InventoryUsageEvent>
     */
    public function record(array $data, User $user): Collection
    {
        $businessId = (int) $data['business_id'];
        $context = (string) $data['context'];
        $itemId = (int) $data['item_id'];
        $quantity = round((float) $data['quantity'], 4);
        $clientId = isset($data['client_id']) ? (int) $data['client_id'] : null;
        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
        $notes = $notes !== '' ? $notes : null;
        $occurredAt = $data['occurred_at'] ?? now();

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if ($context === InventoryUsageEvent::CONTEXT_PATIENT) {
            return $this->recordPatientUsage(
                $businessId,
                $clientId,
                $itemId,
                $storeId,
                $quantity,
                $notes,
                $occurredAt,
                $user
            );
        }

        $stockContexts = [
            InventoryUsageEvent::CONTEXT_ADMINISTRATIVE => [
                'classification' => InventoryUsageEvent::CLASSIFICATION_ADMINISTRATIVE,
                'label' => 'Administrative usage',
                'source' => InventoryDailyConsumption::SOURCE_MANUAL,
                'require_crash_cart' => false,
                'require_crash_capability' => false,
                'bill' => false,
            ],
            InventoryUsageEvent::CONTEXT_CRASH_CART => [
                'classification' => InventoryUsageEvent::CLASSIFICATION_CRASH_CART,
                'label' => 'Crash cart usage',
                'source' => InventoryDailyConsumption::SOURCE_MANUAL,
                'require_crash_cart' => true,
                'require_crash_capability' => true,
                'bill' => true,
            ],
            InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL => [
                'classification' => InventoryUsageEvent::CLASSIFICATION_WASTAGE_OPERATIONAL,
                'label' => 'Wastage (operational)',
                'source' => InventoryDailyConsumption::SOURCE_MANUAL,
                'require_crash_cart' => false,
                'require_crash_capability' => false,
                'bill' => false,
            ],
            InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED => [
                'classification' => InventoryUsageEvent::CLASSIFICATION_WASTAGE_EXPIRED,
                'label' => 'Wastage (expired)',
                'source' => InventoryDailyConsumption::SOURCE_WASTAGE_EXPIRED,
                'require_crash_cart' => false,
                'require_crash_capability' => false,
                'bill' => false,
            ],
        ];

        if (! isset($stockContexts[$context])) {
            throw ValidationException::withMessages([
                'context' => 'Unknown usage context.',
            ]);
        }

        $meta = $stockContexts[$context];

        if ($meta['require_crash_capability']) {
            $this->assertCrashCartEnabled($businessId);
        }

        $this->assertFloorStockEnabled($businessId);

        if ($meta['bill'] && ! $clientId) {
            throw ValidationException::withMessages([
                'client_id' => 'Select the patient to bill for crash cart usage (Main Module postpaid packet).',
            ]);
        }

        $event = $this->recordPhysicalStockUsage(
            businessId: $businessId,
            context: $context,
            classification: $meta['classification'],
            clientId: $meta['bill'] ? $clientId : null,
            storeId: $storeId,
            itemId: $itemId,
            quantity: $quantity,
            billed: (bool) $meta['bill'],
            notes: $notes,
            occurredAt: $occurredAt,
            user: $user,
            consumptionLabel: $meta['label'],
            consumptionSource: $meta['source'],
            requireCrashCartStore: $meta['require_crash_cart'],
        );

        if ($event->billed_main_module) {
            $this->mainModule->dispatchUsageBilling($event);
        }

        if ($context === InventoryUsageEvent::CONTEXT_CRASH_CART) {
            // Outbox signal only — IR draft is created once on Seal Ready (SRD §6 step 4–5).
            $this->mainModule->enqueueCrashCartReplenishment($event);
        }

        return collect([$event]);
    }

    /**
     * @return Collection<int, InventoryUsageEvent>
     */
    protected function recordPatientUsage(
        int $businessId,
        ?int $clientId,
        int $itemId,
        ?int $storeId,
        float $quantity,
        ?string $notes,
        mixed $occurredAt,
        User $user
    ): Collection {
        if (! $clientId) {
            throw ValidationException::withMessages([
                'client_id' => 'Select a client for patient usage.',
            ]);
        }

        return DB::transaction(function () use ($businessId, $clientId, $itemId, $storeId, $quantity, $notes, $occurredAt, $user) {
            $poolLines = PatientApprovedPoolLine::query()
                ->where('business_id', $businessId)
                ->where('client_id', $clientId)
                ->where('item_id', $itemId)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $poolAvailable = (float) $poolLines->sum('quantity_remaining');
            $fromPool = min($poolAvailable, $quantity);
            $fromFloor = round($quantity - $fromPool, 4);

            if ($fromFloor > 0) {
                if (! $this->floorStockEnabled($businessId)) {
                    throw ValidationException::withMessages([
                        'quantity' => $poolAvailable > 0
                            ? 'Approved Pool only covers '.rtrim(rtrim(number_format($fromPool, 2), '0'), '.')
                                .'. Floor stock management is disabled, so the remaining quantity cannot be recorded.'
                            : 'No Approved Pool balance for this item, and floor stock management is disabled.',
                    ]);
                }

                if (! $storeId) {
                    throw ValidationException::withMessages([
                        'store_id' => $poolAvailable > 0
                            ? 'Approved Pool covers '.rtrim(rtrim(number_format($fromPool, 2), '0'), '.')
                                .'. Select a store for the remaining '
                                .rtrim(rtrim(number_format($fromFloor, 2), '0'), '.').'.'
                            : 'No Approved Pool balance for this item. Select a store to record floor usage.',
                    ]);
                }
            }

            $events = collect();

            if ($fromPool > 0) {
                $events->push($this->depletePoolAndCreateEvent(
                    $businessId,
                    $clientId,
                    $itemId,
                    $fromPool,
                    $poolLines,
                    $notes,
                    $occurredAt,
                    $user
                ));
            }

            if ($fromFloor > 0) {
                $floorEvent = $this->recordPhysicalStockUsage(
                    businessId: $businessId,
                    context: InventoryUsageEvent::CONTEXT_PATIENT,
                    classification: InventoryUsageEvent::CLASSIFICATION_PATIENT,
                    clientId: $clientId,
                    storeId: $storeId,
                    itemId: $itemId,
                    quantity: $fromFloor,
                    billed: true,
                    notes: $notes,
                    occurredAt: $occurredAt,
                    user: $user,
                    consumptionLabel: 'Patient floor usage'
                );
                $events->push($floorEvent);
                $this->mainModule->dispatchUsageBilling($floorEvent);
            }

            return $events;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PatientApprovedPoolLine>  $poolLines
     */
    protected function depletePoolAndCreateEvent(
        int $businessId,
        int $clientId,
        int $itemId,
        float $quantity,
        $poolLines,
        ?string $notes,
        mixed $occurredAt,
        User $user
    ): InventoryUsageEvent {
        $remaining = $quantity;
        $allocations = [];

        foreach ($poolLines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $line->quantity_remaining, $remaining);
            if ($take <= 0) {
                continue;
            }

            $line->quantity_remaining = round((float) $line->quantity_remaining - $take, 4);
            $line->save();

            $allocations[] = [
                'pool_line_id' => $line->id,
                'quantity' => $take,
            ];
            $remaining = round($remaining - $take, 4);
        }

        $poolRemainingAfter = (float) $poolLines->sum(fn ($line) => (float) $line->quantity_remaining);

        $this->audit->record(
            $businessId,
            'PATIENT_USAGE_APPROVED_POOL',
            $user->id,
            null,
            $itemId,
            round($poolRemainingAfter + $quantity, 4),
            $poolRemainingAfter,
            $clientId,
            [
                'quantity' => $quantity,
                'pool_allocations' => $allocations,
                'resolution' => InventoryUsageEvent::RESOLUTION_APPROVED_POOL,
            ]
        );

        return InventoryUsageEvent::create([
            'business_id' => $businessId,
            'context' => InventoryUsageEvent::CONTEXT_PATIENT,
            'classification' => InventoryUsageEvent::CLASSIFICATION_PATIENT,
            'client_id' => $clientId,
            'item_id' => $itemId,
            'store_id' => null,
            'quantity' => $quantity,
            'resolution' => InventoryUsageEvent::RESOLUTION_APPROVED_POOL,
            'billed_main_module' => false,
            'recorded_by' => $user->id,
            'occurred_at' => $occurredAt,
            'notes' => $notes,
            'pool_allocations' => $allocations,
        ]);
    }

    protected function recordPhysicalStockUsage(
        int $businessId,
        string $context,
        string $classification,
        ?int $clientId,
        ?int $storeId,
        int $itemId,
        float $quantity,
        bool $billed,
        ?string $notes,
        mixed $occurredAt,
        User $user,
        string $consumptionLabel,
        string $consumptionSource = InventoryDailyConsumption::SOURCE_MANUAL,
        bool $requireCrashCartStore = false,
    ): InventoryUsageEvent {
        if (! $storeId) {
            throw ValidationException::withMessages([
                'store_id' => 'Select a store.',
            ]);
        }

        $this->assertFloorStockEnabled($businessId);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($storeId)
            ->first();

        if (! $store) {
            throw ValidationException::withMessages([
                'store_id' => 'Store not found for this organisation.',
            ]);
        }

        if ($requireCrashCartStore) {
            if (! $store->isCrashCart()) {
                throw ValidationException::withMessages([
                    'store_id' => 'Select a crash cart satellite store.',
                ]);
            }

            // SRD §6: Deploy first (no docs); Record Usage only while Reconciling.
            if ($store->crash_cart_status === Store::CRASH_CART_READY) {
                throw ValidationException::withMessages([
                    'store_id' => 'Deploy this crash cart before recording usage. Use Crash Carts → Deploy, then Start reconcile.',
                ]);
            }

            if ($store->crash_cart_status === Store::CRASH_CART_DEPLOYED) {
                throw ValidationException::withMessages([
                    'store_id' => 'Crash cart is deployed for emergency use — no inventory documentation yet. Start reconcile first.',
                ]);
            }

            if ($store->crash_cart_status !== Store::CRASH_CART_RECONCILING) {
                throw ValidationException::withMessages([
                    'store_id' => 'Crash cart must be in Reconciling status to record usage.',
                ]);
            }
        } elseif (! in_array($store->distribution_type, [
            Store::DISTRIBUTION_END,
            Store::DISTRIBUTION_SATELLITE,
        ], true)) {
            throw ValidationException::withMessages([
                'store_id' => 'Floor usage must come from an End Store or Satellite.',
            ]);
        }

        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->value('quantity_suom') ?? 0);

        if ($onHand + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock at '.$store->name.'. On hand: '
                    .number_format($onHand, 0).', needed: '.number_format($quantity, 0).'.',
            ]);
        }

        if ($classification === InventoryUsageEvent::CLASSIFICATION_WASTAGE_EXPIRED) {
            $this->escrow->moveToEscrow(
                $businessId,
                $storeId,
                $itemId,
                $quantity,
                $user,
                $notes
            );
        } else {
            $this->analytics->recordConsumption(
                $businessId,
                $storeId,
                $itemId,
                now()->toDateString(),
                $quantity,
                $consumptionSource,
                (int) $user->id,
                $consumptionLabel.($notes ? ': '.$notes : ''),
                $occurredAt instanceof \DateTimeInterface ? $occurredAt : now(),
                null
            );
        }

        $this->audit->record(
            $businessId,
            $classification,
            (int) $user->id,
            $storeId,
            $itemId,
            $onHand,
            max(0, $onHand - $quantity),
            $clientId,
            ['context' => $context]
        );

        return InventoryUsageEvent::create([
            'business_id' => $businessId,
            'context' => $context,
            'classification' => $classification,
            'client_id' => $clientId,
            'item_id' => $itemId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'resolution' => InventoryUsageEvent::RESOLUTION_PHYSICAL_STOCK,
            'billed_main_module' => $billed,
            'main_billing_status' => $billed ? 'pending' : null,
            'recorded_by' => $user->id,
            'occurred_at' => $occurredAt,
            'notes' => $notes,
            'pool_allocations' => null,
        ]);
    }

    protected function assertFloorStockEnabled(int $businessId): void
    {
        if (! $this->floorStockEnabled($businessId)) {
            throw ValidationException::withMessages([
                'store_id' => 'Floor stock management is disabled for this organisation. Enable it under Inventory settings → Capabilities.',
            ]);
        }
    }

    protected function assertCrashCartEnabled(int $businessId): void
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->first();

        if (! ($config?->crashCartEnabled() ?? false)) {
            throw ValidationException::withMessages([
                'context' => 'Crash cart management is disabled. Enable it under Inventory settings → Capabilities.',
            ]);
        }
    }

    protected function floorStockEnabled(int $businessId): bool
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->first();

        return $config?->floorStockEnabled() ?? true;
    }
}
