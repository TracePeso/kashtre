<?php

namespace App\Observers;

use App\Contracts\ModuleDispatcher;
use App\Models\ImagingModuleConfig;
use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Models\PeerReviewCase;
use App\Models\User;
use App\Notifications\ImagingCriticalFindingNotification;
use App\Services\Clinical\Facts\ImagingCriticalFindingFact;
use App\Services\Clinical\Facts\ImagingReportValidatedFact;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImagingReportObserver
{
    /**
     * Drives the parent ImagingStudy's status machine from the report's own
     * lifecycle (Pillars 4/5), fires the Pillar 6 critical-finding alert on
     * first submission, and rolls the Pillar 5.1 randomized peer-review
     * audit on verification.
     */
    public function updated(ImagingReport $report): void
    {
        if (! $report->wasChanged('status')) {
            return;
        }

        $study = $report->imagingStudy;

        if (! $study) {
            return;
        }

        match ($report->status) {
            ImagingReport::STATUS_REPORTED => $this->handleReported($report, $study),
            ImagingReport::STATUS_VERIFIED => $this->handleVerified($report, $study),
            default => null,
        };
    }

    private function handleReported(ImagingReport $report, ImagingStudy $study): void
    {
        $this->safeTransition($study, 'markReported');

        if ($report->is_critical_finding) {
            $this->alertCriticalFinding($report, $study);
        }
    }

    private function handleVerified(ImagingReport $report, ImagingStudy $study): void
    {
        $this->safeTransition($study, 'markVerified');
        $this->rollPeerReview($report, $study);

        app(ModuleDispatcher::class)->dispatch(new ImagingReportValidatedFact(
            businessId: $study->business_id,
            branchId: $study->branch_id,
            clientId: $study->client_id,
            visitId: $study->visit_id,
            imagingOrderId: $study->imaging_order_id,
            protocolCode: $study->protocol_code,
            structuredDataPayload: $report->structured_data_payload ?? [],
            verifiedByUserId: $report->verified_by_user_id,
        ));
    }

    private function safeTransition($study, string $method): void
    {
        try {
            $study->{$method}(Auth::id());
        } catch (\RuntimeException $e) {
            // Study already progressed via another path (e.g. re-verifying
            // an amended report — the study reached VERIFIED once already).
            // The report's own status is authoritative for the reporting
            // lifecycle regardless, so this is not fatal.
            Log::info('ImagingReportObserver: study transition skipped — '.$e->getMessage());
        }
    }

    /**
     * Pillar 6: no Clinical Module to call back to yet, so this notifies
     * the ordering clinician plus anyone holding the alert-recipient
     * permission — see Notifications\ImagingCriticalFindingNotification.
     */
    private function alertCriticalFinding(ImagingReport $report, ImagingStudy $study): void
    {
        $recipients = User::where('business_id', $study->business_id)
            ->get()
            ->filter(fn (User $user) => in_array('Receive Critical Imaging Alerts', $user->permissions ?? []))
            ->keyBy('id');

        $orderingUser = $study->resolveOrderingUser();

        if ($orderingUser) {
            $recipients->put($orderingUser->id, $orderingUser);
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new ImagingCriticalFindingNotification($report));
        }

        app(ModuleDispatcher::class)->dispatch(new ImagingCriticalFindingFact(
            businessId: $study->business_id,
            branchId: $study->branch_id,
            clientId: $study->client_id,
            visitId: $study->visit_id,
            imagingOrderId: $study->imaging_order_id,
            protocolCode: $study->protocol_code,
            findingCode: $report->critical_finding_code ?? 'UNSPECIFIED',
            reportingRadiologistId: $report->author_user_id,
        ));
    }

    private function rollPeerReview(ImagingReport $report, ImagingStudy $study): void
    {
        if (! ImagingModuleConfig::isModalityEligibleForPeerReview($study->business_id, $study->modality_type)) {
            return;
        }

        $rate = ImagingModuleConfig::effectivePeerReviewRate($study->business_id);

        if (! PeerReviewCase::shouldTrigger($rate, random_int(1, 100))) {
            return;
        }

        PeerReviewCase::create([
            'imaging_study_id' => $study->id,
            'imaging_report_id' => $report->id,
            'original_author_user_id' => $report->author_user_id,
            'qa_status' => PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ,
        ]);
    }
}
