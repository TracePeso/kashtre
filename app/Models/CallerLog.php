<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallerLog extends Model
{
    protected $fillable = [
        'business_id',
        'caller_id',
        'service_point_id',
        'room_id',
        'client_id',
        'client_name',
        'item_name',
        'called_by',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function caller()
    {
        return $this->belongsTo(Caller::class);
    }

    public function servicePoint()
    {
        return $this->belongsTo(ServicePoint::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function calledBy()
    {
        return $this->belongsTo(User::class, 'called_by');
    }
}
