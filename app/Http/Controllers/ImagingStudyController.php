<?php

namespace App\Http\Controllers;

use App\Contracts\PacsClient;
use App\Models\ImagingStudy;
use App\Services\Imaging\ImagingAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImagingStudyController extends Controller
{
    public function __construct(
        private readonly ImagingAuditService $audit,
        private readonly PacsClient $pacs,
    ) {}

    public function index()
    {
        return view('imaging.studies.index');
    }

    public function show(ImagingStudy $imagingStudy)
    {
        $this->authorizeView();

        $this->audit->log(ImagingAuditService::ACTION_VIEW_STUDY, $imagingStudy);

        return view('imaging.studies.show', ['study' => $imagingStudy]);
    }

    /**
     * Goes through the PacsClient contract (StubPacsClient for now) so the
     * only thing that changes when a real PACS exists is the
     * AppServiceProvider binding — this controller doesn't change.
     */
    public function openImages(ImagingStudy $imagingStudy)
    {
        $this->authorizeView();

        $this->audit->log(ImagingAuditService::ACTION_OPEN_IMAGES, $imagingStudy);

        $url = $this->pacs->viewerUrl($imagingStudy);

        if ($url) {
            return redirect()->away($url);
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Open Images requested — logged for audit. PACS viewer is not yet connected.');
    }

    public function exportImages(ImagingStudy $imagingStudy)
    {
        $this->authorizeView();

        $this->audit->log(ImagingAuditService::ACTION_EXPORT_IMAGES, $imagingStudy);

        $exported = $this->pacs->retrieve($imagingStudy);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', $exported
                ? 'Export completed.'
                : 'Export Images requested — logged for audit. PACS export is not yet connected.');
    }

    public function updateChecklist(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeProgress();

        $imagingStudy->updateChecklistResults((array) $request->input('checked', []));

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Checklist updated.');
    }

    public function verifyConsent(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeProgress();

        $validated = $request->validate([
            'consent_notes' => 'nullable|string|max:1000',
        ]);

        $imagingStudy->verifyConsent(Auth::id(), $validated['consent_notes'] ?? null);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Consent verified.');
    }

    public function startPreparation(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markPreparationRequired', 'Preparation started.');
    }

    public function completePreparation(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markPreparationComplete', 'Preparation marked complete.');
    }

    public function readyForStudy(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markReadyForStudy', 'Study marked ready — worklist entry queued.');
    }

    public function start(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markInProgress', 'Study claimed and in progress.', Auth::id());
    }

    public function imageAcquired(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markImageAcquired', 'Images marked as acquired.');
    }

    public function reportPending(ImagingStudy $imagingStudy)
    {
        return $this->transition($imagingStudy, 'markReportPending', 'Sent to the radiologist queue.');
    }

    private function transition(ImagingStudy $imagingStudy, string $method, string $successMessage, ?int $userId = null)
    {
        $this->authorizeProgress();

        try {
            $imagingStudy->{$method}($userId ?? Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', $successMessage);
    }

    private function authorizeView(): void
    {
        abort_unless(in_array('View Imaging Studies', Auth::user()->permissions ?? []), 403);
    }

    private function authorizeProgress(): void
    {
        abort_unless(in_array('Progress Imaging Studies', Auth::user()->permissions ?? []), 403);
    }
}
