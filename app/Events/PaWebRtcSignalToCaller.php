<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaWebRtcSignalToCaller implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $callerId,
        public string $sessionId,
        public string $type,
        public ?array $data,
        public int $sectionId,
        public string $sectionName,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel('pa-caller.' . $this->callerId)];
    }

    public function broadcastWith(): array
    {
        return [
            'caller_id' => $this->callerId,
            'session_id' => $this->sessionId,
            'type' => $this->type,
            'data' => $this->data,
            'section_id' => $this->sectionId,
            'section_name' => $this->sectionName,
        ];
    }
}
