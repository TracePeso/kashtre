<?php

namespace App\Services\Imaging;

use App\Models\ActivityLog;
use App\Models\ImagingStudy;
use Illuminate\Support\Facades\Auth;

/**
 * Pillar 19: Security & Audit Trail. ModelActivityObserver already covers
 * every CRUD mutation on the Imaging models (registered in
 * AppServiceProvider) with generic created/updated/deleted entries — this
 * service adds semantic, spec-named entries alongside those generic ones,
 * both for actions with no corresponding Eloquent event to hook
 * (viewing/opening/exporting a study) and for named report lifecycle
 * events (CREATE_REPORT/VERIFY_REPORT/AMEND_REPORT) where a richer label
 * than "updated" is useful.
 */
class ImagingAuditService
{
    const ACTION_VIEW_STUDY = 'VIEW_STUDY';
    const ACTION_OPEN_IMAGES = 'OPEN_IMAGES';
    const ACTION_EXPORT_IMAGES = 'EXPORT_IMAGES';
    const ACTION_CREATE_REPORT = 'CREATE_REPORT';
    const ACTION_VERIFY_REPORT = 'VERIFY_REPORT';
    const ACTION_AMEND_REPORT = 'AMEND_REPORT';

    // RIS Amendment v2.6, Chunk 7 — Workflow Engine events. Same
    // generic-plus-semantic idiom as the report actions above:
    // ModelActivityObserver already logs the underlying row changes
    // (ImagingStudyWorkflowExecution/ImagingWorkflowClaim updates), these
    // add a named, purpose-built entry alongside them.
    const ACTION_STEP_STARTED = 'STEP_STARTED';
    const ACTION_STEP_COMPLETED = 'STEP_COMPLETED';
    const ACTION_STUDY_CLAIMED = 'STUDY_CLAIMED';
    const ACTION_STUDY_RELEASED = 'STUDY_RELEASED';
    const ACTION_CONSUMPTION_TRIGGERED = 'CONSUMPTION_TRIGGERED';
    const ACTION_CONSUMPTION_FAILED = 'CONSUMPTION_FAILED';

    public function log(string $action, ImagingStudy $study, array $extra = []): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'business_id' => $user?->business_id,
            'branch_id' => $user?->branch_id,
            'model_type' => ImagingStudy::class,
            'model_id' => $study->id,
            'action' => $action,
            'new_values' => array_merge([
                'accession_number' => $study->accession_number,
                'client_id' => $study->client_id,
            ], $extra),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'description' => ($user?->name ?? 'System')." {$action} on study {$study->accession_number}",
        ]);
    }
}
