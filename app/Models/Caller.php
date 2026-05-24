<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Caller extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'business_id',
        'branch_id',
        'status',
        'token',
        'user_id',
        'display_token',
        'announcement_message',
        'speech_rate',
        'speech_volume',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id'   => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($caller) {
            $caller->uuid  = $caller->uuid  ?? (string) Str::uuid();
            $caller->token = $caller->token ?? Str::random(32);
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servicePoints()
    {
        return $this->belongsToMany(ServicePoint::class, 'caller_service_points')
                    ->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
