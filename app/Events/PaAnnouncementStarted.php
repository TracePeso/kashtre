<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaAnnouncementStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $businessId,
        public int    $sectionId,
        public string $sectionName,
        public string $announcerName,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('pa-business.' . $this->businessId)];
    }

    public function broadcastWith(): array
    {
        return [
            'section_id'    => $this->sectionId,
            'section_name'  => $this->sectionName,
            'announcer'     => $this->announcerName,
        ];
    }
}
