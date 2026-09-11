# Orthanc Modality Worklist + StableStudy integration

Real, runnable wiring for the KashTre Imaging Module (RIS) ⇄ Orthanc (PACS).

Two halves:

1. **Outbound (RIS → Orthanc worklist).** When a study hits `READY_FOR_STUDY`, the
   RIS generates a valid DICOM worklist file (`.wl`) and drops it in Orthanc's
   worklist database directory. The modality queries Orthanc via **C-FIND** (the
   modality is the client — the RIS never pushes C-FIND) and populates its console
   with zero keystrokes.

2. **Inbound (Orthanc → RIS).** When the acquired study settles in Orthanc, Orthanc's
   built-in Lua callback `OnStableStudy` POSTs to a Laravel webhook. The webhook
   re-fetches authoritative tags from Orthanc's REST API, matches on
   **AccessionNumber**, advances the study to `IMAGE_ACQUIRED` idempotently, records
   the Orthanc study id, and removes the now-consumed `.wl` file.

```
READY_FOR_STUDY ──write .wl──▶ Orthanc worklist SCP ──C-FIND──▶ modality
                                                                    │
                                                              scan + C-STORE
                                                                    ▼
IMAGE_ACQUIRED ◀──webhook──── OnStableStudy (Lua) ◀── study stable in Orthanc
```

---

## 0. Prerequisites

Install DCMTK on the box that runs the Laravel queue worker (it needs `dump2dcm`):

```bash
sudo apt-get update && sudo apt-get install -y dcmtk
which dump2dcm   # confirm it's on PATH, or set DCMTK_DUMP2DCM to the absolute path
```

PHP extension: this uses `ext-gmp` (falls back to `ext-bcmath`) to build DICOM UIDs.
Enable one of them:

```bash
sudo apt-get install -y php-gmp   # or php-bcmath
```

The queue worker process must have **write access** to Orthanc's worklist directory
(see step 3). If Orthanc runs in Docker, bind-mount that directory to a host path the
worker can write to.

---

## 1. Database changes

The RIS blueprint's `imaging_studies` table has no place to store the DICOM
`StudyInstanceUID` or the Orthanc study id, and `tenant_procedure_protocols` has no
`modality` — all three are required for MWL. Add them:

```php
// database/migrations/xxxx_add_pacs_linkage_to_imaging.php
public function up(): void
{
    Schema::table('imaging_studies', function (Blueprint $t) {
        $t->string('study_instance_uid', 64)->nullable()->unique()->after('accession_number');
        $t->string('orthanc_study_id', 64)->nullable()->after('study_instance_uid');
    });

    Schema::table('tenant_procedure_protocols', function (Blueprint $t) {
        // DICOM Modality code (0008,0060): CT, MR, US, DX, XA, MG, ...
        $t->string('modality', 16)->nullable()->after('protocol_name');
    });
}
```

You also need the AE title of the scanner assigned to each room/service point. The
blueprint's worklist code already referenced `servicePointMetadata->hardware_ae_title`,
so store it wherever your service-point metadata lives (a `hardware_ae_title` column,
max 16 chars per the DICOM AE VR).

---

## 2. Eloquent relations

`app/Models/ImagingStudy.php` must expose these relations (names used by the job):

```php
public function patient()      { return $this->belongsTo(Patient::class, 'global_client_id', 'global_client_id'); }
public function protocol()     { return $this->belongsTo(TenantProcedureProtocol::class, 'protocol_code', 'protocol_code'); }
public function servicePoint() { return $this->belongsTo(ServicePoint::class, 'main_module_service_point_id'); }
```

Adjust foreign keys to your actual schema. The job only reads: patient name
parts / `full_name`, birth date, sex; protocol `modality` and `protocol_name`;
service point `hardware_ae_title`.

---

## 3. Config

`config/services.php`:

```php
'orthanc' => [
    'url'            => env('ORTHANC_URL', 'http://127.0.0.1:8042'),
    'username'       => env('ORTHANC_USERNAME'),
    'password'       => env('ORTHANC_PASSWORD'),
    'worklist_dir'   => env('ORTHANC_WORKLIST_DIR', '/var/lib/orthanc/worklists'),
    'dump2dcm'       => env('DCMTK_DUMP2DCM', 'dump2dcm'),
    'uid_root'       => env('DICOM_UID_ROOT', '2.25'), // 2.25 = ISO UUID-derived, no registration needed
    'webhook_secret' => env('ORTHANC_WEBHOOK_SECRET'),
],
```

`.env`:

```dotenv
ORTHANC_URL=http://127.0.0.1:8042
ORTHANC_USERNAME=kashtre
ORTHANC_PASSWORD=change-me
ORTHANC_WORKLIST_DIR=/var/lib/orthanc/worklists
ORTHANC_WEBHOOK_SECRET=generate-a-long-random-string
```

---

## 4. Orthanc configuration

In Orthanc's `configuration.json` (worklist plugin + Lua callback + auth):

```jsonc
{
  // ... your existing config ...

  "Worklists": {
    "Enable": true,
    "Database": "/var/lib/orthanc/worklists"   // same path as ORTHANC_WORKLIST_DIR
  },

  // Lua script that POSTs to the RIS on StableStudy (file from this bundle):
  "LuaScripts": [ "/etc/orthanc/stable-study.lua" ],

  // How long (seconds) a study must be idle before it's "stable" and the
  // OnStableStudy callback fires. Lower = faster, higher = fewer duplicate fires.
  "StableAge": 60,

  // Lock Orthanc down — the RIS/gateway is the only thing that should reach it.
  "RemoteAccessAllowed": false,
  "AuthenticationEnabled": true,
  "RegisteredUsers": { "kashtre": "change-me" }
}
```

Make sure the `ModalityWorklists` plugin (`libModalityWorklists`) is loaded. Register
each modality's AE title in Orthanc's `DicomModalities` so it can query the worklist.

Edit `stable-study.lua` before deploying: set `RIS_WEBHOOK_URL` and `SHARED_SECRET`
(the secret must equal `ORTHANC_WEBHOOK_SECRET`).

---

## 5. Route

`routes/api.php`:

```php
use App\Http\Controllers\OrthancWebhookController;

Route::post('/orthanc/stable-study', [OrthancWebhookController::class, 'stableStudy'])
    ->middleware('throttle:120,1');
```

If this endpoint is exposed publicly, also restrict it to Orthanc's IP with a
middleware or web-server ACL — the shared secret is defence-in-depth, not the only lock.

---

## 6. Dispatching the worklist

Dispatch the job at the moment the study becomes ready — e.g. in the state-transition
code or an observer on `imaging_studies`:

```php
use App\Jobs\BroadcastModalityWorklist;

// when status transitions to READY_FOR_STUDY:
BroadcastModalityWorklist::dispatch($study->id);
```

---

## 7. Verifying it works

```bash
# a) After dispatch, the .wl file should exist:
ls -l /var/lib/orthanc/worklists/

# b) Query the worklist the way a modality would (needs DCMTK's findscu):
findscu -W -k "0008,0050=" -aet TEST -aec ORTHANC localhost 4242

# c) Simulate a stable study: send any DICOM with the matching AccessionNumber
#    to Orthanc via C-STORE (storescu), wait StableAge seconds, and watch the
#    Laravel log — the study should advance to IMAGE_ACQUIRED and the .wl vanish.
```

---

## Design notes worth knowing

- **Accession is the join key.** The RIS generates it, writes it into the worklist,
  the modality stamps it into the acquired DICOM, and the webhook matches on it. Keep
  accession generation in one place and never reformat it downstream.
- **StudyInstanceUID is best-effort.** We put a UID in the worklist so well-behaved
  modalities reuse it, but some generate their own. The webhook therefore matches on
  **accession** and only cross-checks the UID (logs a notice on mismatch, still
  proceeds).
- **Idempotent by design.** `OnStableStudy` can fire more than once (late images
  re-open then re-stabilise a study). The webhook only advances from a pre-acquisition
  state and re-runs safely; the row is locked `FOR UPDATE` during the check.
- **Unmatched studies aren't lost.** Images that arrive with no matchable accession
  return HTTP 200 with an `unmatched_*` status and a warning log — build a
  reconciliation queue off those logs rather than letting them fail silently.
- **The webhook stays thin.** It fires a `StudyImagesAcquired` event after commit.
  Hang your dose-SR ingestion and inventory depletion (`RadiologyRecipeEngine`) off
  that event as listeners, so retries and ordering are handled by the queue.
