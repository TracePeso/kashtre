<?php

namespace App\Http\Controllers;

use App\Events\StudyImagesAcquired;
use App\Models\ImagingStudy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Orthanc's OnStableStudy Lua callback (pacs integration files/stable-study.lua)
 * once a study's images have settled. Drives the study through its real
 * status-machine transitions (markInProgress()/markImageAcquired()) rather
 * than writing the status column directly, so status_history logging and
 * the per-protocol consumption trigger (ImagingStudy::triggerConsumptionIfDue())
 * still fire exactly as they do for a manually-progressed study.
 *
 * Deliberately makes zero outbound HTTP calls back to Orthanc. Orthanc's Lua
 * engine blocks synchronously on its own HttpPost() call until this handler
 * responds — an earlier version re-fetched tags via OrthancClient::study()
 * and cleaned up the worklist via deleteWorklist() from inside this same
 * request, both of which reproducibly deadlocked (confirmed via a real
 * end-to-end test: the reentrant GET timed out at exactly our client's 10s
 * ceiling with zero bytes received, because Orthanc couldn't service it
 * while still inside the Lua callback that originated the whole chain).
 * The shared-secret check below is already the trust boundary for this
 * payload, so a second round-trip to re-verify it added no real security —
 * only the deadlock. Worklist cleanup is likewise redundant: Orthanc's own
 * `DeleteWorklistsOnStableStudy` housekeeper already removes the entry
 * (confirmed in Orthanc's own log) independently of this webhook.
 */
class OrthancWebhookController extends Controller
{
    public function stableStudy(Request $request): JsonResponse
    {
        // 1. Reject anything without the shared secret (constant-time compare).
        // Orthanc's Lua HttpPost() doesn't reliably set a Content-Type:
        // application/json header, so $request->input() (which only reads
        // the JSON body when that header is present) can't be trusted here
        // — $request->json() decodes the raw body unconditionally instead.
        $expected = (string) config('services.orthanc.webhook_secret');
        $provided = (string) $request->json('secret', $request->input('secret', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401);
        }

        $orthancStudyId = (string) $request->json('orthancStudyId', $request->input('orthancStudyId', ''));
        $accession = (string) $request->json('accessionNumber', $request->input('accessionNumber', ''));
        $studyUid = (string) $request->json('studyInstanceUid', $request->input('studyInstanceUid', ''));

        if ($orthancStudyId === '') {
            return response()->json(['error' => 'missing orthancStudyId'], 422);
        }

        if (blank($accession)) {
            Log::warning("StableStudy: Orthanc study {$orthancStudyId} has no AccessionNumber; needs reconciliation.");

            return response()->json(['status' => 'unmatched_no_accession'], 200);
        }

        // 2. Match + advance atomically under a row lock, via the model's
        // own transitions (not a raw status write).
        $result = DB::transaction(function () use ($accession, $studyUid, $orthancStudyId) {
            $study = ImagingStudy::where('accession_number', $accession)
                ->lockForUpdate()
                ->first();

            if ($study === null) {
                Log::warning("StableStudy: no imaging_studies row for accession {$accession}.");

                return ['study' => null, 'advanced' => false];
            }

            if (! blank($study->study_instance_uid) && ! blank($studyUid)
                && $study->study_instance_uid !== $studyUid) {
                Log::notice("StableStudy: accession {$accession} UID mismatch (modality generated its own). Matching on accession.");
            }

            $study->orthanc_study_id = $orthancStudyId;
            if (blank($study->study_instance_uid) && ! blank($studyUid)) {
                $study->study_instance_uid = $studyUid;
            }
            // Orthanc's own housekeeper deletes the worklist entry once the
            // study is stable — we just stop tracking it locally.
            $study->orthanc_worklist_id = null;
            $study->save();

            $advanced = false;

            if ($study->isStatus(ImagingStudy::STATUS_READY_FOR_STUDY)) {
                $study->markInProgress(null);
            }

            if ($study->isStatus(ImagingStudy::STATUS_IN_PROGRESS)) {
                $study->markImageAcquired(null);
                $advanced = true;
            }

            return ['study' => $study, 'advanced' => $advanced];
        });

        // 3. Side effects only on the first advance, after commit.
        if ($result['advanced'] && $result['study'] !== null) {
            StudyImagesAcquired::dispatch($result['study']->id);
        }

        return response()->json([
            'status' => $result['advanced'] ? 'advanced' : 'noop',
            'accession' => $accession,
        ], 200);
    }
}
