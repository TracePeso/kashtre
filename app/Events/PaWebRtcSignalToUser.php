<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaWebRtcSignalToUser implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $targetUserId,
        public int $callerId,
        public string $sessionId,
        public string $type,
        public ?array $data,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->targetUserId)];
    }

    public function broadcastWith(): array
    {
        return [
            'caller_id' => $this->callerId,
            'session_id' => $this->sessionId,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
