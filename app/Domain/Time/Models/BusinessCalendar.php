<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessCalendar extends Model
{
    protected $table = 'core_business_calendars';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'name',
        'iana_id',
        'version_no',
        'status',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'version_no' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function days(): HasMany
    {
        return $this->hasMany(BusinessCalendarDay::class, 'calendar_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
