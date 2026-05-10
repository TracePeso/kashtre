<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaturationSystemDefault extends Model
{
    protected $table = 'maturation_system_defaults';

    protected $fillable = [
        'payment_method',
        'entity_maturation_days',
        'service_charge_maturation_days',
    ];

    protected $casts = [
        'entity_maturation_days' => 'integer',
        'service_charge_maturation_days' => 'integer',
    ];

    /**
     * All payment methods we allow system defaults for (from config keys).
     *
     * @return list<string>
     */
    public static function allowedPaymentMethods(): array
    {
        $entity = array_keys(config('maturation_defaults.entity', []));
        $service = array_keys(config('maturation_defaults.service_charge', []));

        return array_values(array_unique(array_merge($entity, $service)));
    }

    /**
     * Stable display order; unknown methods sort last.
     *
     * @return list<string>
     */
    public static function orderedPaymentMethods(): array
    {
        $preferred = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];
        $allowed = static::allowedPaymentMethods();
        $sorted = array_values(array_filter($preferred, fn (string $m): bool => in_array($m, $allowed, true)));
        $rest = array_diff($allowed, $sorted);
        sort($rest);

        return array_merge($sorted, array_values($rest));
    }

    public static function resolveEntityDays(string $paymentMethod): int
    {
        $row = static::query()->where('payment_method', $paymentMethod)->first();
        if ($row !== null) {
            return (int) $row->entity_maturation_days;
        }

        return (int) config("maturation_defaults.entity.{$paymentMethod}", 0);
    }

    public static function resolveServiceChargeDays(string $paymentMethod): int
    {
        $row = static::query()->where('payment_method', $paymentMethod)->first();
        if ($row !== null) {
            return (int) $row->service_charge_maturation_days;
        }

        return (int) config("maturation_defaults.service_charge.{$paymentMethod}", 0);
    }

    /**
     * Effective maps for UI and runtime (DB overrides config).
     *
     * @return array<string, int>
     */
    public static function entityDefaultsMap(): array
    {
        $map = [];
        foreach (static::orderedPaymentMethods() as $method) {
            $map[$method] = static::resolveEntityDays($method);
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    public static function serviceChargeDefaultsMap(): array
    {
        $map = [];
        foreach (static::orderedPaymentMethods() as $method) {
            $map[$method] = static::resolveServiceChargeDays($method);
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $entityDays
     * @param  array<string, int>  $serviceChargeDays
     */
    public static function syncFromArrays(array $entityDays, array $serviceChargeDays): void
    {
        foreach (static::allowedPaymentMethods() as $method) {
            static::query()->updateOrCreate(
                ['payment_method' => $method],
                [
                    'entity_maturation_days' => (int) ($entityDays[$method] ?? 0),
                    'service_charge_maturation_days' => (int) ($serviceChargeDays[$method] ?? 0),
                ]
            );
        }
    }
}
