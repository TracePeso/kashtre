# Orthanc + KashTre RIS — local Windows 11 (XAMPP) dev setup

Everything runs on one Windows 11 PC: **Orthanc** (the PACS) and your **Laravel app under
XAMPP** (the RIS). No conflict — Orthanc uses ports **8042** (REST/web UI) and **4242**
(DICOM); XAMPP uses 80/443/3306.

This version creates worklists through Orthanc's **Worklists plugin REST API**, so there's
**no DCMTK / `dump2dcm` and no `.wl` files to manage**. DCMTK is now optional — only handy
for testing (`findscu`, `storescu`).

```
READY_FOR_STUDY ──POST /worklists/create──▶ Orthanc ──C-FIND──▶ modality
                                                                    │
                                                              scan + C-STORE
                                                                    ▼
IMAGE_ACQUIRED ◀── POST /api/orthanc/stable-study ◀── OnStableStudy (Lua) ◀── study stable
```

Files that go with this guide:
`BroadcastModalityWorklist.php`, `OrthancClient.php`, `OrthancWebhookController.php`,
`DicomUid.php`, `StudyImagesAcquired.php`, `stable-study.lua`.
(The earlier `OrthancWorklistWriter.php` / `dump2dcm` approach is superseded — keep it only
as a fallback if you ever can't use the Worklists plugin.)

---

## 1. Install Orthanc

1. Download the **64-bit Windows installer** (`.exe`) from the Orthanc downloads page.
2. It isn't code-signed, so click **More info → Run anyway** if SmartScreen blocks it.
3. Accept defaults: install dir `C:\Program Files\Orthanc Server`, data dir `C:\Orthanc`.
4. On the plugin screen, **select all plugins** — you specifically need **DICOMweb** (for OHIF)
   and **Worklists**; the viewers are useful too.
5. The installer registers Orthanc as a **Windows service** that auto-starts. The web UI is at
   `http://localhost:8042/ui/app/` — default login `orthanc` / `orthanc`. **Change it.**

Confirm the plugins loaded: open `http://localhost:8042/plugins` (or the Plugins page in the
UI) and check that `dicom-web` and `worklists` are listed.

---

## 2. Configure Orthanc for dev

For development, the easiest workflow is to **stop the service and run Orthanc from a terminal
against your own config**, so you see logs live:

```powershell
# stop the auto-started service
net stop "Orthanc"

# run against your own config, verbose
& "C:\Program Files\Orthanc Server\Orthanc.exe" "C:\Orthanc\config.json" --verbose
```

Create `C:\Orthanc\config.json`:

```jsonc
{
  "Name": "KASHTRE-DEV",

  "StorageDirectory": "C:\\Orthanc\\db",
  "IndexDirectory":   "C:\\Orthanc\\db",

  "HttpServerEnabled":  true,
  "HttpPort":           8042,
  "DicomServerEnabled": true,
  "DicomPort":          4242,
  "DicomAet":           "ORTHANC",

  // localhost-only + auth. XAMPP/Laravel on the same PC counts as localhost, so it can
  // reach the REST API even with RemoteAccessAllowed=false.
  "RemoteAccessAllowed":   false,
  "AuthenticationEnabled": true,
  "RegisteredUsers": { "kashtre": "change-me-please" },

  // point at the folder holding the plugin DLLs installed in step 1
  "Plugins": [ "C:\\Program Files\\Orthanc Server\\Plugins" ],

  "DicomWeb": {
    "Enable": true,
    "Root":   "/dicom-web/"
  },

  "Worklists": {
    "Enable": true,
    "SaveInOrthancDatabase": true,          // stores worklists in Orthanc's DB (default SQLite)
    "SetStudyInstanceUidIfMissing": true,
    "DeleteWorklistsOnStableStudy": true,   // auto-removes the worklist once its study lands
    "HousekeepingInterval": 30
  },

  "StableAge": 30,   // seconds of no new images before a study is "stable" (dev-friendly; prod ~60)

  "LuaScripts": [ "C:\\Orthanc\\stable-study.lua" ],

  // lets you test worklists with DCMTK's findscu
  "DicomModalities": {
    "findscu": [ "FINDSCU", "127.0.0.1", 1234 ]
  }
}
```

**If Orthanc complains at startup that the database doesn't support key-value stores**, your
build's SQLite index can't hold worklists — switch to folder mode (Laravel code is identical):

```jsonc
"Worklists": {
  "Enable": true,
  "SaveInOrthancDatabase": false,
  "Directory": "C:\\Orthanc\\worklists",   // create this folder; Orthanc writes the .wl files
  "SetStudyInstanceUidIfMissing": true,
  "DeleteWorklistsOnStableStudy": true,
  "HousekeepingInterval": 30
}
```

Copy `stable-study.lua` to `C:\Orthanc\stable-study.lua` and edit its two constants:
`RIS_WEBHOOK_URL` (your Laravel URL) and `SHARED_SECRET` (must equal `ORTHANC_WEBHOOK_SECRET`).

Once it works from the terminal, you can point the Windows **service** at the same config
(edit the generated config files under the install's `Configuration` folder to match, or keep
running it by hand during active dev) and `net start "Orthanc"`.

---

## 3. Wire Laravel (XAMPP) to Orthanc

`config/services.php`:

```php
'orthanc' => [
    'url'            => env('ORTHANC_URL', 'http://127.0.0.1:8042'),
    'username'       => env('ORTHANC_USERNAME'),
    'password'       => env('ORTHANC_PASSWORD'),
    'uid_root'       => env('DICOM_UID_ROOT', '2.25'),
    'webhook_secret' => env('ORTHANC_WEBHOOK_SECRET'),
],
```

`.env` (use `127.0.0.1`, not `localhost`, to avoid IPv6 `::1` surprises):

```dotenv
ORTHANC_URL=http://127.0.0.1:8042
ORTHANC_USERNAME=kashtre
ORTHANC_PASSWORD=change-me-please
ORTHANC_WEBHOOK_SECRET=generate-a-long-random-string
DICOM_UID_ROOT=2.25
```

Enable `ext-gmp` (or `ext-bcmath`) in XAMPP's `php.ini` — uncomment `extension=gmp` and restart
Apache. It's used to build DICOM UIDs.

---

## 4. Database changes

```php
// database/migrations/xxxx_add_pacs_linkage_to_imaging.php
public function up(): void
{
    Schema::table('imaging_studies', function (Blueprint $t) {
        $t->string('study_instance_uid', 64)->nullable()->unique()->after('accession_number');
        $t->string('orthanc_study_id', 64)->nullable()->after('study_instance_uid');
        $t->string('orthanc_worklist_id', 64)->nullable()->after('orthanc_study_id');
    });

    Schema::table('tenant_procedure_protocols', function (Blueprint $t) {
        // DICOM Modality code (0008,0060): CT, MR, US, DX, XA, MG, ...
        $t->string('modality', 16)->nullable()->after('protocol_name');
    });
}
```

Make sure `ImagingStudy` has `patient()`, `protocol()`, and `servicePoint()` relations, and
that the service point carries a `hardware_ae_title` (max 16 chars — the scanner's AE title).

---

## 5. Route

`routes/api.php`:

```php
use App\Http\Controllers\OrthancWebhookController;

Route::post('/orthanc/stable-study', [OrthancWebhookController::class, 'stableStudy'])
    ->middleware('throttle:120,1');
```

Since everything's on localhost, no public exposure is needed; the shared secret is enough.

---

## 6. Run the loop

Keep a queue worker running (the job and the `StudyImagesAcquired` listeners are queued):

```powershell
php artisan queue:work
```

Dispatch a worklist when a study is ready (in your state-transition code):

```php
BroadcastModalityWorklist::dispatch($study->id);
```

---

## 7. Test end-to-end with no real scanner

You can simulate the whole cycle on the PC. DCMTK is only needed here — grab the DCMTK
Windows binaries and put them on PATH.

```powershell
# a) Create a worklist directly (or just run the job) and confirm it exists:
curl -u kashtre:change-me-please -X POST http://127.0.0.1:8042/worklists/create ^
  -d "{ \"Tags\": { \"AccessionNumber\": \"ACC-TEST-1\", \"PatientID\": \"CLIENT-1\", \"PatientName\": \"Doe^John\", \"StudyInstanceUID\": \"2.25.9999\", \"ScheduledProcedureStepSequence\": [ { \"Modality\": \"CT\", \"ScheduledStationAETitle\": \"CT01\" } ] } }"

# b) Query it the way a modality would (should return AccessionNumber ACC-TEST-1):
findscu -W -aet FINDSCU -aec ORTHANC -k "0008,0050=*" 127.0.0.1 4242

# c) Simulate acquisition: stamp a sample DICOM with the SAME accession + UID, then send it.
dcmodify -i "(0008,0050)=ACC-TEST-1" -i "(0020,000D)=2.25.9999" sample.dcm
storescu -aec ORTHANC -aet CT01 127.0.0.1 4242 sample.dcm

# d) Wait ~StableAge seconds, then watch storage/logs/laravel.log:
#    the study advances to IMAGE_ACQUIRED and the worklist is removed.
```

If you don't have a sample DICOM, upload any file via the Explorer 2 **Upload** menu first,
then export one to use with `dcmodify`.

---

## 8. Viewing images (OHIF / built-in)

For quick checks, use the viewers bundled with Orthanc (open a study in Explorer 2). For your
embedded OHIF, point its DICOMweb data source at `http://127.0.0.1:8042/dicom-web/` with the
basic-auth credentials. In anything beyond local dev, proxy DICOMweb through your gateway so
the browser never holds Orthanc credentials directly (the control-plane / data-plane split from
earlier still applies).

---

## Notes

- **Local dev only.** `RemoteAccessAllowed: false` keeps Orthanc bound to localhost. Don't put
  real patient data on a dev laptop; production hosting and Uganda DPA 2019 data-residency are a
  separate decision.
- **LAN modalities.** If you later point a real scanner (or another PC) at Orthanc, open
  inbound TCP **4242** in Windows Firewall and add that modality under `DicomModalities`.
- **What changed from the DCMTK version:** worklists are created via REST (no `dump2dcm`, no
  file permissions), stored in Orthanc's DB or a folder Orthanc itself owns; a new
  `orthanc_worklist_id` column lets the webhook delete the entry via REST; the Lua script points
  at `127.0.0.1`. DCMTK is now optional and only used for testing.
