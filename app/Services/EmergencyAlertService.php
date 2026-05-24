<?php

namespace App\Services;

use App\Jobs\AutoResolveEmergencyJob;
use App\Models\CallingModuleConfig;
use App\Models\EmergencyAlert;
use Carbon\Carbon;

class EmergencyAlertService
{
    public function resolveActiveAlertForBusiness(int $businessId): ?EmergencyAlert
    {
        $config = CallingModuleConfig::where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return null;
        }

        $alert = EmergencyAlert::where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if ($alert) {
            if (!$this->hasExpired($alert, $config)) {
                return $alert;
            }

            // Display time is up â€” deactivate only this alert so any queued
            // alerts can be promoted once their scheduled time is reached.
            $alert->update([
                'is_active'   => false,
                'resolved_at' => now(),
            ]);
        }

        $next = EmergencyAlert::where('business_id', $businessId)
            ->whereNull('resolved_at')
            ->where('is_active', false)
            ->where('scheduled_announce_at', '<=', now())
            ->orderBy('scheduled_announce_at')
            ->first();

        if ($next) {
            $next->update([
                'is_active'    => true,
                'activated_at' => now(),
            ]);

            // Keep audio in sync with the alert that actually becomes active.
            app(CallingServiceClient::class)->syncEmergency($next);

            return $next;
        }

        $hasQueued = EmergencyAlert::where('business_id', $businessId)
            ->whereNull('resolved_at')
            ->exists();

        if (!$hasQueued) {
            app(CallingServiceClient::class)->resolveEmergency($businessId);
        }

        return null;
    }

    public function scheduleAutoResolve(EmergencyAlert $alert, CallingModuleConfig $config): void
    {
        $resolveDelay = max(
            (int) ($config->emergency_display_duration ?? 0),
            $this->estimateAnnounceDuration($alert, $config)
        );

        if ($resolveDelay <= 0) {
            return;
        }

        if (config('queue.default') === 'sync') {
            return;
        }

        $resolveAt = Carbon::parse($alert->scheduled_announce_at)->addSeconds($resolveDelay);
        AutoResolveEmergencyJob::dispatch($alert->id)->delay($resolveAt);
    }

    public function estimateAnnounceDuration(EmergencyAlert $alert, CallingModuleConfig $config): int
    {
        $repeatCount = max(1, (int) ($config->emergency_repeat_count ?? 1));
        $repeatInterval = max(0, (int) ($config->emergency_repeat_interval ?? 5));
        $ttsSpeed = max(0.5, (float) ($config->emergency_tts_speed ?? $config->tts_speed ?? 1.0));
        $audioSeconds = (int) ceil(strlen($alert->message) / (3 * $ttsSpeed));
        $audioSeconds = max(5, $audioSeconds);

        return $audioSeconds * $repeatCount + $repeatInterval * max(0, $repeatCount - 1);
    }

    private function hasExpired(EmergencyAlert $alert, CallingModuleConfig $config): bool
    {
        $displayDuration = (int) ($config->emergency_display_duration ?? 0);
        $resolveDelay = max($displayDuration, $this->estimateAnnounceDuration($alert, $config));

        if ($resolveDelay <= 0) {
            return false;
        }

        $startedAt = $alert->scheduled_announce_at ?? $alert->activated_at ?? $alert->triggered_at;

        if (!$startedAt) {
            return false;
        }

        return Carbon::now()->greaterThanOrEqualTo(Carbon::parse($startedAt)->addSeconds($resolveDelay));
    }
}
