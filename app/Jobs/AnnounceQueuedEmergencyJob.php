<?php

namespace App\Jobs;

use App\Models\EmergencyAlert;
use App\Services\CallingServiceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnnounceQueuedEmergencyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $alertId) {}

    public function handle(): void
    {
        $alert = EmergencyAlert::find($this->alertId);

        // Skip if the alert was resolved while waiting in the queue
        if (!$alert || $alert->resolved_at !== null) {
            return;
        }

        // Deactivate any currently announcing alert for this business
        EmergencyAlert::where('business_id', $alert->business_id)
            ->where('is_active', true)
            ->update([
                'is_active'   => false,
                'resolved_at' => now(),
            ]);

        $alert->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);

        app(CallingServiceClient::class)->syncEmergency($alert);

        $config = \App\Models\CallingModuleConfig::where('business_id', $alert->business_id)->first();
        if ($config) {
            app(\App\Services\EmergencyAlertService::class)->scheduleAutoResolve($alert, $config);
        }
    }
}
