# PACS End-to-End Test — via the Web UI

Walks a real study from order → worklist → simulated scan → automatic
completion, entirely through the browser. Uses **Chest X-Ray** since it has
no consent/prep/readiness gates in the way — fastest path to a clean test.

## 0. Start everything

| Service | Command | Terminal |
|---|---|---|
| MySQL | (already running via XAMPP) | — |
| Vite | `npm run dev` | leave open |
| Laravel | `php artisan serve --port=8000` | leave open |
| Orthanc | `& "C:\Program Files\Orthanc Server\Orthanc.exe" "C:\Orthanc\config.json" --verbose` | leave open |

Check Orthanc's terminal says `Orthanc has started` and the earlier
`Name: KASHTRE-DEV` (not `MyOrthanc`) — if it still says `MyOrthanc`, the
Windows service is running instead; `net stop "Orthanc"` first.

## 1. One-time: give a service point a hardware AE Title

The worklist broadcast silently skips any service point with no AE Title set.

1. Log in as a **business_id = 1** (Kashtre) account.
2. **Settings → Manage Imaging Rooms** (`/imaging-service-point-configs`).
3. **New Imaging Room Config** → pick the Business + a Service Point you'll
   route orders to → **Hardware AE Title**: any short string, e.g. `CT01`
   (this is cosmetic for the test — no real scanner needs to match it) →
   leave Inventory Store blank → Save.

## 2. Place and accept an order

1. **Imaging → Imaging Orders** (`/imaging-orders`).
2. **New Imaging Order** → pick the Business/Branch/Client → Protocol:
   **Chest X-Ray** → Save.
3. On the same list, **Accept** the order you just created → pick the
   Service Point you configured in step 1 → confirm. This creates the
   `ImagingStudy` in `ORDER_RECEIVED` and opens its accession page.

## 3. Progress to Ready For Study

On the study's detail page (`/imaging-studies/{id}`):

1. **Start Preparation** → **Complete Preparation** (Chest X-Ray has no
   checklist items, so these just advance the status).
2. **Ready For Study**.

This is the exact moment `BroadcastModalityWorklist` fires — since
`QUEUE_CONNECTION=sync` in this app, it runs immediately, inline, before the
page even finishes redirecting. No need to wait or run a queue worker.

## 4. Confirm the worklist landed in Orthanc

Open Orthanc's own UI: **http://127.0.0.1:8042/ui/app/** (login `kashtre` /
whatever's in `C:\Orthanc\config.json`'s `RegisteredUsers`) → look for a
pending worklist entry with your study's Accession Number.

If nothing appears, check `storage/logs/laravel.log` for `MWL skipped: ...`
— almost always a missing AE Title (step 1) or a modality with no DICOM
mapping (only Chest X-Ray/CT/MRI/US/MG/Fluoro protocols are mapped).

## 5. Simulate the scan (no real scanner needed)

The script requires the **real** patient name, patient ID, modality, and
physician as named arguments — no placeholder defaults — so what lands in
Orthanc actually matches the order instead of showing a generic fake
patient on the wrong modality. Pull all of it in one go:

```bash
php artisan tinker --execute="
\$s = App\Models\ImagingStudy::find(<id>);
\$c = \$s->resolveClient();
\$u = \$s->resolveOrderingUser();
echo 'study-uid: ' . \$s->study_instance_uid . PHP_EOL;
echo 'patient-name: ' . \$c->full_name . PHP_EOL;
echo 'patient-id: ' . \$s->client_id . PHP_EOL;
echo 'modality (app): ' . \$s->modality_type . PHP_EOL;
echo 'physician: ' . (\$u->name ?? '') . PHP_EOL;
"
```

`modality (app)` is this app's internal vocabulary, not a DICOM code —
translate it via `OrthancDicomWorklistBroker::DICOM_MODALITY_CODES`
(`XRAY→DX, CT→CT, MRI→MR, US→US, MG→MG, FLUORO→XA`) before passing
`--modality`.

Then, from the project root (Windows: use `curl.exe`, not PowerShell's
`curl` alias — it doesn't understand `-u`):

```bash
pip install pydicom   # one-time
python "pacs integration files/make_test_dicom.py" \
  --accession <AccessionNumber> --study-uid <StudyInstanceUID> \
  --patient-name "<real name>" --patient-id <real client_id> \
  --modality <real DICOM code> --physician "<real physician>" \
  --output test.dcm
curl.exe -u kashtre:kashtre -X POST http://127.0.0.1:8042/instances --data-binary "@test.dcm"
```

This uploads a synthetic image Orthanc treats exactly like a real C-STORE
from a scanner — with data that actually matches the order.

**Why this can't happen for real**: this manual DICOM-crafting step only
exists because there's no physical scanner in a dev environment. In real
use, a scanner reads the patient/accession straight off the worklist entry
via C-FIND when the technologist selects the matching row — that's the
whole point of Pillar 1.1's "zero-keystroke" design (`SETUP.md` section 1)
— so there's no manual re-entry step for a mismatch to creep into.

## 6. Watch it finish itself

Wait ~30 seconds (`StableAge` in `config.json`), then refresh the study
page in KashTre. It should now read **Image Acquired** — advanced
automatically, no manual action. Behind the scenes: Orthanc's Lua script
fired, hit `/api/orthanc/stable-study`, and the study ran through its real
`markInProgress()` → `markImageAcquired()` transitions (check the page for
the status history / consumption behaving normally).

If it's still stuck on **Ready For Study** after a minute, check:
- Orthanc's terminal for a `LUA-EVENTS` line — confirms the callback fired.
- `storage/logs/laravel.log` for anything from `StableStudy: ...`.

## 7. Clean up (optional)

Test studies/orders are ordinary rows — delete via tinker if you don't want
them cluttering real lists:

```bash
php artisan tinker --execute="App\Models\ImagingStudy::find(<id>)->delete();"
```

Orthanc side: **Explorer 2 → the study → Delete**, or:

```bash
curl -u kashtre:kashtre -X DELETE http://127.0.0.1:8042/studies/<orthanc-study-id>
```
