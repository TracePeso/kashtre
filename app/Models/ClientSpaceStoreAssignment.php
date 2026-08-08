<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientSpaceStoreAssignment extends Model
{
    public const STRATEGY_DISCRETE_IMMEDIATE = 'DISCRETE_IMMEDIATE';

    public const STRATEGY_BATCH_AND_STAGE = 'BATCH_AND_STAGE';

    protected $fillable = [
        'uuid',
        'business_id',
        'client_space_id',
        'store_id',
        'fulfillment_strategy',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'client_space_id' => 'integer',
        'store_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment) {
            if (empty($assignment->uuid)) {
                $assignment->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $assignment) {
            $assignment->enforceRoutingRules();
        });
    }

    /**
     * @return array<string, string>
     */
    public static function strategyOptions(): array
    {
        return [
            self::STRATEGY_DISCRETE_IMMEDIATE => 'Outpatient (discrete / immediate)',
            self::STRATEGY_BATCH_AND_STAGE => 'Inpatient (batch & stage)',
        ];
    }

    public function strategyLabel(): string
    {
        return self::strategyOptions()[$this->fulfillment_strategy]
            ?? $this->fulfillment_strategy;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function clientSpace(): BelongsTo
    {
        return $this->belongsTo(ClientSpace::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function endStore(): BelongsTo
    {
        return $this->store();
    }

    /**
     * Resolve the active End Store routing for a Client Space (SRD §4.1).
     */
    public static function resolveForClientSpace(int $clientSpaceId): ?self
    {
        return static::query()
            ->with(['store', 'clientSpace'])
            ->where('client_space_id', $clientSpaceId)
            ->where('is_active', true)
            ->first();
    }

    protected function enforceRoutingRules(): void
    {
        $space = ClientSpace::query()->find($this->client_space_id);
        $store = Store::query()->find($this->store_id);

        if (! $space) {
            throw ValidationException::withMessages([
                'client_space_id' => 'The selected client space is invalid.',
            ]);
        }

        if (! $store) {
            throw ValidationException::withMessages([
                'store_id' => 'The selected end store is invalid.',
            ]);
        }

        if (! $store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Only End Stores can be assigned to a Client Space.',
            ]);
        }

        if ((int) $space->business_id !== (int) $store->business_id) {
            throw ValidationException::withMessages([
                'store_id' => 'Client Space and End Store must belong to the same business.',
            ]);
        }

        $this->business_id = (int) $space->business_id;

        if (! in_array($this->fulfillment_strategy, array_keys(self::strategyOptions()), true)) {
            throw ValidationException::withMessages([
                'fulfillment_strategy' => 'Choose a valid fulfillment strategy.',
            ]);
        }
    }
}
