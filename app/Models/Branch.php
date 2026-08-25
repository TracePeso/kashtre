<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'email',
        'phone',
        'address',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function servicePoints()
    {
        return $this->hasMany(ServicePoint::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
