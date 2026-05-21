<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoResolveEmergencyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $alertId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $alert = \App\Models\EmergencyAlert::find($this->alertId);

        if (!$alert || $alert->resolved_at !== null) {
            return;
        }

        $latest = \App\Models\EmergencyAlert::where('business_id', $alert->business_id)
            ->whereNull('resolved_at')
            ->orderByDesc('scheduled_announce_at')
            ->first();

        // If the latest alert is NOT the one that scheduled this job, then a new emergency happened.
        // Let the new emergency's job handle the auto-resolve.
        if ($latest && $latest->id !== $alert->id) {
            return;
        }

        \App\Models\EmergencyAlert::where('business_id', $alert->business_id)
            ->whereNull('resolved_at')
            ->update([
                'is_active'   => false,
                'resolved_at' => now(),
            ]);

        app(\App\Services\CallingServiceClient::class)->resolveEmergency($alert->business_id);
    }
}
