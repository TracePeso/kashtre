<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaAudioChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $businessId,
        public int    $sectionId,
        public string $chunk,   // base64-encoded audio data
        public bool   $isInit,  // true for the first chunk (stream header)
        public string $mimeType = 'audio/webm',
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('pa-business.' . $this->businessId)];
    }

    public function broadcastWith(): array
    {
        return [
            'section_id' => $this->sectionId,
            'chunk'      => $this->chunk,
            'is_init'    => $this->isInit,
            'mime_type'  => $this->mimeType,
        ];
    }
}
