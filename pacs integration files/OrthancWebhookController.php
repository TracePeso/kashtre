<?php

// app/Http/Controllers/OrthancWebhookController.php

namespace App\Http\Controllers;

use App\Events\StudyImagesAcquired;
use App\Models\ImagingStudy;
use App\Services\OrthancClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrthancWebhookController extends Controller
{
    /**
     * Statuses from which a StableStudy event may advance a study. Anything at or past
     * IMAGE_ACQUIRED is left untouched (idempotency for late re-fires).
     */
    private const PRE_ACQUISITION = ['READY_FOR_STUDY', 'IN_PROGRESS'];

    public function stableStudy(Request $request, OrthancClient $orthanc): JsonResponse
    {
        // 1. Reject anything without the shared secret (constant-time compare).
        $expected = (string) config('services.orthanc.webhook_secret');
        $provided = (string) $request->input('secret', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(401);
        }

        $orthancStudyId = (string) $request->input('orthancStudyId', '');
        if ($orthancStudyId === '') {
            return response()->json(['error' => 'missing orthancStudyId'], 422);
        }

        // 2. Trust Orthanc, not the POST body — re-fetch authoritative tags.
        try {
            $resource  = $orthanc->study($orthancStudyId);
            $accession = data_get($resource, 'MainDicomTags.AccessionNumber');
            $studyUid  = data_get($resource, 'MainDicomTags.StudyInstanceUID');
        } catch (Throwable $e) {
            Log::error("StableStudy: Orthanc unreachable for study {$orthancStudyId}: {$e->getMessage()}");
            return response()->json(['error' => 'orthanc unreachable'], 502); // allow retry
        }

        if (blank($accession)) {
            Log::warning("StableStudy: Orthanc study {$orthancStudyId} has no AccessionNumber; needs reconciliation.");
            return response()->json(['status' => 'unmatched_no_accession'], 200);
        }

        // 3. Match + advance atomically under a row lock.
        $result = DB::transaction(function () use ($accession, $studyUid, $orthancStudyId) {
            $study = ImagingStudy::where('accession_number', $accession)
                ->lockForUpdate()
                ->first();

            if ($study === null) {
                Log::warning("StableStudy: no imaging_studies row for accession {$accession}.");
                return ['study' => null, 'advanced' => false];
            }

            if (!blank($study->study_instance_uid) && !blank($studyUid)
                && $study->study_instance_uid !== $studyUid) {
                Log::notice("StableStudy: accession {$accession} UID mismatch (modality generated its own). Matching on accession.");
            }

            $study->orthanc_study_id = $orthancStudyId;
            if (blank($study->study_instance_uid) && !blank($studyUid)) {
                $study->study_instance_uid = $studyUid;
            }

            $advanced = false;
            if (in_array($study->status, self::PRE_ACQUISITION, true)) {
                $study->status = 'IMAGE_ACQUIRED';
                $advanced = true;
            }

            $study->save();

            return ['study' => $study, 'advanced' => $advanced];
        });

        // 4. Side effects only on the first advance, after commit.
        if ($result['advanced'] && $result['study'] !== null) {
            $study = $result['study'];

            // Belt-and-suspenders: Orthanc's DeleteWorklistsOnStableStudy may already have
            // removed it; deleteWorklist() treats a 404 as success.
            if (!blank($study->orthanc_worklist_id)) {
                try {
                    $orthanc->deleteWorklist($study->orthanc_worklist_id);
                } catch (Throwable $e) {
                    Log::warning("StableStudy: could not delete worklist {$study->orthanc_worklist_id}: {$e->getMessage()}");
                }
                $study->orthanc_worklist_id = null;
                $study->save();
            }

            StudyImagesAcquired::dispatch($study->id); // dose-SR read, inventory depletion, etc.
        }

        return response()->json([
            'status'    => $result['advanced'] ? 'advanced' : 'noop',
            'accession' => $accession,
        ], 200);
    }
}
