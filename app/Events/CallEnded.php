<?php

namespace App\Events;

use App\Models\P2PCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $targetUserId;

    public function __construct(public P2PCall $call, int $targetUserId)
    {
        $this->targetUserId = $targetUserId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallEnded';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id'    => $this->call->uuid,
            'reason'     => $this->call->end_reason,
            'duration'   => $this->call->duration,
        ];
    }
}
