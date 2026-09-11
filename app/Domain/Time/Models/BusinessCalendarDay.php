<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCalendarDay extends Model
{
    protected $table = 'core_business_calendar_days';

    protected $fillable = [
        'calendar_id',
        'local_date',
        'day_type',
        'label',
    ];

    protected $casts = [
        'local_date' => 'date',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'calendar_id');
    }
}
