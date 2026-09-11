<?php

namespace App\Http\Controllers\API\Imaging;

use App\Http\Controllers\Controller;
use App\Models\ImagingStudy;
use App\Models\ImagingStudyWorkflowExecution;
use App\Models\ImagingWorkflowStep;
use Illuminate\Http\Request;

/**
 * RIS Amendment v2.6, Chunk 8: /api/v1/imaging/workflow-steps — thin
 * controllers over Chunk 1's registry and Chunk 4's per-step queue
 * concept. No new logic: everything here is a direct query, the same
 * shape ListImagingWorkflowSteps/ListMyImagingQueue already build for the
 * web UI.
 */
class WorkflowStepController extends Controller
{
    public function index(Request $request)
    {
        $steps = ImagingWorkflowStep::query()
            ->when($request->filled('business_id'), fn ($q) => $q->availableToBusiness((int) $request->business_id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('step_name')
            ->get(['id', 'business_id', 'step_code', 'step_name', 'description', 'is_active']);

        return response()->json($steps);
    }

    public function users(ImagingWorkflowStep $workflowStep)
    {
        $users = $workflowStep->users()->get(['users.id', 'users.name', 'users.email', 'users.business_id']);

        return response()->json($users);
    }

    /**
     * Studies currently sitting at this shared step, across every protocol
     * workflow that uses it — the same "step is a queue" model My Queue
     * (Chunk 4) presents in the web UI, exposed here for an external
     * consumer to poll instead.
     */
    public function queue(ImagingWorkflowStep $workflowStep, Request $request)
    {
        $studies = ImagingStudy::query()
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', (int) $request->business_id))
            ->whereHas('workflowExecutions', function ($exec) use ($workflowStep) {
                $exec->where('status', ImagingStudyWorkflowExecution::STATUS_ACTIVE)
                    ->whereHas('currentStep', fn ($step) => $step->where('imaging_workflow_step_id', $workflowStep->id));
            })
            ->when($request->filled('unclaimed_only') && $request->boolean('unclaimed_only'), function ($q) {
                $q->whereDoesntHave('workflowExecutions.claims', fn ($c) => $c->active());
            })
            ->get(['id', 'accession_number', 'business_id', 'client_id', 'protocol_code', 'status', 'priority', 'created_at']);

        return response()->json($studies);
    }
}
