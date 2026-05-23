<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrphanedStaffDetected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public array $tierSummary,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $staffCount = collect($this->tierSummary['staff'] ?? [])->count();
        $unitName = $this->tierSummary['unit_name'] ?? 'Unassigned pool';

        $mail = (new MailMessage())
            ->subject("[{$this->organization->name}] Orphaned staff at {$unitName}")
            ->greeting('Hi,')
            ->line("{$staffCount} staff member(s) are stuck mid-hierarchy at {$unitName} and have been flagged as orphaned.")
            ->line('Please complete their routing so they can reach a final client space.');

        foreach ($this->tierSummary['staff'] ?? [] as $staff) {
            $label = trim($staff['name'] ?? 'Unknown');
            $details = collect([$staff['title'] ?? null, $staff['department'] ?? null, $staff['cadre'] ?? null])
                ->filter()
                ->implode(' / ');

            if ($details !== '') {
                $label .= " ({$details})";
            }

            $mail->line('- ' . $label);
        }

        return $mail->action('Open tier assignments', url('/hr/tier-staff-assignments'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'unit_id' => $this->tierSummary['unit_id'] ?? null,
            'unit_name' => $this->tierSummary['unit_name'] ?? null,
            'staff' => $this->tierSummary['staff'] ?? [],
        ];
    }
}
