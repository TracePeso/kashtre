<?php

namespace App\Http\Controllers\API\Imaging;

use App\Http\Controllers\Controller;
use App\Models\ProtocolWorkflow;
use Illuminate\Http\Request;

/**
 * RIS Amendment v2.6, Chunk 8: /api/v1/imaging/protocol-workflows — lets
 * an external consumer (the eventual Clinical Module) read a protocol's
 * configured step sequence, e.g. to show progress or know what statuses
 * to expect, without duplicating Chunk 2's ManageProtocolWorkflow logic.
 */
class ProtocolWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $workflows = ProtocolWorkflow::query()
            ->with(['steps.workflowStep:id,step_name,step_code'])
            ->when($request->filled('imaging_protocol_id'), fn ($q) => $q->where('imaging_protocol_id', (int) $request->imaging_protocol_id))
            ->when($request->filled('protocol_code'), function ($q) use ($request) {
                $q->whereHas('protocol', fn ($p) => $p->where('code', $request->protocol_code));
            })
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->get()
            ->map(fn (ProtocolWorkflow $workflow) => [
                'id' => $workflow->id,
                'imaging_protocol_id' => $workflow->imaging_protocol_id,
                'workflow_name' => $workflow->workflow_name,
                'workflow_version' => $workflow->workflow_version,
                'is_active' => $workflow->is_active,
                'steps' => $workflow->steps->map(fn ($step) => [
                    'id' => $step->id,
                    'sequence_no' => $step->sequence_no,
                    'imaging_workflow_step_id' => $step->imaging_workflow_step_id,
                    'step_name' => $step->workflowStep?->step_name,
                    'step_code' => $step->workflowStep?->step_code,
                    'ris_status' => $step->ris_status,
                    'main_status' => $step->main_status,
                    'triggers_consumption' => $step->triggers_consumption,
                ]),
            ]);

        return response()->json($workflows);
    }
}
