<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalDdiDictionary extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_ddi_dictionary';

    const SEVERITY_INFO = 'INFO';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_HARD_BLOCK = 'HARD_BLOCK';

    protected $fillable = [
        'business_id',
        'drug_a_code',
        'drug_b_code',
        'severity',
        'description',
    ];

    protected $casts = [
        'business_id' => 'integer',
    ];

    /**
     * Bidirectional lookup (A-B or B-A) — the dictionary only needs one
     * row per pair, not two.
     */
    public static function findInteraction(int $businessId, string $drugCodeA, string $drugCodeB): ?self
    {
        return static::query()
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->orWhereNull('business_id');
            })
            ->where(function ($query) use ($drugCodeA, $drugCodeB) {
                $query->where(['drug_a_code' => $drugCodeA, 'drug_b_code' => $drugCodeB])
                    ->orWhere(['drug_a_code' => $drugCodeB, 'drug_b_code' => $drugCodeA]);
            })
            ->orderByRaw('business_id IS NULL')
            ->first();
    }
}
