# Clinical Module — End-to-End Manual Test Script

**Purpose:** walk a real chart through every implemented Clinical Module path — bedside charting, ward/bed
management, cross-module ordering (RIS + LIMS), inventory consumption, admission→discharge, medication
safety, security paths — using the actual UI, against a real (or your local dev) database. This is a manual
QA script, not the automated suite (that's §7).

Every route, permission string, CDE code, process code, and reason code below is copied from the current
codebase, not the spec docs — if something here doesn't match what you see on screen, the code changed and
this doc is stale, not the other way round.

Use the checkboxes as you go. Where a step says "Expected," that's the pass condition.

---

## 0. One-time environment setup

Run these once, before the first test pass (skip anything you've already done).

- [ ] **Migrate the Clinical connection** (separate from the default DB — see `config/database.php`):
  ```
  php artisan migrate --database=clinical
  ```
- [ ] **Seed the master dictionaries** (UOM, unit conversions, reason codes, escalation tiers, route/frequency
      list, the CDE registry):
  ```
  php artisan db:seed --class="Database\Seeders\ClinicalMasterDictionariesSeeder"
  ```
- [ ] **Seed the process registry** (Admission/Transfer/Discharge/Referral/Death workflows):
  ```
  php artisan db:seed --class="Database\Seeders\ClinicalProcessRegistrySeeder"
  ```
- [ ] **Confirm the dispatch driver.** In `.env`, `DISPATCH_DRIVER` should be unset or `local` for the main
      walkthrough below — this routes Clinical → Imaging/Inventory calls in-process against your real
      Imaging/Inventory data. (§8 covers testing the `http` driver separately, once you're done with the
      main script.)
- [ ] **Create a ward and at least one bed.** There's no admin UI for this yet (Ward Census only manages
      *occupancy* of existing beds, plus overflow beds on top of an existing ward) — seed one via tinker.
      Replace `4` with the `business_id` of the business you'll log in as.

      **PowerShell** (note the `--%` stop-parsing token — without it, PowerShell 5.1 silently strips the
      inner quotes before `php.exe` ever sees them):
      ```
      php artisan tinker --% --execute="$w = App\Models\ClinicalWard::create(['business_id' => 4, 'ward_code' => 'W1', 'ward_name' => 'Test Ward 1']); App\Models\ClinicalBed::create(['ward_id' => $w->id, 'bed_code' => 'BED-01']); App\Models\ClinicalBed::create(['ward_id' => $w->id, 'bed_code' => 'BED-02']); echo $w->id;"
      ```

      **Bash / Git Bash:**
      ```
      php artisan tinker --execute='$w = App\Models\ClinicalWard::create(["business_id" => 4, "ward_code" => "W1", "ward_name" => "Test Ward 1"]); App\Models\ClinicalBed::create(["ward_id" => $w->id, "bed_code" => "BED-01"]); App\Models\ClinicalBed::create(["ward_id" => $w->id, "bed_code" => "BED-02"]); echo $w->id;'
      ```
- [ ] **Pick a test client.** Use an existing client from **Clients** in your business, or create a new one.
      You need their **`client_id`** (the short business identifier shown on their record — e.g. `EXKTKLAB`),
      not the numeric database id. Clinical addresses patients by this string everywhere.
      - If the client is under 12 (`age < 12` on the record), the pediatric weight-dose CDSS check in §4.9
        will apply to them — useful to know but not required.

### Test users

Create three staff users (or edit existing ones) via **Staff → Edit → Access Control**, and tick exactly the
permissions listed. These map to real clinical roles so the role-gated steps in §4.5/§4.9/§4.14 behave the
way they would in production — you'll switch between "Nurse" and "Consultant" logins at a few points.

| Permission | QA-Nurse | QA-Consultant | QA-NoAccess |
|---|:---:|:---:|:---:|
| View Ward Census | ✅ | ✅ | |
| Manage Ward Census | ✅ | ✅ | |
| Add Overflow Beds | ✅ | ✅ | |
| View Care Assignments | ✅ | ✅ | |
| Manage Care Assignments | ✅ | ✅ | |
| View Clinical Observations | ✅ | ✅ | |
| Add Clinical Observations | ✅ | ✅ | |
| View Clinical Work Orders | ✅ | ✅ | |
| Add Clinical Work Orders | ✅ | ✅ | |
| View Clinical Process Registry | ✅ | ✅ | |
| Progress Clinical Process Registry | ✅ | ✅ | |
| **Act As Ward Nurse (Clinical)** | ✅ | | |
| **Act As Consultant (Clinical)** | | ✅ | |
| View Medication Orders | ✅ | ✅ | |
| **Prescribe Medication Orders** | | ✅ | |
| **Override CDSS Safety Block** | | ✅ | |
| **Administer MAR Doses** | ✅ | | |
| View Clinical Diagnoses | ✅ | ✅ | |
| Add Clinical Diagnoses | ✅ | ✅ | |
| View Clinical Audit Trail | ✅ | ✅ | |
| Export FHIR Bundle | ✅ | ✅ | |
| **Trigger Break Glass Override** | | ✅ | |

`QA-NoAccess` gets **no** Clinical permissions at all — it exists purely to prove the 403 paths in §4.17.

---

## 1. Ward Census Board

**URL:** `/clinical/ward-census` · Login as **QA-Nurse**.

- [ ] Page loads, shows **Test Ward 1** with `BED-01` and `BED-02`, both `AVAILABLE`. Header counts (total/
      occupied/reserved/available) match.
- [ ] Click **Add Overflow Bed** on the ward. Expected: a new `BED-3-EXTRA` bed appears, marked as overflow.
- [ ] Click the `AVAILABLE` `BED-01`, enter your test client's `client_id` (and optionally a visit id), confirm.
      Expected: bed flips to `OCCUPIED`, shows the client id.
- [ ] Click the now-`OCCUPIED` `BED-01`. Expected: navigates to that patient's chart
      (`/clinical/patients/{clientId}/observations`).
- [ ] Back on the ward board, click **Release** on the overflow bed you added. Expected: the overflow bed is
      **deleted outright** (not left `AVAILABLE`) — confirms the SRD's overflow auto-retirement rule.
- [ ] Click **Release** on `BED-01` (the real bed, not overflow). Expected: it returns to `AVAILABLE` with the
      client cleared, bed itself still exists.
- [ ] Re-occupy `BED-01` with your test client before continuing — later sections assume the patient is
      admitted to a bed.

---

## 2. Bedside Observations (CDE capture, unit conversion, safety heuristic)

**URL:** `/clinical/patients/{clientId}/observations` · Login as **QA-Nurse**.

This page is one long stack of panels — you'll come back to it repeatedly through this script. Skip to
**Capture Observations** for this section.

- [ ] The form lists numeric CDEs from the registry: **Axillary Temperature, Random Blood Glucose, Pulse
      Rate, Oxygen Saturation, Body Weight, Serum Creatinine, Estimated GFR** — each with a unit dropdown
      defaulted to its base unit.
- [ ] Enter **Random Blood Glucose = 126.1**, unit = **mg/dL**, save.
      Expected: saves without error; the flowsheet shows `126.1`. (Base unit is mmol/L — internally this
      converts to ≈7.0 mmol/L; that's just for your own sanity check, not visible on screen.)
- [ ] Enter **Random Blood Glucose = 180**, unit = **mmol/L**, save.
      Expected: **rejected** — 180 mmol/L is physiologically implausible (max is 50). You should see a
      `HEURISTIC_SAFETY_BLOCK` error and **no** new observation recorded.
- [ ] Enter **Axillary Temperature = 98.6**, unit = **°F**, save. Expected: succeeds (converts to ≈37°C).
- [ ] Enter **Body Weight** = a plausible value for your test client (any number 0.3–400 kg). You'll need
      this later for the pediatric-dose CDSS check if your client is under 12, and it's otherwise harmless.
- [ ] Confirm all of the above appear in **Recent Observations** with correct values/units/timestamps.

### Claim Patient (ReBAC)

- [ ] If you haven't claimed this patient as QA-Nurse yet, you should see **"No active care assignment for
      you on this patient"** with claim buttons.
- [ ] Click **Claim as Nurse**. Expected: succeeds, banner disappears (QA-Nurse holds
      `Act As Ward Nurse (Clinical)`).
- [ ] Log in as **QA-Consultant**, open the same chart, click **Claim as Doctor**. Expected: succeeds
      (holds `Act As Consultant (Clinical)`).
- [ ] Log in as a user with `Manage Care Assignments` but **without** either capacity permission and try to
      claim (either role). Expected: **403 Forbidden** — capacity is enforced, not just the generic
      "manage assignments" permission.

---

## 3. My Patient Tasks

**URL:** `/clinical/my-tasks` · Login as **QA-Nurse** (who claimed the patient above).

- [ ] Page loads, **My Patients** count includes your test client.
- [ ] The ward grouping shows **Test Ward 1** with the occupied bed, since the claimed client is currently
      admitted to `BED-01`.
- [ ] Any pending main-module service-delivery queue items for that client appear grouped by item name.

---

## 4. Clinical Process Registry — Admission → Bed Allocation

**On the same patient chart** (`Clinical Process Panel` section) · Login as **QA-Nurse**.

Recall the seeded `ADMISSION` workflow steps, in order:

1. `ADMISSION_ASSESSMENT` — no role required
2. `INITIAL_NURSING_ASSESSMENT` — requires **WARD_NURSE**
3. `CLINICAL_RISK_ASSESSMENT` — requires **WARD_NURSE**
4. `CARE_PLAN_INITIALIZATION` — no role required
5. `BED_ALLOCATION` — no role required, but **triggers a bed-allocation side effect**
6. `WARD_NURSE_ACCEPTANCE` — requires **WARD_NURSE**

- [ ] Select process **ADMISSION**, enter a note, **Start Process**. Expected: an execution is created,
      panel shows step 1 as current.
- [ ] Complete step 1 (`ADMISSION_ASSESSMENT`). Expected: succeeds for anyone with `Progress Clinical
      Process Registry` — no role check.
- [ ] Complete step 2 (`INITIAL_NURSING_ASSESSMENT`) as **QA-Nurse**. Expected: succeeds — you hold
      `Act As Ward Nurse (Clinical)`.
- [ ] **Negative check:** switch to a user with `Progress Clinical Process Registry` but no
      `Act As Ward Nurse (Clinical)`, try to complete a `WARD_NURSE`-gated step. Expected: **403**, with a
      message naming the required role.
- [ ] Complete step 3 (`CLINICAL_RISK_ASSESSMENT`) and step 4 (`CARE_PLAN_INITIALIZATION`).
- [ ] Reach step 5, **Bed Allocation**. Pick an available bed (use `BED-02`, since `BED-01` is already
      occupied by this same client) and complete it.
      Expected: `BED-02`'s `operational_state` flips to `OCCUPIED` — this is the `EFFECT_ALLOCATE_BED`
      side-effect firing, driven by the seeded `side_effect` flag, not a hardcoded step-code check.
- [ ] Complete step 6 (`WARD_NURSE_ACCEPTANCE`) as QA-Nurse. Expected: the execution reports complete/
      no more current step.
- [ ] Open **Audit Trail** (§9) afterward and confirm all six step completions are listed with timestamps
      and the completing user.

---

## 5. Diagnostic Ordering — real RIS/Imaging integration

**Same chart, "Place Diagnostic Order" panel** · Login as QA-Nurse or QA-Consultant (either holds
`Add Clinical Work Orders`).

- [ ] Protocol dropdown is populated from **Imaging's real protocol list** (this is a live cross-module
      read, not mock data). Pick **Chest X-Ray** (code `CHEST-XRAY`, a system-wide protocol available to
      every business) — or any protocol specific to your business if you'd rather.
- [ ] Enter a clinical indication, **Place Order**.
      Expected: a new row appears in this panel's work-order list, status `IN_PROGRESS` (or `PENDING` if
      the dispatch didn't return an imaging id — investigate if so).
- [ ] **Cross-module check:** open the **Imaging module** in another tab (its own order/study list) and
      confirm a real `ImagingOrder`/study now exists for this client, linked back to the work order you just
      created — not just a row in Clinical's own table.
- [ ] Progress that study through Imaging's normal workflow (reporting → validation) using the Imaging UI
      as you normally would, up to a validated report.
- [ ] Back on the Clinical chart, confirm:
      - The work order's status flips to `COMPLETED`.
      - A new observation with code `RAD_IMPRESSION_{protocol_code}` (e.g. `RAD_IMPRESSION_CHEST-XRAY`)
        appears in **Recent Observations**, containing the report text.
- [ ] If the study/protocol is configured to trigger consumption (e.g. contrast media), confirm a
      corresponding consumption event lands as in §6.

---

## 6. Point-of-care Consumption — real Inventory integration

**Same chart, "Record Consumption" panel** · Login as QA-Nurse or QA-Consultant (`Add Clinical
Observations`).

- [ ] Pick a real item from your business's Inventory (e.g. `Axcel 400mg`), enter a quantity, leave the
      fact token as **Medication Administered**, usage context **Patient**, **Record**.
      Expected: result message is either:
      - `Recorded (SCENARIO) — billing event created.` / `Recorded (SCENARIO).`, or
      - `Could not deplete stock: ...` if there isn't enough stock, or
      - `Inventory module is not active for this business.`
- [ ] **Cross-module check:** open **Inventory** for this item and confirm the stock quantity actually
      decreased by what you recorded — not just a Clinical-side log entry.
- [ ] If the item isn't part of an approved pool / the business has no matching store, confirm a billing
      event was created for the excess/non-approved usage (per the reconciliation matrix) — check
      Inventory's billing/postpaid records.
- [ ] Confirm the event appears in **Recent Events** on this same panel with the correct quantity and
      timestamp.

---

## 7. Lab Ordering — LIMS (stub-backed today)

**Same chart, "Place Lab Order" panel** · Login as QA-Nurse or QA-Consultant.

LIMS doesn't exist as a real service yet, so this panel talks to `StubLimsClient` by default — you'll see a
note to that effect on the panel ("isStubbed" — a **Simulate Result** control only appears while stubbed).

- [ ] Enter test code **GLUCOSE** (matches the seeded `GLUCOSE_RANDOM` CDE), clinical indication optional,
      **Place Order**. Expected: a new `LAB_GLUCOSE` work order appears, status `PENDING`, with an
      `external_reference` (lab order UUID).
- [ ] Use the **Simulate Result** control next to that order, enter a value (e.g. `6.5`), submit.
      Expected:
      - The work order flips to `COMPLETED`.
      - A new `GLUCOSE_RANDOM` observation appears in **Recent Observations** on the Capture Observations
        panel, attributed to the lab result.
- [ ] Place a second lab order and simulate an **abnormal/critical** value if your test data supports it —
      confirm no error, and note whether an escalation/critical-result path fires (check for any alerting
      hook; if none is visibly wired to UI yet, that's expected — escalation delivery channels are
      config-only at this point per the seeded `clinical_escalation_rules`).

---

## 8. Medication Ordering, Deterministic CDSS Shield, and MAR

**Same chart, "Medication Orders" panel.**

### 8a. Normal prescribe → MAR generation (QA-Consultant)

- [ ] Search drug **"Axcel"** (or any real item name in your business), dose amount e.g. `500`, route
      **PO**, frequency **BID**, **Prescribe**.
      Expected: `Prescribed {drug name} from internal stock.` — an active medication order appears, and
      MAR doses are generated (check **Due Doses** — should populate once a dose falls within today).
- [ ] Search a drug name that **doesn't exist** in your inventory (e.g. `"Zzznotarealdrug"`), prescribe.
      Expected: `External referral generated for {name} — no internal SKU matched.` A PDF referral is
      generated (check `storage/app/clinical/external-referrals/{business_id}/...`).

### 8b. Hard-block: drug allergy

- [ ] Back on **Capture Observations**, there's no dedicated allergy UI in this MVP — record the allergy
      via tinker instead (the CDSS shield reads whatever's in `cde_observations` for
      `ALLERGY_MEDICATION`). Replace `4` and `YOUR_CLIENT_ID` with your real business id / client id.

      **PowerShell:**
      ```
      php artisan tinker --% --execute="App\Models\CdeObservation::create(['business_id' => 4, 'client_id' => 'YOUR_CLIENT_ID', 'cde_code' => 'ALLERGY_MEDICATION', 'captured_value_text' => 'Axcel', 'capture_method' => 'MANUAL', 'validation_status' => 'VALIDATED', 'captured_at' => now()]);"
      ```

      **Bash / Git Bash:**
      ```
      php artisan tinker --execute='App\Models\CdeObservation::create(["business_id" => 4, "client_id" => "YOUR_CLIENT_ID", "cde_code" => "ALLERGY_MEDICATION", "captured_value_text" => "Axcel", "capture_method" => "MANUAL", "validation_status" => "VALIDATED", "captured_at" => now()]);'
      ```
- [ ] Try to prescribe **Axcel** again as QA-Consultant. Expected: **blocked** — the panel surfaces a
      hard-block reason (`DRUG_ALLERGY`, naming the matched allergy) instead of creating the order, and asks
      for an override reason.
- [ ] Pick an override reason (e.g. *"Patient Previously Desensitized / Tolerates Under Monitoring"*),
      re-submit. Expected: order now goes through, `cdss_override_reason` recorded on the order.
- [ ] **Negative check:** repeat the same override attempt as **QA-Nurse** (who lacks `Override CDSS Safety
      Block`). Expected: **403** even with a valid override reason selected.

### 8c. Pediatric weight-dose block (only if your test client is under 12)

- [ ] Confirm a `BODY_WEIGHT` observation exists for the client (from §2). Prescribe a dose deliberately
      more than 150% of `weight_kg × 15mg/kg` (the default max). Expected: hard-blocked with
      `PEDIATRIC_WEIGHT_OVERDOSE`, same override flow as above.

### 8d. MAR administration

- [ ] As **QA-Nurse**, find a due dose in **Due Doses**, **Administer**. Expected: succeeds, dose status
      updates, and the ward is correctly inferred from the client's current bed (no manual ward picker).
- [ ] Administer a second dose, but this time **Hold** it instead — pick a wastage reason (e.g. *"Dose
      Dropped / Contaminated"*), add a note, submit. Expected: dose marked held with reason attached.
- [ ] **Negative check:** try `Administer`/`Hold` as **QA-Consultant** (who lacks `Administer MAR Doses`).
      Expected: **403**.

---

## 9. Diagnoses Panel

**Same chart, "Diagnoses" panel.**

- [ ] Add a condition: ICD-11 code (any string, e.g. `1A00`), description **"Cholera"**, submit. Expected:
      appears in the list with recorded-by user and timestamp.
- [ ] Confirm a user without `Add Clinical Diagnoses` gets **403** attempting the same action, while one
      with only `View Clinical Diagnoses` can still see the list.

---

## 10. Bedside Scratchpad

**Same chart, "Bedside Scratchpad" panel.**

- [ ] Type a free-text note, **Save as Note**. Expected: saved instantly as a `BEDSIDE_NOTE` observation,
      appears under **Recent Notes** — this path has **zero dependency on the AI Gateway**.
- [ ] If `AI_GATEWAY_URL` is unset (default in dev), the **Extract with AI** control should either be hidden
      or, if you click it anyway, respond with a graceful "AI Gateway is unavailable — use Save as Note
      instead" message rather than an error page. This is the expected state until a real Gateway is
      deployed — not a bug.

---

## 11. Audit Trail

**Same chart, "Audit Trail" panel.**

- [ ] Confirm the process-step executions from §4 (all six ADMISSION steps) are listed, most recent first,
      each showing the step name and completing user.
- [ ] Once you've done the Break-Glass exercise in §13, confirm the grant shows up here too.

---

## 12. FHIR Export

**URL:** `/clinical/patients/{clientId}/fhir-export`

- [ ] As a user **with** `Export FHIR Bundle`, open the URL directly (it opens in a new tab from the chart
      page too). Expected: a JSON FHIR Bundle response containing Patient/Observation/Condition entries
      reflecting what you captured above (vitals, the allergy note, the diagnosis).
- [ ] As a user **without** the permission, hit the same URL. Expected: **403**.

---

## 13. Transfer / Discharge — consultant-gated steps, bed release

Repeat the Clinical Process Panel flow from §4, this time with **DISCHARGE**:

1. `CONSULTANT_DISCHARGE_SIGNOFF` — requires **CONSULTANT**
2. `NURSING_DISCHARGE_REVIEW` — requires **WARD_NURSE**
3. `DISCHARGE_MEDICATION_RECONCILIATION` — no role
4. `OUTSTANDING_RESULTS_REVIEW` — no role
5. `DISCHARGE_SUMMARY_ICD11` — no role
6. `DISCHARGE_MEDICATION_ISSUE` — no role
7. `FINANCIAL_CLEARANCE` — no role, **optional/non-mandatory** step
8. `ENCOUNTER_CLOSURE` — no role, **releases the bed**

- [ ] Start **DISCHARGE**. Attempt step 1 as **QA-Nurse**. Expected: **403** (needs
      `Act As Consultant (Clinical)`).
- [ ] Complete step 1 as **QA-Consultant**. Expected: succeeds.
- [ ] Attempt step 2 as **QA-Consultant**. Expected: **403** (needs `Act As Ward Nurse (Clinical)`).
- [ ] Complete step 2 as **QA-Nurse**, then steps 3–7 as either user.
- [ ] Complete step 8 (`ENCOUNTER_CLOSURE`). Expected: the bed the client currently occupies (`BED-02`,
      allocated in §4) flips back to `AVAILABLE` and is cleared — the `EFFECT_RELEASE_BED` side effect.
- [ ] Confirm on the **Ward Census Board** that `BED-02` now shows `AVAILABLE`.

*(Optional, time-permitting: repeat with `TRANSFER`, `REFERRAL`, or `DEATH_CERT` to exercise the remaining
seeded workflows — same mechanics, different role gates. `REFERRAL`'s final step generates an IPS/C-CDA
export package; `DEATH_CERT`'s `MORTUARY_CUSTODY_HANDSHAKE` step also releases the bed.)*

---

## 14. ZTNA — off-premises detection, watermarking, break-glass

By default, `ZTNA_HOSPITAL_SUBNETS` includes `127.0.0.1/32`, so local dev always resolves as **on-premises**
— you won't see the off-premises banner without forcing it.

### 14a. Simulating off-premises locally

- [ ] In `.env`, temporarily set `ZTNA_HOSPITAL_SUBNETS=8.8.8.0/24` (anything that excludes your dev
      machine's IP), then `php artisan config:clear`.
- [ ] Reload the patient chart. Expected: amber **"Off-premises access — this session is watermarked"**
      banner at the top, plus a faint rotating watermark overlay with your name/id/IP/timestamp.
- [ ] Check the response headers (dev tools → Network) for `X-KashTre-Watermark-User`,
      `X-KashTre-Watermark-IP`, `X-KashTre-Watermark-Timestamp`, and `Cache-Control: no-store, ...`.
- [ ] Try to **Prescribe** a medication or **Administer** a MAR dose while "off-premises". Expected:
      **403** with message `ZTNA_OFF_PREMISE_RESTRICTION: Live medication ordering and MAR dose
      administration are strictly prohibited off-premises.` Other actions (recording observations, placing
      diagnostic/lab orders) should still work — the restriction is scoped to live medication actions only.
- [ ] **Revert** `ZTNA_HOSPITAL_SUBNETS` in `.env` back to its original value (or remove the override) and
      `php artisan config:clear` before continuing — don't leave this changed.

### 14b. Break-glass override (ReBAC denial path)

- [ ] Pick a **different** client this session has no care relationship with (nobody has claimed them, and
      you haven't administered/observed them). Visit their chart as a user who otherwise has full clinical
      permissions but no claimed relationship.
      Expected: **403** access-denied page, explicitly offering a **Break-Glass** request with
      `requires_break_glass: true` semantics.
- [ ] On that page, two break-glass reasons are seeded: *"Emergency Resuscitation / Crash Call"* (requires a
      justification note — submitting without one should show a validation error) and *"On-Call Night /
      Weekend Coverage"* (no note required). Try the first one without a note first to confirm the
      validation fires, then add a note and resubmit.
- [ ] Submit as a user **without** `Trigger Break Glass Override`. Expected: **403**.
- [ ] Submit as **QA-Consultant** (holds the permission). Expected: access granted, redirected into the
      chart, and a `ClinicalBreakGlassLog` row now exists valid for 15 minutes — confirm it shows up in that
      patient's **Audit Trail**.

---

## 15. Permission-denial sweep

Login as **QA-NoAccess** and confirm every one of these returns **403**, not a blank page or a 500:

- [ ] `/clinical/ward-census`
- [ ] `/clinical/my-tasks`
- [ ] `/clinical/patients/{clientId}/observations` — every panel's `mount()` should abort before rendering
      (you'll likely hit the first gated panel's 403 before seeing the rest of the page).
- [ ] `/clinical/patients/{clientId}/fhir-export`

---

## 16. Cross-module HTTP driver (optional — proves the "split onto another server later" path)

Everything above ran with `DISPATCH_DRIVER=local` (in-process calls). These two checks prove the endpoints
a *real* HTTP dispatch or a genuinely external LIMS would hit actually exist and work — useful once, not
part of the routine walkthrough.

- [ ] **Imaging facts endpoint** (what `HttpModuleDispatcher` would call if Imaging moved off-box):
  ```
  curl -X POST http://localhost/api/v1/imaging/facts/diagnostic-order-placed \
    -H "Content-Type: application/json" \
    -H "X-Imaging-API-Key: <value of IMAGING_MODULE_API_KEY in your .env>" \
    -d '{"business_id":YOUR_BUSINESS_ID,"branch_id":null,"global_client_id":"YOUR_CLIENT_ID","visit_id":null,"protocol_code":"CHEST-XRAY","ordering_clinician_id":1,"clinical_indication":null}'
  ```
  Expected: `200` with `{"status":"ORDER_RECEIVED", ...}`, and a real `ImagingOrder` created. Omit/garble
  the header → expect `401`.
- [ ] **LIMS inbound webhook** (what a real, external LIMS would call back on):
  ```
  BODY='{"business_id":YOUR_BUSINESS_ID,"branch_id":null,"client_id":"YOUR_CLIENT_ID","visit_id":null,"lab_order_uuid":"test-uuid","test_code":"GLUCOSE","cde_code":"GLUCOSE_RANDOM","value_numeric":6.5,"unit_label":"mmol/L","is_abnormal":false,"validated_by_user_id":1}'
  SIG=$(php -r "echo hash_hmac('sha256', '$BODY', getenv('LIMS_MODULE_SECRET') ?: 'CHANGE_ME');")
  curl -X POST http://localhost/api/v1/clinical/lab-proxy/result-validated \
    -H "Content-Type: application/json" -H "X-KashTre-Signature: $SIG" -d "$BODY"
  ```
  Expected: `200` with `{"status":"RECORDED"}`, and a new `GLUCOSE_RANDOM` observation for that client.
  Tamper the body after signing, or omit the header → expect `401`.

---

## 17. Automated regression suite

Run this after any manual pass that involved code changes, to catch anything the manual script didn't:

```
php artisan test --filter=Clinical
```

Full-app regression (only when you have time for it — this is the whole suite, not just Clinical):

```
php artisan test
```

Expected, as of the last full run: **116/116** Clinical tests passing, **205/205** app-wide (7 pre-existing
unrelated skips). If your numbers differ, note what changed before assuming it's this script that's wrong.

---

## Sign-off

| Area | Pass/Fail | Notes |
|---|---|---|
| Ward Census & bed lifecycle (§1) | | |
| Observations, unit conversion, safety heuristic (§2) | | |
| Care assignment / claim (§2) | | |
| My Patient Tasks (§3) | | |
| Admission workflow + bed allocation (§4) | | |
| Diagnostic ordering → real Imaging (§5) | | |
| Consumption → real Inventory (§6) | | |
| Lab ordering (stub LIMS) (§7) | | |
| Medication ordering + CDSS shield + MAR (§8) | | |
| Diagnoses panel (§9) | | |
| Bedside scratchpad (§10) | | |
| Audit trail (§11) | | |
| FHIR export (§12) | | |
| Discharge workflow + bed release (§13) | | |
| ZTNA off-premises + watermark (§14a) | | |
| Break-glass override (§14b) | | |
| Permission-denial sweep (§15) | | |
| HTTP driver round-trip (§16) | | |
| Automated suite (§17) | | |
