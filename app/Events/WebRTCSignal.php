<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRTCSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $callUuid,
        public int    $targetUserId,
        public string $type,   // 'offer', 'answer', 'candidate', 'audio_chunk'
        public array  $data,
        public ?int   $signalId = null,
    ) {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WebRTCSignal';
    }

    public function broadcastWith(): array
    {
        return [
            'signal_id' => $this->signalId,
            'call_id' => $this->callUuid,
            'type'    => $this->type,
            'data'    => $this->data,
        ];
    }
}
