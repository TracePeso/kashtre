<?php

// app/Services/OrthancClient.php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Authenticated client for Orthanc's REST API.
 *
 * Worklists are created and removed via the Worklists plugin's REST API
 * (POST /worklists/create, DELETE /worklists/{id}) — the plugin converts the JSON
 * tags into a DICOM worklist internally, so no DCMTK / dump2dcm is needed.
 */
class OrthancClient
{
    /**
     * Fetch a study's metadata by Orthanc id (MainDicomTags, PatientMainDicomTags, Series...).
     *
     * @return array<string, mixed>
     */
    public function study(string $orthancStudyId): array
    {
        $response = $this->http()->get("/studies/{$orthancStudyId}");
        $response->throw();

        return $response->json();
    }

    /**
     * Create a modality worklist. $tags uses DICOM keyword names, e.g.
     * ['AccessionNumber' => ..., 'PatientName' => 'Doe^John',
     *  'ScheduledProcedureStepSequence' => [['Modality' => 'CT', ...]]].
     *
     * @param array<string, mixed> $tags
     * @return string The Orthanc worklist id (store it to delete the entry later).
     */
    public function createWorklist(array $tags): string
    {
        $response = $this->http()->post('/worklists/create', ['Tags' => $tags]);
        $response->throw();

        return (string) $response->json('ID');
    }

    /**
     * Delete a worklist. A 404 is treated as success (it may already have been removed,
     * e.g. by Orthanc's DeleteWorklistsOnStableStudy housekeeper).
     */
    public function deleteWorklist(string $worklistId): void
    {
        $response = $this->http()->delete("/worklists/{$worklistId}");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    private function http(): PendingRequest
    {
        $config = (array) config('services.orthanc');

        $request = Http::baseUrl(rtrim((string) $config['url'], '/'))
            ->timeout(10)
            ->acceptJson();

        if (!blank($config['username'] ?? null)) {
            $request = $request->withBasicAuth(
                (string) $config['username'],
                (string) ($config['password'] ?? '')
            );
        }

        return $request;
    }
}
