<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinancialPeriod extends Model
{
    protected $table = 'core_financial_periods';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'name',
        'period_type',
        'local_start_date',
        'local_end_date',
        'status',
        'iana_id',
        'closed_at_utc',
        'closed_by',
    ];

    protected $casts = [
        'local_start_date' => 'date',
        'local_end_date' => 'date',
        'closed_at_utc' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['OPEN', 'REOPENED'], true);
    }
}
