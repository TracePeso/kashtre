<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class P2PCallSignal extends Model
{
    protected $table = 'p2p_call_signals';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'delivered_at' => 'datetime',
    ];

    public function call()
    {
        return $this->belongsTo(P2PCall::class, 'p2p_call_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
