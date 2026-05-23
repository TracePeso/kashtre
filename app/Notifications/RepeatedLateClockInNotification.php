<?php

namespace App\Notifications;

use App\Models\HrAttendanceLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepeatedLateClockInNotification extends Notification
{
    use Queueable;

    public function __construct(
        public HrAttendanceLedger $ledger,
        public int $lateCount,
        public string $recipientRole = 'employee',
    ) {
        $this->ledger->loadMissing('organization', 'staffAssignment', 'shiftType');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $staffName = $this->ledger->staffAssignment?->staff_name ?? 'A staff member';
        $minutesLate = (int) ($this->ledger->minutes_late ?? 0);
        $organizationName = $this->ledger->organization?->name ?? 'Kashtre HR';

        if ($this->recipientRole === 'manager') {
            return (new MailMessage())
                ->subject("[{$organizationName}] Repeated lateness alert for {$staffName}")
                ->greeting('Hi,')
                ->line("{$staffName} has now recorded {$this->lateCount} late clock-ins.")
                ->line("Latest late punch: {$minutesLate} minute(s) late.")
                ->action('Review attendance', url('/hr/biometrics/attendance'));
        }

        return (new MailMessage())
            ->subject("[{$organizationName}] Repeated lateness warning")
            ->greeting('Hi,')
            ->line("You have now recorded {$this->lateCount} late clock-ins.")
            ->line("Your latest clock-in was {$minutesLate} minute(s) late.")
            ->line('Please review your reporting time and speak with your supervisor if there is an issue affecting attendance.')
            ->action('Open dashboard', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        $staffName = $this->ledger->staffAssignment?->staff_name ?? 'A staff member';
        $minutesLate = (int) ($this->ledger->minutes_late ?? 0);
        $occurredAt = $this->ledger->occurred_at?->toDateTimeString();
        $organizationName = $this->ledger->organization?->name ?? 'Kashtre HR';

        if ($this->recipientRole === 'manager') {
            return [
                'kind' => 'repeated_late_clock_in',
                'recipient_role' => 'manager',
                'organization_id' => $this->ledger->organization_id,
                'organization_name' => $organizationName,
                'staff_assignment_id' => $this->ledger->staff_assignment_id,
                'staff_uuid' => $this->ledger->staff_uuid,
                'staff_name' => $staffName,
                'late_count' => $this->lateCount,
                'minutes_late' => $minutesLate,
                'occurred_at' => $occurredAt,
                'shift_name' => $this->ledger->shiftType?->name,
                'title' => 'Late attendance alert',
                'message' => "{$staffName} has reached {$this->lateCount} late clock-ins. Latest punch was {$minutesLate} minute(s) late.",
                'action_url' => url('/hr/biometrics/attendance'),
                'action_label' => 'Review attendance',
            ];
        }

        return [
            'kind' => 'repeated_late_clock_in',
            'recipient_role' => 'employee',
            'organization_id' => $this->ledger->organization_id,
            'organization_name' => $organizationName,
            'staff_assignment_id' => $this->ledger->staff_assignment_id,
            'staff_uuid' => $this->ledger->staff_uuid,
            'staff_name' => $staffName,
            'late_count' => $this->lateCount,
            'minutes_late' => $minutesLate,
            'occurred_at' => $occurredAt,
            'shift_name' => $this->ledger->shiftType?->name,
            'title' => 'Repeated lateness warning',
            'message' => "You have been flagged after {$this->lateCount} late clock-ins. Your latest punch was {$minutesLate} minute(s) late.",
            'action_url' => url('/dashboard'),
            'action_label' => 'Open dashboard',
        ];
    }
}
