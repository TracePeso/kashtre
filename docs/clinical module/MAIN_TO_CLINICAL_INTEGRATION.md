# Main ↔ Clinical Module Integration

How this repository (the **Main** module) talks to the separate
**CLINICAL_ORCHESTRATOR** service, and how to cut over.

Companion to the Clinical Module API Integration Guide. Where that document is
the contract, this one is our side of it.

---

## 1. The shape of it

The Clinical Module used to live inside this application: 28 migrations, ~30
`Clinical*`/`Cde*` models, and the engines in `app/Services/Clinical/`. It is
becoming its own service.

Rather than delete all of that and rewrite the UI against HTTP in one step, every
clinical capability now sits behind an interface in `app/Contracts/Clinical/`
with two implementations:

```
app/Contracts/Clinical/ObservationsGateway.php        ← callers depend on this
   ├── Services/Clinical/Gateways/Local/LocalObservationsGateway.php   (in-process engines)
   └── Services/Clinical/Gateways/Api/ApiObservationsGateway.php       (HTTP to Clinical)
```

`ClinicalGatewayServiceProvider` picks one based on `CLINICAL_DRIVER`. Nothing
that consumes a gateway knows or cares which is bound.

| Interface | Covers | Guide § |
| --- | --- | --- |
| `ObservationsGateway` | Atomic CDE capture, flowsheets, unit options | §10.2 |
| `MedicationOrdersGateway` | Prescribing, CDSS dry-run, cancellation | §10.3 |
| `MarGateway` | MAR doses, administration, hold/refuse/waste | §10.4 |
| `CareAccessGateway` | ReBAC, care claims, break-glass, on-premises check | §10.1, §8 |
| `ClinicalDictionaryGateway` | Units, reason codes, routes, frequencies | §10.9 |
| `ScratchpadGateway` | Bedside notes, AI extraction, per-item accept/reject | §10.7 |

### Gateways vs. the full API surface

The gateways above cover only the capabilities that have a **local equivalent** —
the ones that must keep working under `CLINICAL_DRIVER=local`. That is a small
fraction of the contract.

The other ~180 endpoints (FHIR, maternity, transitions, device enrolment, the
surveillance feed, the settings dictionaries) have no local counterpart at all.
Those are reached through `ClinicalApi`, which always goes over HTTP:

```php
$api = app(\App\Services\Clinical\Api\ClinicalApi::class);

$api->chart()->observations('CL-00001234', ['display_uom_id' => 7]);
$api->orders()->translate('ceftriaxone');
$api->transitions()->start('ADMISSION', ['patient_id' => '...', 'visit_id' => '...']);
$api->settings()->cdeRegistry();
$api->interop()->fhirPatientEverything('CL-00001234');
```

| Accessor | Covers | Guide § |
| --- | --- | --- |
| `chart()` | Observations, allergies, diagnoses, immunizations, entitlements | §10.2, §10.8 |
| `orders()` | All four order types, CDSS, order sets, translate | §10.3 |
| `mar()` | MAR, consumption facts, ward tote handshake | §10.4, §12 |
| `wards()` | Beds, census, task visibility, work orders | §10.5 |
| `transitions()` | Admission → death certification step machines | §10.6 |
| `diagnostics()` | Diagnostics, critical alerts, telemetry, scores | §16 |
| `ai()` | Scratchpad, extraction, per-item accept/reject | §10.7 |
| `interop()` | IPS export/import, read-only FHIR R4 | §13 |
| `maternity()` | Birth events, APGAR, infant linking, recall | §10.8 |
| `security()` | Break-glass, devices, surveillance, audit trail | §8, §10.1 |
| `settings()` | All tenant-configurable dictionaries | §10.9 |
| `webhooks()` | HMAC-signed LIMS/RIS callbacks (for testing) | §11 |

**Rule of thumb:** if a capability has a gateway, use the gateway. If it does
not, use `ClinicalApi`.

### Why a seam instead of a rewrite

The cutover is reversible. If the remote service misbehaves in production,
`CLINICAL_DRIVER=local` restores the previous behaviour in one deploy rather
than one revert. That matters more than usual here — the failure mode of a bad
clinical deploy is a ward that cannot chart observations.

---

## 2. Configuration

```ini
CLINICAL_DRIVER=local            # local | api
CLINICAL_MODULE_URL=
CLINICAL_SERVICE_KEY=            # the key Clinical issued to Main
CLINICAL_DEFAULT_TENANT=DEFAULT
CLINICAL_IDENTITY_TRANSPORT=headers   # headers | jwt
CLINICAL_INBOUND_SERVICE_KEYS=   # comma-separated; what Clinical presents to us
```

Selecting `api` without a URL logs an error and falls back to `local`. A
half-configured deploy degrades to something that works rather than to a
clinical module that 503s every action.

---

## 3. Identifier translation

The two modules do not agree on identifiers, and this is where integration bugs
come from. `ClinicalRequestContext` owns the whole mapping — **nothing else
should build these headers by hand.**

| Main | Clinical API |
| --- | --- |
| `users.business_id` (int) | `X-Tenant-Id` (string, from `Business.entity_code`) |
| `users.branch_id` (int) | *no equivalent* — Clinical is tenant-scoped only |
| `clients.client_id` (string) | `global_client_id` / `{patientId}` |
| `clients.visit_id` (string) | `visit_id` |
| `users.permissions` (array) | `X-User-Roles` / JWT `roles` claim |

A business with no `entity_code` maps to `TENANT-{id}` rather than falling into
`DEFAULT`. Pooling unmapped businesses into a shared tenant would let one
facility read another's charts.

---

## 4. Error handling

`ClinicalApiClient` maps §6 statuses onto typed exceptions so callers branch on
a type, never on a message string.

| Exception | Status | What the UI should do |
| --- | --- | --- |
| `ClinicalSafetyBlockException` | 422 `CDSS_HARD_BLOCK` | Show the blocks, collect an override reason code, resend |
| `ClinicalRuleRefusedException` | 422 (other) | Show the message; `requiresExternalFulfilment()` → offer referral |
| `ClinicalAccessDeniedException` | 403 | `requiresBreakGlass()` → offer break-glass; otherwise explain |
| `ClinicalChartLockedException` | 409 | Chart locked = never retry. In-flight = retry shortly |
| `ClinicalBiometricRequiredException` | 428 | Device must sign a challenge and retry |
| `ClinicalAuthException` | 401 | Never retry. `requiresIdentityToken()` → switch to JWT |
| `ClinicalUnavailableException` | 503 / transport | Retry with backoff |

**A 422 is not just bad input.** It is also how the deterministic safety engine
says no. Do not collapse these into a generic error banner.

Every exception carries `requestId()`. Log it — it is on every Clinical log line
and it is the fastest route to an answer when something goes wrong.

---

## 5. Idempotency

Opt-in, per §7. A caller that sends no key gets no protection.

Already keyed:

| Action | Key |
| --- | --- |
| MAR administer / hold / refuse | `mar-dose-{id}-{action}` |
| Observation capture | `obs-{patient}-{cde}-{timestamp}` |
| Care claim | `care-claim-{user}-{patient}-{role}` |
| Prescribing | supplied by the caller |

Prescribing is the one the caller must own. `MedicationOrdersPanel` generates a
UUID on mount and **holds it across the CDSS-override and external-fulfilment
retries** — those are continuations of one clinical decision, not new ones — then
regenerates it once an order is actually placed.

The failure this prevents is ordinary: a ward tablet loses signal mid-request,
the client retries, and without a key the patient receives the dose twice.

---

## 6. What Main owes Clinical

### Inbound (Clinical calls us)

| Route | Purpose |
| --- | --- |
| `POST /api/v1/events` | §12 event stream. De-duplicated on `event_id` |
| `POST /api/v1/catalogue/resolve` | §14 — resolve a generic term to a SKU |
| `GET /api/v1/catalogue/items/{code}` | §14 — single SKU fetch |

Both are behind `clinical.service` middleware (`VerifyClinicalServiceKey`),
which fails closed when no keys are configured.

> **The catalogue lookup blocks all ordering.** §14 lists it as "contract
> proposed, not built by Main" — Clinical cannot resolve a clinician's generic
> term into a SKU without it, so nothing can be prescribed until it is reachable.
> It is now built, and it shares its matching logic with
> `ClinicalTranslatorEngine::resolveDrug()` so both drivers resolve drugs
> identically.

Two honest gaps in that contract, carried over from the local engine:

- `Item` has no `is_offer_item` flag, so `is_available` reports "active
  catalogue row", not a pharmacy-availability check. Inventory must add the
  column before that can mean more.
- `other_names` is a plain string, not a JSON array, so alternative-name
  matching is a substring search.

### Outbound (we call Clinical)

`ClinicalMainNotifier` — all best-effort:

- `encounterCreated()` — call whenever a visit opens. `previous_visit_id` is
  what carries a returning outpatient's pending orders onto the new visit.
- `entitlementGranted()` — call on package purchase.
- `linkInfant()` — call after registering an infant from an
  `INFANT_REGISTRATION_REQUESTED` event.

**These deliberately never throw.** Refusing to open a visit because the
clinical module is down would take the registration desk offline for an outage
in one subsystem. A dropped notification means someone reprints a barcode; a
blocked registration desk is a hospital-wide incident.

> Not yet wired into a caller. `encounterCreated()` needs to be invoked wherever
> Main opens a visit — see §8.

---

## 7. Finding gaps in the deployed service

All 194 documented routes are catalogued in `ClinicalEndpointCatalog`. Two
commands work off it.

### `clinical:probe` — what does the service actually honour?

```bash
php artisan clinical:probe
php artisan clinical:probe --group=Settings
php artisan clinical:probe --patient=CL-00001234 --ward=ICU --show-skipped
php artisan clinical:probe --json=storage/clinical-probe.json
```

**Only GET endpoints are ever called.** Probing a live hospital API by
administering a MAR dose is not a test, so the catalog marks each route
`safe` and the command never invokes a write. A test asserts this.

Results are classified by what they say about the *endpoint*, not about your
request:

| Status | Meaning | Gap? |
| --- | --- | --- |
| `OK` | Live and answering | no |
| `GATED` | 403/428 — exists, a gate refused. Normal for P/Z routes | no |
| `NO_RECORD` | 404 on a path where we supplied a sample id | no |
| `MISSING` | 404/405 on a path with **no** id — the route is absent | **yes** |
| `NOT_IMPLEMENTED` | 501 | **yes** |
| `DEPENDENCY` | 503 — exists, but something it needs is unconfigured (§14) | reported separately |
| `AUTH` | 401 — check `CLINICAL_SERVICE_KEY` | **yes** |
| `SKIPPED` | Needs a sample id you did not supply | no |

The 404 split is the important one. Without a sample id a 404 means the route
does not exist; with one it almost certainly means the id matched nothing.
Conflating them fills the report with false alarms.

The command exits non-zero on `MISSING` / `NOT_IMPLEMENTED`, so it works as a
CI contract check. It stops early if `/health` is not ok — every endpoint would
otherwise report `MISSING` against a dead service, which would be a lie.

### `clinical:call` — poke a single endpoint

```bash
php artisan clinical:call --list=maternity          # what exists
php artisan clinical:call GET clinical/security/context
php artisan clinical:call GET clinical/patients/CL-00001234/observations --data='{"limit":5}'
php artisan clinical:call POST clinical/orders/translate --data='{"requested_term":"ceftriaxone"}' --confirm
```

Non-GET requires `--confirm`. Errors print the `request_id`, which is what
Clinical's support will ask for.

### Suggested first pass

1. `php artisan clinical:probe --group=Settings` — service-key routes only, no
   patient data needed. If these fail, the problem is credentials or tenancy,
   not clinical logic.
2. `php artisan clinical:call GET clinical/security/context` — confirms
   identity, roles, tenant and on-premises detection in one call.
3. `php artisan clinical:call POST clinical/orders/translate --data='{"requested_term":"paracetamol"}' --confirm`
   — the cheapest check that the §14 catalogue lookup is wired. If this fails,
   **no ordering will work at all**.
4. `php artisan clinical:probe --patient=<real id> --ward=<real code>` — full
   read sweep.

---

## 8. Cutover sequence

1. Exchange service keys both directions. Set `CLINICAL_INBOUND_SERVICE_KEYS`
   here and `SERVICE_CLIENT_KEYS=main:<secret>` there.
2. Confirm Clinical can reach `POST /api/v1/catalogue/resolve`. **Ordering does
   not work until this succeeds.**
3. Set `CLINICAL_MODULE_URL` and `CLINICAL_SERVICE_KEY`; leave
   `CLINICAL_DRIVER=local`.
4. Check `ClinicalApiClient::health()` returns ok.
5. Flip `CLINICAL_DRIVER=api` in staging. Verify: chart an observation, place a
   prescription, administer a MAR dose, trigger a CDSS block.
6. Confirm Clinical's `php artisan schedule:run` is running on their side.
   Without it the observation compliance state machine never advances, missed
   rounds are never escalated and chronic patients are never recalled — a
   clinically inert system that still passes its health check.
7. Production. Keep `local` one deploy away.

### Identity cutover

`headers` and `jwt` must switch **together** with Clinical's
`IDENTITY_JWT_REQUIRED`. Once that is true, sending identity headers is refused
with `401 IDENTITY_TOKEN_REQUIRED`, not ignored.

`ClinicalRequestContext::identityToken()` returns `null` today — Main has no
signing key deployed. Selecting `jwt` before that is implemented degrades every
caller to unattributed module traffic, which cannot perform acts requiring a
named clinician. The seam is wired; the token minting is not.

---

## 9. Known gaps

| Gap | Effect |
| --- | --- |
| `ClinicalMainNotifier` not called anywhere | Clinical never learns a visit opened; returning outpatients' orders do not carry over |
| Identity JWT minting not implemented | `CLINICAL_IDENTITY_TRANSPORT=jwt` degrades to module traffic |
| `INFANT_REGISTRATION_REQUESTED` recorded, not acted on | Newborns have no chart until someone registers them and calls `linkInfant()` |
| Remaining Livewire components still on Eloquent | `WardCensusBoard`, `ClinicalProcessPanel`, `DiagnosesPanel`, `AuditTrail`, `RecordConsumption`, `PlaceLabOrder`, `PlaceDiagnosticOrder` need gateways of their own before `api` covers the full UI |
| Consumption emission | Under `api`, Clinical emits consumption facts. `ConsumptionEventBroker` must not also fire, or stock decrements twice |
| Payload shapes are unverified | The 194 routes are wired from the guide's documented shapes. Paths, verbs and headers are asserted by tests; **request/response bodies have only been checked against a live service where the guide gave a worked example.** Expect to adjust field names once `clinical:probe` runs against the real thing |
| Response key guessing | Collection endpoints are unwrapped tolerantly (`items`, `data`, or a bare list). Where the guide did not name the key, `ClinicalResource::rows()` guesses — a wrong guess shows up as an empty array, not an error |

---

## 10. Tests

Run without a database (HTTP-faked):

```bash
./vendor/bin/phpunit --filter="ClinicalApiClientTest|ClinicalApiGatewayTest|ClinicalGatewayServiceProviderTest|ClinicalMainNotifierTest|VerifyClinicalServiceKeyTest|ClinicalApiCoverageTest|ClinicalProbeCommandTest"
```

`ClinicalApiCoverageTest` is the one that keeps the surface honest. It reflects
over every public method on every resource, invokes it against a faked client,
and compares the paths actually requested against the catalog — in both
directions:

- a catalogued endpoint with no resource method fails the test;
- a resource method calling an undocumented path fails the test.

A hand-maintained checklist of 194 routes would drift within a week. This
cannot.

The pre-existing clinical suite (`tests/Feature/Clinical/`) needs MySQL on
`127.0.0.1:3306` with the `kashtre_testing` and `kashtre_testing_clinical`
databases — see `.env.testing`.
