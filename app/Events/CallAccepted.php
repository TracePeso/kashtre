<?php

namespace App\Events;

use App\Models\P2PCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public P2PCall $call)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->call->caller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallAccepted';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->uuid,
            'callee'  => [
                'id'    => $this->call->callee->id,
                'uuid'  => $this->call->callee->uuid,
                'name'  => $this->call->callee->name,
                'photo' => $this->call->callee->profile_photo_url,
            ],
        ];
    }
}
