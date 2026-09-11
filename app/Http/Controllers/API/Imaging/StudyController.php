<?php

namespace App\Http\Controllers\API\Imaging;

use App\Http\Controllers\Controller;
use App\Models\ImagingStudy;
use App\Services\Imaging\WorkflowOwnershipService;
use Illuminate\Http\Request;

/**
 * RIS Amendment v2.6, Chunk 8: /api/v1/imaging/studies/{id}/claim and
 * /complete-step. There's no Auth::user() session in an API request, so
 * every action takes an explicit user_id — exactly what
 * WorkflowOwnershipService/ImagingStudy's mark*() methods already accept,
 * per the "API-ready services" principle this whole amendment was built
 * against. Nothing here is new business logic — it's the same guarded
 * transitions the web study page uses.
 */
class StudyController extends Controller
{
    /**
     * The 9 standard RIS statuses, mapped to the ImagingStudy method that
     * enforces their status-sequence guard and (for the two that have one)
     * CompletionRuleService validation — exactly what the web study page's
     * transition buttons call. A custom, non-standard workflow step (an
     * admin-configured ris_status outside this list) has no such guarded
     * method to dispatch to yet, so it's explicitly rejected below rather
     * than silently completed via the raw, unvalidated WorkflowEngineService
     * call — a real gap to close once a real custom-status use case exists,
     * not guessed at here.
     */
    private const STATUS_METHODS = [
        ImagingStudy::STATUS_PREPARATION_REQUIRED => 'markPreparationRequired',
        ImagingStudy::STATUS_PREPARATION_COMPLETE => 'markPreparationComplete',
        ImagingStudy::STATUS_READY_FOR_STUDY => 'markReadyForStudy',
        ImagingStudy::STATUS_IN_PROGRESS => 'markInProgress',
        ImagingStudy::STATUS_IMAGE_ACQUIRED => 'markImageAcquired',
        ImagingStudy::STATUS_REPORT_PENDING => 'markReportPending',
        ImagingStudy::STATUS_REPORTED => 'markReported',
        ImagingStudy::STATUS_VERIFIED => 'markVerified',
    ];

    private const METHODS_ACCEPTING_OVERRIDE = ['markPreparationComplete', 'markReadyForStudy'];

    public function claim(ImagingStudy $study, Request $request, WorkflowOwnershipService $ownership)
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $claim = $ownership->claimStudy($study, (int) $data['user_id']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($claim);
    }

    public function completeStep(ImagingStudy $study, Request $request)
    {
        $data = $request->validate([
            'target_ris_status' => 'required|string',
            'user_id' => 'nullable|integer',
            'override_reason' => 'nullable|string',
        ]);

        $targetRisStatus = strtoupper($data['target_ris_status']);
        $method = self::STATUS_METHODS[$targetRisStatus] ?? null;

        if (! $method) {
            return response()->json([
                'error' => "RIS status [{$targetRisStatus}] has no corresponding transition method — custom, non-standard workflow steps aren't supported via this endpoint yet.",
            ], 422);
        }

        $args = [$data['user_id'] ?? null];

        if (in_array($method, self::METHODS_ACCEPTING_OVERRIDE, true)) {
            $args[] = $data['override_reason'] ?? null;
        }

        try {
            $study->{$method}(...$args);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $study->refresh();

        return response()->json([
            'id' => $study->id,
            'accession_number' => $study->accession_number,
            'status' => $study->status,
            'main_module_status' => $study->main_module_status,
        ]);
    }
}
