<?php

namespace App\Http\Controllers;

use App\Models\ImagingModuleConfig;
use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Services\Imaging\ImagingAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImagingReportController extends Controller
{
    public function __construct(
        private readonly ImagingAuditService $audit,
    ) {}

    public function saveDraft(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeReport();

        $validated = $request->validate([
            'sections' => 'array',
            'sections.*' => 'nullable|string',
            'is_critical_finding' => 'nullable|boolean',
            'critical_finding_code' => 'nullable|required_if:is_critical_finding,1|string|max:255',
        ]);

        $report = $imagingStudy->reports()->latest()->first();

        if ($report && ! $report->isStatus(ImagingReport::STATUS_DRAFT)) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', 'This report has already been submitted — use Amend instead.');
        }

        $payload = $validated['sections'] ?? [];
        $isCritical = (bool) ($validated['is_critical_finding'] ?? false);
        $criticalFindingCode = $isCritical ? ($validated['critical_finding_code'] ?? null) : null;

        if ($report) {
            $report->update([
                'structured_data_payload' => $payload,
                'is_critical_finding' => $isCritical,
                'critical_finding_code' => $criticalFindingCode,
            ]);
        } else {
            $newReport = $imagingStudy->reports()->create([
                'author_user_id' => Auth::id(),
                'structured_data_payload' => $payload,
                'is_critical_finding' => $isCritical,
                'critical_finding_code' => $criticalFindingCode,
            ]);

            $this->audit->log(ImagingAuditService::ACTION_CREATE_REPORT, $imagingStudy, [
                'imaging_report_id' => $newReport->id,
            ]);
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Draft saved.');
    }

    public function submit(ImagingStudy $imagingStudy)
    {
        $this->authorizeReport();

        $report = $imagingStudy->reports()->latest()->first();

        if (! $report) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', 'No draft report to submit yet.');
        }

        try {
            $report->markReported(Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Report submitted.');
    }

    public function verify(ImagingStudy $imagingStudy)
    {
        $this->authorizeVerify($imagingStudy);

        $report = $imagingStudy->reports()->latest()->first();

        if (! $report) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', 'No report to verify.');
        }

        try {
            $report->markVerified(Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        $this->audit->log(ImagingAuditService::ACTION_VERIFY_REPORT, $imagingStudy, [
            'imaging_report_id' => $report->id,
        ]);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Report verified.');
    }

    public function amend(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeVerify($imagingStudy);

        $validated = $request->validate([
            'sections' => 'array',
            'sections.*' => 'nullable|string',
            'justification' => 'required|string|max:1000',
        ]);

        $report = $imagingStudy->reports()->latest()->first();

        if (! $report) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', 'No report to amend.');
        }

        try {
            $report->markAmended(Auth::id(), $validated['justification'], $validated['sections'] ?? []);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        $this->audit->log(ImagingAuditService::ACTION_AMEND_REPORT, $imagingStudy, [
            'imaging_report_id' => $report->id,
            'justification' => $validated['justification'],
        ]);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Amendment recorded.');
    }

    private function authorizeReport(): void
    {
        abort_unless(in_array('Report Imaging Studies', Auth::user()->permissions ?? []), 403);
    }

    /**
     * Holding the permission string is necessary but not sufficient — the
     * user must also be in the business's configured radiologist pool
     * (same ImagingModuleConfig::isEligibleReviewer() pool already used to
     * gate Peer Review Case completion), so a general staff account with
     * the permission flag set can't sign off reports it isn't qualified to.
     * Empty/unset pool = every permission-holder eligible, same as today.
     */
    private function authorizeVerify(ImagingStudy $imagingStudy): void
    {
        abort_unless(in_array('Verify Imaging Reports', Auth::user()->permissions ?? []), 403);
        abort_unless(ImagingModuleConfig::isEligibleReviewer($imagingStudy->business_id, Auth::id()), 403);
    }
}
