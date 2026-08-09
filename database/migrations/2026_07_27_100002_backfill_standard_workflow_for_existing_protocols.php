<?php

use App\Models\ImagingProtocol;
use App\Models\ImagingWorkflowStep;
use App\Models\ProtocolWorkflow;
use App\Models\ProtocolWorkflowStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 9 statuses ImagingStudy's hardcoded machine already enforces,
     * given generic (protocol-agnostic) step names — "Scan Execution",
     * "Reporting", and "Certification" borrowed from the SRD's own
     * vocabulary where they cleanly correspond to IN_PROGRESS/REPORTED/
     * VERIFIED; the rest named to match this app's existing status labels
     * so the standard workflow reads as a direct translation of what's
     * already there, not a new invention.
     *
     * main_status is a deliberately coarse 3-bucket default (everything
     * before active scanning = PENDING, active scanning through report
     * drafting = IN_PROGRESS, signed-off report states = COMPLETED) —
     * editable per protocol via the Configure Workflow admin page if a
     * facility wants a different split.
     */
    private const STANDARD_STEPS = [
        ['code' => 'ORDER_RECEIVED', 'name' => 'Order Received', 'ris_status' => 'ORDER_RECEIVED', 'main_status' => 'PENDING'],
        ['code' => 'PREPARATION_REQUIRED', 'name' => 'Preparation Required', 'ris_status' => 'PREPARATION_REQUIRED', 'main_status' => 'PENDING'],
        ['code' => 'PREPARATION_COMPLETE', 'name' => 'Preparation Complete', 'ris_status' => 'PREPARATION_COMPLETE', 'main_status' => 'PENDING'],
        ['code' => 'READY_FOR_STUDY', 'name' => 'Ready For Study', 'ris_status' => 'READY_FOR_STUDY', 'main_status' => 'PENDING'],
        ['code' => 'SCAN_EXECUTION', 'name' => 'Scan Execution', 'ris_status' => 'IN_PROGRESS', 'main_status' => 'IN_PROGRESS'],
        ['code' => 'IMAGE_ACQUIRED', 'name' => 'Image Acquired', 'ris_status' => 'IMAGE_ACQUIRED', 'main_status' => 'IN_PROGRESS'],
        ['code' => 'REPORT_PENDING', 'name' => 'Report Pending', 'ris_status' => 'REPORT_PENDING', 'main_status' => 'IN_PROGRESS'],
        ['code' => 'REPORTING', 'name' => 'Reporting', 'ris_status' => 'REPORTED', 'main_status' => 'COMPLETED'],
        ['code' => 'CERTIFICATION', 'name' => 'Certification', 'ris_status' => 'VERIFIED', 'main_status' => 'COMPLETED'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            // System-wide (business_id null) shared steps, one insert per
            // standard status — every protocol's backfilled workflow
            // references these same 9 rows rather than duplicating them,
            // satisfying the "steps are reusable, no duplication" rule.
            $stepIdsByRisStatus = [];

            foreach (self::STANDARD_STEPS as $standard) {
                $step = ImagingWorkflowStep::create([
                    'business_id' => null,
                    'step_code' => $standard['code'],
                    'step_name' => $standard['name'],
                    'description' => "Standard workflow step, auto-generated from the pre-v2.6 hardcoded {$standard['ris_status']} status.",
                    'is_active' => true,
                ]);

                $stepIdsByRisStatus[$standard['ris_status']] = $step->id;
            }

            foreach (ImagingProtocol::all() as $protocol) {
                $workflow = ProtocolWorkflow::create([
                    'imaging_protocol_id' => $protocol->id,
                    'workflow_name' => 'Standard Workflow',
                    'workflow_version' => 1,
                    'is_active' => true,
                ]);

                foreach (self::STANDARD_STEPS as $sequenceNo => $standard) {
                    ProtocolWorkflowStep::create([
                        'imaging_protocol_workflow_id' => $workflow->id,
                        'imaging_workflow_step_id' => $stepIdsByRisStatus[$standard['ris_status']],
                        'sequence_no' => $sequenceNo + 1,
                        'ris_status' => $standard['ris_status'],
                        'main_status' => $standard['main_status'],
                        // consumption_trigger_status has 4 possible values
                        // today, but RECOVERY_COMPLETE isn't one of the 9
                        // study statuses (it's RecoveryRecord's own event,
                        // handled separately) — no standard step can match
                        // it, so a protocol configured that way simply gets
                        // no step flagged here. Chunk 6 wires this flag to
                        // actually fire consumption; until then it's
                        // composed data only.
                        'triggers_consumption' => $standard['ris_status'] === $protocol->consumption_trigger_status,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            ProtocolWorkflowStep::whereHas('protocolWorkflow', function ($q) {
                $q->where('workflow_name', 'Standard Workflow')->where('workflow_version', 1);
            })->delete();

            ProtocolWorkflow::where('workflow_name', 'Standard Workflow')
                ->where('workflow_version', 1)
                ->delete();

            ImagingWorkflowStep::whereIn(
                'step_code',
                array_column(self::STANDARD_STEPS, 'code')
            )->whereNull('business_id')->delete();
        });
    }
};
