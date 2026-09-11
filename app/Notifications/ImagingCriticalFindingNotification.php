<?php

namespace App\Notifications;

use App\Models\ImagingReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ImagingCriticalFindingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $report;

    /**
     * Pillar 6: Critical Findings Engine. Stand-in for the eventual
     * Clinical Module webhook — an in-app notification until that
     * module exists to call back into.
     */
    public function __construct(ImagingReport $report)
    {
        $this->report = $report;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $study = $this->report->imagingStudy;
        $findingLabel = $this->report->resolveCriticalFindingType()?->label;

        return [
            'imaging_report_id' => $this->report->id,
            'imaging_study_id' => $study?->id,
            'accession_number' => $study?->accession_number,
            'client_id' => $study?->client_id,
            'protocol' => $study?->protocol()?->name,
            'critical_finding_label' => $findingLabel,
            'message' => $findingLabel
                ? "Critical finding ({$findingLabel}) flagged on study {$study?->accession_number}."
                : "Critical finding flagged on study {$study?->accession_number}.",
        ];
    }
}
