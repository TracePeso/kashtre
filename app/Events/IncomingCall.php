<?php

namespace App\Events;

use App\Models\P2PCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCall implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public P2PCall $call)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->call->callee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'IncomingCall';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id'    => $this->call->uuid,
            'caller'     => [
                'id'    => $this->call->caller->id,
                'uuid'  => $this->call->caller->uuid,
                'name'  => $this->call->caller->p2p_name,
                'photo' => $this->call->caller->profile_photo_url,
            ],
            'started_at' => $this->call->started_at->toISOString(),
        ];
    }
}
