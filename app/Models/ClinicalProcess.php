<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalProcess extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_processes';

    const ADMISSION = 'ADMISSION';
    const TRANSFER = 'TRANSFER';
    const DISCHARGE = 'DISCHARGE';
    const REFERRAL = 'REFERRAL';
    const DEATH_CERT = 'DEATH_CERT';

    protected $fillable = [
        'business_id',
        'process_code',
        'process_name',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(ClinicalProcessStep::class, 'process_id')->orderBy('step_order');
    }

    public static function resolve(int $businessId, string $processCode): ?self
    {
        return static::query()
            ->where('process_code', $processCode)
            ->where('is_active', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->orWhereNull('business_id');
            })
            ->orderByRaw('business_id IS NULL')
            ->first();
    }
}
