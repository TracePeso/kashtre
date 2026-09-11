# KashTre Clinical Module — v6.1 EDD Volumes: API Guide for Main

**Audience:** Main Module developers building UI screens against the KashTre Clinical v6.1 EDD
work (15 supplemental volumes).

**Companion document, not a replacement.** Authentication, tenancy, the response envelope,
error codes, idempotency and access gates are all unchanged — see
[API_GUIDE.md](API_GUIDE.md) §§1–8 and use them as-is. This document only covers what the v6.1
EDD engagement added or changed.

> **Read this whole page before you scope any v6.1 UI work.** Of the 15 volumes implemented,
> **exactly one** (Care Transitions, Volume 8) ships as a fully live, Main-callable endpoint group
> today. One more volume (Assurance, Volume 9) adds a single new endpoint. One more (Operations,
> Volume 11) changes the *behaviour* of endpoints you already call, with no new route. The other
> 12 volumes are installed, migrated and covered by their own test suites, but **have no HTTP
> endpoint at all yet** — there is nothing for a UI to call. Building screens against those now
> would mean building against nothing.
>
> **This is a second, separate body of work from the "15 EDD Volumes" above — read [§6](#6-srd-v61-phases-1-to-5-live-now)
> if that's what you're here for.** The KashTre Clinical Module v6.1 SRD (a different, larger
> document than the EDD volume packages — 10 "Phases" covering the whole functional baseline) is
> being implemented phase by phase, separately from the 15 Volumes. Unlike most of the Volumes
> above, **everything in §6 is live and callable today.**

---

## Contents

- [Status at a glance](#status-at-a-glance)
- [1. Live now: Care Transitions (Volume 8)](#1-live-now-care-transitions-volume-8)
- [2. Live now: Break-glass independent review (Volume 9)](#2-live-now-break-glass-independent-review-volume-9)
- [3. Internal-only changes: you cannot see them, but should know about them](#3-internal-only-changes-you-cannot-see-them-but-should-know-about-them)
- [4. What "not yet exposed" means, concretely](#4-what-not-yet-exposed-means-concretely)
- [5. Installed, not yet exposed](#5-installed-not-yet-exposed)
- [6. SRD v6.1 Phases 1 to 10 (complete)](#6-srd-v61-phases-1-to-10-complete)
- [Getting help](#getting-help)

---

## Status at a glance

| # | Volume | Domain | Status for Main |
| --- | --- | --- | --- |
| 1 | Foundation | Tenant/identity/audit/idempotency/outbox plumbing | **Internal only.** Folded into existing endpoints (see [§3](#3-internal-only-changes-you-cannot-see-them-but-should-know-about-them)). Nothing new to call |
| 2 | Observation Plan | Scheduled observations, due/overdue tracking | **Not exposed.** No route |
| 3 | Observation / CDE | Atomic observation capture | **Not exposed.** Existing `/clinical/observations` endpoints are separate, pre-existing code |
| 4 | Forms | Structured clinical forms | **Not exposed.** No route |
| 5 | Scoring | Clinical scoring engines | **Not exposed.** Existing `/clinical/scores/{scoreCode}/calculate` is separate, pre-existing code |
| 6 | Medication/Consumption | MAR, consumption | **Not exposed.** Existing `/clinical/mar/*` is separate, pre-existing code |
| 7 | Results | Diagnostic report ingestion, critical results | **Not exposed.** Your live LIMS/RIS webhook pipeline is untouched — see [§5](#5-installed-not-yet-exposed) |
| **8** | **Transitions of Care** | **Admission/transfer/discharge/referral governance** | ✅ **Live now** — [§1](#1-live-now-care-transitions-volume-8) |
| **9** | **Assurance** | Break-glass, downtime, consent | ✅ **One live endpoint** (independent review) — [§2](#2-live-now-break-glass-independent-review-volume-9) |
| 10 | Release Assurance | Environment promotion, release gates | **Not exposed.** No route |
| **11** | **Operations (AI governance)** | AI use-case approval | ⚠️ **Changes existing AI endpoints' behaviour** — [§3](#3-internal-only-changes-you-cannot-see-them-but-should-know-about-them) |
| 12 | Interoperability | FHIR exchange, cohorts, quality measures, DHIS2-adjacent | **Not exposed.** Existing `/fhir/*` endpoints are separate, pre-existing code |
| 13 | Engagement | Patient portal, proxy access, messaging, remote monitoring | **Not exposed.** No route. (Also: rebuilt from a corrected spec — see [§5](#5-installed-not-yet-exposed)) |
| 14 | Content Governance | Content versioning, promotion, localization, accessibility | **Not exposed.** No route |
| 15 | DHIS2 Integration | Public-health reporting | **Not exposed.** No route |

---

## 1. Live now: Care Transitions (Volume 8)

A new governance layer for admission/transfer/discharge/referral, sitting **alongside** your
existing `/clinical/transitions/*` endpoints — not replacing them. The old endpoints still do the
actual bed allocation, order halting and chart locking exactly as before. This layer adds three
things that had no equivalent before: a pre-authorization **readiness gate**, **attested versioned
discharge documents**, and re-anchoring of a patient's scheduled-observation obligations when they
move wards.

**Base path:** `/api/v1/clinical/care-transitions` · **Gate:** service key + ZTNA only — **no
automatic care-relationship or chart-lock check at the route layer** (the older
`/clinical/transitions/*` path does have those gates; this one does not, so don't assume the same
403s apply here). Permission checks below still apply.

### Start a transition and run its readiness check

```http
POST /api/v1/clinical/care-transitions
{ "patient_id": "CL-00001234", "encounter_id": "VIS-2026-001245",
  "type": "DISCHARGE",                    // INTERNAL_TRANSFER | INTERFACILITY_TRANSFER |
                                           // DISCHARGE | REFERRAL | TEMPORARY_LEAVE | DEATH
  "from_client_space_id": null,
  "to_client_space_id": null,
  "destination_organization_id": null,    // always optional per validation — but see the
                                           // REFERRAL note below if you omit it
  "planned_at": "2026-08-14T10:00:00Z",
  "reason": "Medically fit for discharge",
  "context": {} }
→ 201 { "data": { "public_id": "01J...", "patient_public_id": "CL-00001234",
                  "encounter_public_id": "VIS-2026-001245", "transition_type": "DISCHARGE",
                  "status": "READY",                // or READINESS_CHECK if blocked
                  "planned_at": "...", "effective_at": null, "completed_at": null,
                  "record_version": 1 } }
```

**Permission required:** `clinical.transition.initiate`. A caller without it gets a plain
`403` with no `error_code` — match on status, not a code, for this one.

**Requires a real `ClientSpace` id, scoped to your tenant.** Sending a `from_client_space_id` or
`to_client_space_id` belonging to another tenant (or that doesn't exist) is refused `422` — this
is the volume's own "cross-tenant destination input is rejected" test, enforced here.

**The response does not carry the readiness detail — call `show` for that.** `status` tells you
`READY` or `READINESS_CHECK` (blocked), but not *why*. Immediately follow with:

```http
GET /api/v1/clinical/care-transitions/{transition}
→ 200 { "data": { ...same fields as above...,
    "readiness_history": [ {
        "outcome": "BLOCK",                 // PASS | WARNING | BLOCK
        "evaluated_at": "...",
        "items": [
          { "check_code": "UNACKNOWLEDGED_CRITICAL_RESULT", "outcome": "BLOCK",
            "is_blocking": true, "evidence": { "unacknowledged_critical_results": 1 } },
          { "check_code": "MEDICATION_RECONCILIATION", "outcome": "WARNING",
            "is_blocking": false, "evidence": { "open_mar_doses": 2 } }
        ] } ] } }
```

**Important: `WARNING`-level items do not block `status: READY`.** A transition can be `READY`
while still carrying warnings — render them anyway. Only `BLOCK` holds the transition at
`READINESS_CHECK`.

**The complete, verified set of `check_code` values.** There are exactly five, from four
contributors — this is not illustrative, it is exhaustive as of this build:

| `check_code` | Outcome | `evidence` shape | Fires when |
| --- | --- | --- | --- |
| `UNACKNOWLEDGED_CRITICAL_RESULT` | **BLOCK** | `{ "unacknowledged_critical_results": N }` | An unacknowledged critical lab/imaging alert exists. The only hard stop |
| `OBSERVATION_PLAN_OVERDUE` | WARNING | `{ "overdue_observations": N }` | A scheduled observation on this visit is overdue |
| `MEDICATION_RECONCILIATION` | WARNING | `{ "open_mar_doses": N }` | An open/due MAR dose exists |
| `ACTIVE_ALERTS` | WARNING | `{ "active_alerts": N }` | An outstanding observation/score/task-sourced alert exists (distinct from the lab/imaging alerts above) |
| `UNRESOLVED_TASKS` | WARNING | `{ "open_work_orders": N }` | An open `WorkOrder` exists for this patient |

A check with nothing to report is simply absent from `items` — there is no "PASS with evidence"
row; `PASS` at the top level just means `items` came back empty.

There is no re-run/refresh endpoint yet: readiness is evaluated once, at `POST`, and again
implicitly is not offered. If something changes on the chart after initiation (an alert gets
acknowledged), there is currently no way to re-trigger the check without starting a new
transition. Flag this to us if your UI needs a "recheck readiness" button — it isn't there today.

**REFERRAL note.** Nothing validates `destination_organization_id` against `type`, so a
`REFERRAL` can be created without one — but if you do that, the automatically-created
`ReferralProjection` (see next paragraph) carries a `null` destination, which is useless
downstream. Always send a real `destination_organization_id` for `REFERRAL`; the API will not
stop you if you don't.

The moment a `REFERRAL`-type transition reaches `status: READY`, a `ReferralProjection` row is
created automatically (`destination_organization_public_id`, `status: "PROJECTED"`) — there is no
separate endpoint for this and no way to opt out of it. There is, however, no endpoint to mark a
referral completed; `PROJECTED` is as far as this volume's shipped code takes it.

### Complete an internal transfer

```http
POST /api/v1/clinical/care-transitions/{transition}/internal-transfer/complete
{ "movement_id": 4821,          // the BedMovement id from the existing bed-move you already did
  "effective_at": "2026-08-14T10:05:00Z" }
→ 200 { "data": { ...transition, "status": "COMPLETED", "record_version": 2 } }
```

**This does not move the bed for you.** The actual bed allocation still happens through your
existing `POST /clinical/beds/{bed}/assign` call. This endpoint only accepts the resulting
`BedMovement` id as evidence that it happened, and re-anchors the patient's Observation Plan
obligations to their new location. Call it **after** the bed move, not instead of it.

**Permission required:** `clinical.transition.authorize`.

Refused `422 CLN_TRANSITION_NOT_TRANSFERABLE` if the transition isn't type `INTERNAL_TRANSFER` or
isn't in `READY`/`AUTHORIZED`/`IN_PROGRESS` status.

### Issue a discharge document

```http
POST /api/v1/clinical/care-transitions/{transition}/discharge-document
{ "sections": {                              // an OBJECT keyed by section code, not a list
    "HOSPITAL_COURSE": "...", "DISCHARGE_MEDICATIONS": "...", "FOLLOW_UP": "..." } }
→ 201 { "data": { "public_id": "01J...", "status": "ATTESTED", "version_no": 1,
                  "attested_at": "2026-08-14T10:06:00Z" } }
```

**`sections` must be a JSON object, not an array** — `{"CODE": "content"}`, keyed by your own
section codes. Sending an array will not populate any section.

**Permission required:** `clinical.discharge.attest`. Refused `422 CLN_DISCHARGE_NOT_READY`
unless the transition is type `DISCHARGE` and status is exactly `READY` — a blocked or already
completed discharge cannot get a document.

**Every document is immutable once attested.** There is no correction endpoint. A wrong document
today means issuing a new one — there is no shipped "amend" or "supersede" action.

### Download the document as a PDF

```http
GET /api/v1/clinical/care-transitions/documents/{document}/pdf
→ 200  Content-Type: application/pdf
```

Same pattern as the existing `external-referrals/{id}/download`.

### Side effect worth knowing: follow-up tasks land in your existing task board

When a discharge or transfer creates a clinical follow-up obligation, it is written as an ordinary
`WorkOrder` row — the same table your existing `GET /clinical/patients/{patientId}/work-orders`
and `GET /clinical/tasks/*` endpoints already read. You do not need a new integration to see it;
watch for `order_type: "CLINICAL_FOLLOWUP"` and `rule_code: "CLINICAL_TRANSITION_FOLLOWUP"`
starting to appear in lists you already render.

### What this does *not* do

- No completion Actions exist for handover, interfacility transfer, medication reconciliation
  sign-off, or outstanding-results snapshot, despite these being named in the design. `store()`
  records a `ReferralProjection` (destination + status) the moment a `REFERRAL` transition becomes
  `READY`, but there is no shipped way to mark a referral *completed*.
- No abandon/cancel endpoint for a `care-transitions` row (the older `/transitions/{id}/abandon`
  is a different table entirely and does not touch this one).

---

## 2. Live now: Break-glass independent review (Volume 9)

One new endpoint, added to the existing break-glass flow you already integrate with
(`POST /clinical/security/break-glass` — unchanged, same request/response as
[API_GUIDE.md §10.1](API_GUIDE.md#101-care-assignment-and-access)):

```http
POST /api/v1/clinical/security/break-glass/{episode}/review
{ "outcome": "JUSTIFIED",              // JUSTIFIED | UNJUSTIFIED | INCONCLUSIVE
  "finding": "Reviewed against the resuscitation record; override was warranted." }
→ 201 { "data": { "public_id": "01J...", "break_glass_public_id": "01J...",
                  "outcome": "JUSTIFIED", "reviewed_at": "2026-08-14T10:10:00Z" } }
```

**Permission required:** `clinical.break_glass.review` — typically a governance/Medical Director
role. A caller without it gets a plain `403`, no `error_code`.

### ⚠️ A gap you'll hit immediately: there is no supported way to get `{episode}`

`{episode}` is the **public ID of a new internal record** (`BreakGlassEpisode`) created
automatically every time someone exercises break-glass — but neither
`POST /clinical/security/break-glass`'s response nor `GET /clinical/security/break-glass`'s log
exposes this id anywhere. The existing break-glass response still only returns the old numeric
`grant_id`. **There is currently no endpoint that lists `BreakGlassEpisode` rows or their public
IDs at all.**

Practically: **do not build a "review this override" screen against this endpoint yet.** There is
no data source in the API today that would let your UI know which episode id to send. Raise this
with us — either the break-glass response needs to start returning the episode's `public_id`, or a
list endpoint needs to exist, before this capability is usable end-to-end.

---

## 3. Internal-only changes: you cannot see them, but should know about them

### Volume 1 (Foundation) — audit trail storage moved, contract unchanged

`GET /clinical/audit-trail` and `GET /clinical/audit-trail/verify` behave identically from your
side — same request, same response shape. Internally, new entries are now written to a different
table (`clinical_audit_entries` instead of the legacy `clinical_audit_log`), with a stronger
hash-chain. Nothing for you to change.

### Volume 11 (Operations) — every existing AI endpoint now checks use-case governance first

These five endpoints, which you already call, now assert an AI use-case is "registered,
risk-rated and active" before doing anything else:

```
POST /clinical/scratchpad/{note}/extract-observations
POST /clinical/scratchpad/{note}/extract-intent
POST /clinical/ai/icd11-suggest
POST /clinical/ai/summarize-observations
POST /clinical/ai/recommend-protocol
```

**In normal operation this changes nothing** — all five use cases ship pre-registered and
`ACTIVE`. But if a facility administrator ever deactivates or prohibits one (there is currently
**no exposed endpoint to do this** — it can only happen directly against the database today), the
call now fails **`422`, with no generic `error_code`** — only `errors.use_case_code` naming which
one, and a `message` reading either:

```jsonc
{ "message": "AI use case [ICD11_SUGGESTION] is not registered or not active.",
  "errors": { "use_case_code": "ICD11_SUGGESTION" } }
```
```jsonc
{ "message": "AI use case [ICD11_SUGGESTION] is prohibited and cannot execute.",
  "errors": { "use_case_code": "ICD11_SUGGESTION" } }
```

Match on `message` text if you need to distinguish this from any other `422`, since there is no
dedicated code to branch on. Since there is no admin surface for this yet, treat it as a
theoretical case for now, not something your error handling needs to specially design around
today.

---

## 4. What "not yet exposed" means, concretely

For the 12 volumes not covered above, the underlying package is installed, migrated against real
MySQL, and passes its own test suite — but **no host Controller and no route exist**, so there is
nothing at any URL for your UI to call. This is not a partial or flaky API; it is the literal
absence of one. Building a screen against, say, Content Governance or DHIS2 today has no endpoint
to point at.

Getting any of these to the point Main could integrate needs, at minimum: a host Controller,
route registration, a decision on whether it replaces, sits alongside, or has no live host
pipeline to reconcile with (as Volume 8 required for beds/transitions and Volume 9 for
break-glass), and — for several volumes — resolution of an unbound Contract that currently has no
real implementation behind it (e.g. Volume 15's DHIS2 client needs an actual target instance and
credentials; Volume 13's notification/telehealth/device-telemetry gateways need real providers).

---

## 5. Installed, not yet exposed

Two volumes deserve a specific note beyond "no route yet":

- **Volume 7 (Results).** Your live LIMS/RIS webhook pipeline
  (`DiagnosticWebhookController` → `LimsIntegrationProxyService`/`RisIntegrationProxyService`) is
  **completely unaffected** by this volume's package. It is not a staged replacement waiting on a
  cutover — it was deliberately left uninstalled from your live traffic. Nothing changes for you
  here now or when this volume is eventually wired in without a further conversation first.
- **Volume 13 (Engagement — patient portal, proxy access, messaging, remote monitoring).** The
  shipped source for "Volume 13" in the original EDD package turned out to be a mislabeled copy of
  a different volume; what's installed under `kashtre/clinical-engagement` was rebuilt from the
  EDD's own corrected specification. It covers the domain the volume number is supposed to mean
  (patient portal / proxy / messaging / remote monitoring), fully tested, but — like the rest of
  this table — has no host endpoint yet.

---

## 6. SRD v6.1 Phases 1 to 10 (complete)

A **second, separate body of work from the 15 EDD Volumes above.** The KashTre Clinical Module SRD
v6.1 is a different, larger document than the EDD volume packages — 10 "Phases" covering the whole
functional baseline (Foundation/Authorization, Patient/Encounter Context, Clinical Documentation,
Problems/Care Planning, Orders, Medication, Observations, Diagnostics, Transitions, and a
consolidated cross-cutting Phase 10) — implemented phase by phase; all 10 have now been audited.
**Unlike most of the Volumes above, everything in this section is live and
callable today**, at the ordinary `/api/v1/clinical/*` paths alongside everything already in
**[API_GUIDE.md](API_GUIDE.md#1012-governance-client-space-eligibility-privileges-delegation-sensitivity)**
— that's the canonical reference for exact request/response shapes; this section is the map of
what's new and why each piece looks the way it does.

### Phase 1 — Foundation: governance primitives

| Capability | Endpoints | Status |
| --- | --- | --- |
| Client-space assignment (who may work in a ward) | `/clinical/client-space-assignments*` | Live, but **the enforcement gate is off by default** (`REQUIRE_CLIENT_SPACE_ASSIGNMENT=false`) — no request is refused for lacking one yet |
| High-risk privileges (9 named categories) | `/clinical/privileges*` | Live. Not yet wired into any specific order/MAR/discharge endpoint's own authorization check |
| Delegation & cross-cover | `/clinical/delegations*` | Live |
| Sensitivity restrictions | `/clinical/sensitivity-restrictions*` | Live as a primitive — not yet consulted by search, alerts or export endpoints |
| Published permission catalogue (163 atomic `clinical.*` codes) | `/settings/permission-catalog*` | Live — see below |

Two things explicitly **not** built, both because building them would mean either breaking a live
v6.0 system or fabricating a Main Module contract that doesn't exist here: the SRD reassigns the
unit master and the timezone engine to Main Module ownership, but this host's live production
system owns both locally today — the SRD's own gap register marks this reconciliation as
unresolved and Release Blocking, so it was left alone rather than guessed at. (The Unit Engine
half of that reconciliation is now at least *possible* — see "Main Unit Engine / Time Engine
consumption" below — but the local table it would replace is still the live one everything reads.)

#### ⚠️ Your own bearer token is now actually verified — this changes how you should authenticate

Before this pass, Clinical only knew how to verify a locally-signed RS256 JWT — a format Main has
never actually issued. Any real Main-issued session token sent as `Authorization: Bearer ...` was
silently unverifiable and fell back to the older forwarded-header transport
(`X-User-Id`/`X-User-Roles`, API_GUIDE.md §1). **That is fixed.** Clinical now recognizes an opaque
Sanctum token by shape (`{numeric-id}|{plaintext}`, no dots) and calls your own
`POST /v1/auth/introspect` to verify it, using the same `X-Api-Key` credential already configured
between us. Concretely:

- **Forward your caller's own session token as `Authorization: Bearer <token>`** wherever you
  already know it, instead of (or alongside) the `X-User-Id`/`X-User-Roles` headers. Clinical will
  identify the user, their duty roles and their tenant from your own introspection response — no
  separate registration step needed on your side.
- **A token that introspects `active: false`, or that we can't reach you to verify, is now
  refused outright (`401`)** — including if a valid-looking `X-User-Id` header rides alongside it.
  We deliberately do not fall back to trusting the header once a token has been presented and
  failed to verify; a request with no token at all still uses the header transport exactly as
  before.
- **Result caching:** a positive introspection is cached for 60 seconds per token (configurable
  our side). If you suspend a user or revoke a session, expect up to ~60s before Clinical reflects
  that — there is currently no push-invalidation from your side to shorten this window; flag it to
  us if that's too slow for a workflow you're building.
- This only changes *how identity is established*; every existing endpoint, permission and
  response shape is unaffected.

#### New: Main Unit Engine / Time Engine consumption

Three of your real Time Engine endpoints and your Unit Engine's core catalogue/conversion
endpoints are now consumed from Clinical, on **the calling clinician's own forwarded bearer
token** — not our service key — because your `unit-engine/*`/`time-engine/*` routes sit behind
`auth:sanctum`, unlike everything else we call on you.

```http
GET  /api/v1/clinical/main/unit-engine/units?q=mmol
GET  /api/v1/clinical/main/unit-engine/units/{unitPublicId}
POST /api/v1/clinical/main/unit-engine/conversions/preview   { "value": "16.0",
     "source_unit_public_id": "MMOL_L", "target_unit_public_id": "MG_DL",
     "context": { "analyte_public_id": "GLUCOSE" } }          // preview: no side effects
POST /api/v1/clinical/main/unit-engine/conversions/execute    // same body — the committing form
→ 200 { "data": { "status": "SUCCESS", "source": {...}, "target": {...},
                  "provenance": { "rulePublicId": "...", "ruleVersion": 1 } } }
// 422 UNIT_CONVERSION_UNVERIFIED if your Unit Engine itself returns 422 (dimensional
// mismatch, missing context) — we never silently relabel a value under a different unit

GET  /api/v1/clinical/main/time-engine/now
GET  /api/v1/clinical/main/time-engine/timezones
POST /api/v1/clinical/main/time-engine/resolve   { "facility_id": "FAC-1", "purpose": "CLINICAL_EVENT" }
→ 200 { "data": { "ianaId": "Africa/Kampala", "resolutionPath": "FACILITY", "usedFallback": false } }
```

**Every one of these requires the caller to have authenticated with a real Main bearer token**
(see the introspection note above) — calling any of them with only the service key or the legacy
header transport returns `422 MAIN_ON_BEHALF_OF_TOKEN_REQUIRED`, since there is no user session to
act on behalf of. Nothing in Clinical calls these endpoints internally yet (no CDE/observation
write path has been migrated off the local unit master) — they exist and are tested, but today
only reachable if you call them directly through Clinical.

#### New: published permission catalogue

```http
GET /api/v1/settings/permission-catalog                          // all 163, or filter:
GET /api/v1/settings/permission-catalog?resource_family=note      // 14 clinical.note.* codes
GET /api/v1/settings/permission-catalog?risk_tier=CRITICAL
GET /api/v1/settings/permission-catalog?search=cosign
→ 200 { "data": [ { "code": "clinical.note.sign", "resource_family": "note", "action": "sign",
          "risk_tier": "CRITICAL", "default_scope": "ASSIGNED_PATIENTS",
          "requires_credential": true, "break_glass_eligible": true, "audit_level": "FULL",
          "introduced_in_phase": "Phase 3", "status": "ACTIVE" }, ... ],
        "meta": { "count": 163, "active_count": 163, "families": ["note", "order", ...] } }

POST /api/v1/settings/permission-catalog
{ "code": "clinical.example.approve", "description": "...", "risk_tier": "HIGH" }
→ 201 { "data": { ..., "resource_family": "example", "action": "approve" } }
// resource_family/action are always derived server-side from the code, not accepted as input

POST /api/v1/settings/permission-catalog/{id}/deactivate   {}   // retire — never deleted
```

**This is the atomic-code catalogue only — Clinical does not assign these codes to users.**
Per CLN-OWN-011's own split: Clinical publishes what a code means (risk tier, whether it requires
a credential or is break-glass-eligible); you register it and assign it to users/title bundles on
your side. **A real gap found while building this, worth knowing before you build against it: most
of these 163 codes are not actually enforced anywhere yet** — several endpoints that clearly
should check one (encounter close/reopen, care-relationship create/end, ward census/patient-list
reads) currently accept any caller who clears the outer service-key/care-relationship gate. Don't
assume a `403` for "wrong permission" on those specific actions today; that enforcement is planned,
not yet wired, and is being tracked and closed incrementally rather than landed all at once.

### Phase 2 — Patient/encounter context

| Capability | Endpoints | Status |
| --- | --- | --- |
| Positive patient identification (generalizes MAR's 5-Rights to 7 more action types) | `POST /clinical/patients/{patientId}/identity-confirmations` | Live |
| Identity-concern reporting (report only — no merge) | `/clinical/patients/{patientId}/identity-concerns`, `/clinical/identity-concerns*` | Live |
| Encounter lifecycle: create, transition, closure-check, close, reopen | `/clinical/encounters*` | Live — see below |
| Patient workspace: banner, longitudinal timeline, patient lists | `/clinical/patients/{patientId}/banner`, `/timeline`, `/clinical/patient-lists` | Live — see below |

The SRD's own gap register flags "who owns encounter lifecycle" and "who owns bed administration"
as unresolved — both already have live answers in this host (Main owns `visit_id`; Clinical owns
beds), so neither was rebuilt against the SRD's alternative model. What Clinical *does* own here is
an operational encounter-workspace record layered on top of your `visit_id` (below) — additive,
never a claim to your authority over the encounter concept itself.

#### New: encounter lifecycle

```http
POST /api/v1/clinical/encounters
{ "patient_id": "CL-00001234", "visit_id": "VIS-2026-001245",
  "encounter_class": "INPATIENT",     // OUTPATIENT | EMERGENCY | INPATIENT | DAY_CASE |
                                       // VIRTUAL | HOME_COMMUNITY | OBSERVATION
  "service": "General Medicine", "facility_id": "FAC-1",
  "responsible_clinician_id": 104, "initial_client_space_id": 12 }
→ 201 { "data": { "id": 1, "status": "PLANNED", "minimum_data_pending": false, ... } }
// 422 ENCOUNTER_ALREADY_EXISTS if an active encounter already exists for this patient+visit
// EMERGENCY-class encounters missing service/reason are created anyway with
// minimum_data_pending: true, rather than blocked (CLN-P2-ENC-006)

GET /api/v1/clinical/patients/{patientId}/encounters
GET /api/v1/clinical/encounters/{encounter}

POST /api/v1/clinical/encounters/{encounter}/status   { "status": "ARRIVED", "reason": "..." }
→ 200 { "data": { "status": "ARRIVED", ... } }
// 422 ILLEGAL_ENCOUNTER_TRANSITION with a "permitted" array if the target status isn't
// reachable from the current one — the full state machine is in ClinicalEncounter::TRANSITIONS

GET  /api/v1/clinical/encounters/{encounter}/closure-checks
→ 200 { "data": { "ready": false, "items": { "outstanding_orders": 0,
        "outstanding_critical_results": 1, "open_medication_reconciliations": 0,
        "unsigned_notes": 1, "open_tasks": 0 } } }

POST /api/v1/clinical/encounters/{encounter}/close   { "override": false }
→ 422 ENCOUNTER_CLOSURE_ITEMS_OUTSTANDING (with the same "items" breakdown) unless clear or overridden
→ 422 ENCOUNTER_NOT_FINISHED unless the encounter is already FINISHED (a distinct status
     from CLOSED — closure checks run between the two, CLN-P2-CLS-003)

POST /api/v1/clinical/encounters/{encounter}/reopen   { "reason": "required, non-empty" }
→ 200 { "data": { "status": "IN_PROGRESS", "reopened_at": "...", "reopen_reason": "..." } }
// the original closure event is preserved, never erased — closed_at/closed_by_user_id
// on the encounter row are untouched, and both events remain in status_events history
```

**⚠️ Behavior you should know about if you were ever sending `changed_by_user_id` /
`closed_by_user_id` / `reopened_by_user_id` explicitly:** a real bug was found and fixed this
pass — these request fields used to be trusted outright, so any caller could attribute a status
change, closure or reopen to an arbitrary user id. **Your own verified identity (from the bearer
token or header transport, see Phase 1 above) now always wins when present** — the request field
is only used as a fallback for genuine service-to-service calls with no identity to resolve. If
you rely on this field to attribute an action to a specific user, make sure you're authenticating
as that user (forward their token) rather than sending their id as a plain field.

**Not yet enforced:** `clinical.encounter.close`/`.reopen`/`.create` and
`clinical.care_assignment.manage` are published permission codes (see the catalogue above) but are
not yet checked on these endpoints — same status as the rest of the "not yet enforced" note there.

#### New: patient workspace (banner, timeline, lists)

```http
GET /api/v1/clinical/patients/{patientId}/banner?visit_id=...&viewing_user_id=104
→ 200 { "data": {
    "patient_id": "...", "visit_id": "...",
    "encounter": { "id": 1, "encounter_class": "INPATIENT", "status": "IN_PROGRESS",
                   "service": "...", "facility_id": "...", "actual_start": "..." },
    "location": { "client_space_id": 12, "ward_name": "General Ward", "room_number": "..." },
    "responsibility": { ...CareAssignmentService::resolveForPatient() shape... },
    "safety_alerts": [ { "id": 1, "source": "LABORATORY", "alert_label": "...",
                          "severity_tier": "CRITICAL", "created_at": "..." } ],
    "confidentiality": { "restricted": false, "break_glass_active": false } } }
// "confidentiality.restricted" is a plain boolean — the actual sensitivity label/reason is
// never included here regardless of caller authorization (CLN-P2-BNR-004)

GET /api/v1/clinical/patients/{patientId}/timeline?visit_id=...&limit=100
→ 200 { "data": [ { "type": "OBSERVATION", "id": 55, "status": "FINAL",
                     "author_user_id": 104, "occurred_at": "...", "summary": "SPO2" }, ... ] }
// merges notes/observations/orders/problems, ordered by each entry's own clinically
// meaningful time (not created_at) with a deterministic tie-break — but note: no entry
// currently carries which encounter/visit it belongs to, and only visit_id/limit can filter

GET /api/v1/clinical/patient-lists?type=my_patients&user_id=104&role_codes[]=WARD_NURSE
// type: my_patients | my_team | covering_patients | recent_patients
```

Distinct from the existing `GET /clinical/handover` and ward-census endpoints (API_GUIDE.md §2.2,
§10.x) — this is the general-purpose "open a patient's chart" projection, not a shift or ward view.

### Phase 3 — Clinical documentation and the legal record

| Capability | Endpoints | Status |
| --- | --- | --- |
| Documentation type registry (17 pre-seeded families) | `/settings/dictionaries/documentation-types*` | Live, same six-verb dictionary shape as everything else in API_GUIDE.md §10.9 |
| Signable clinical notes: draft → complete → sign → co-sign, plus correct/addendum/entered-in-error | `/clinical/notes*` | Live |

This is genuinely new — the only prior "note" concept was the free-text bedside `scratchpad`
(API_GUIDE.md §10.7), which is unrelated and unchanged. Templates, copy-forward, macros, voice
dictation and AI-assisted drafting are not built yet.

### Phase 4 — Problems, goals and care plans

| Capability | Endpoints | Status |
| --- | --- | --- |
| Longitudinal problem list (distinct from the existing encounter-level `POST /diagnoses`) | `/clinical/problems*` | Live |
| Goals | `/clinical/goals*` | Live |
| Care plans and activities (13 plan types) | `/clinical/care-plans*`, `/clinical/care-plan-activities/{id}/status` | Live |

Terminology/ICD-11 assistance for problems and AI-assisted suggestion are not built yet — the
`code`/`coding_system` fields exist and are ready to receive a real terminology service once one
is wired in.

### Phase 5 — Orders

The live order pipeline (`ClinicalOrder`, the Translator Engine, the CDSS shield, order sets — all
of API_GUIDE.md §10.3) is mature and already in production against its own 4-state status model.
The SRD describes a much richer 12-state model for the same concept; rebuilding the live pipeline
to match would be a breaking rework, not a bug fix, so **it was left untouched**. The one genuinely
new, additive capability built instead:

| Capability | Endpoints | Status |
| --- | --- | --- |
| Verbal and telephone orders | `/clinical/verbal-orders*` | Live |

```http
POST /api/v1/clinical/verbal-orders
{ "patient_id": "CL-00001234", "visit_id": "VIS-2026-001245",
  "communication_method": "TELEPHONE",       // VERBAL | TELEPHONE
  "stated_requester_user_id": 104,           // must differ from recorded_by_user_id
  "recorded_by_user_id": 208,
  "order_content": "Paracetamol 1g IV stat for fever.",
  "read_back_confirmed": true }
→ 201 { "data": { "authentication_status": "PENDING",
                  "authentication_due_at": "...", ... } }

GET  /api/v1/clinical/verbal-orders/pending                 // the authentication queue
POST /api/v1/clinical/verbal-orders/{id}/authenticate
     { "authenticating_user_id": 104, "order_id": 412 }      // only the stated requester may call this
     // 422 VERBAL_ORDER_AUTHENTICATION_REQUESTER_MISMATCH otherwise
POST /api/v1/clinical/verbal-orders/{id}/escalate            // overdue → escalated; no scheduled job drives this yet
```

This does **not** place an order itself — it records the communication and its authentication.
Linking `order_id` at authentication time is how you connect it to an order placed through the
ordinary `POST /orders/medications` flow (or any other family), once one exists.

### Phase 6 — Medication reconciliation and adverse events

The eMAR administration/consumption pipeline itself (§4–§14 of the Phase — MAR schedules,
administrations, wastage, the consumption broker) is already live and mature and was **not**
rebuilt. Two genuinely new capabilities sit alongside it:

| Capability | Endpoints | Status |
| --- | --- | --- |
| Medication reconciliation at admission/transfer/discharge | `/clinical/medication-reconciliations*`, `/clinical/medication-reconciliation-items/{id}/decision` | Live |
| Adverse drug reaction / error / near-miss reporting | `/clinical/medication-adverse-events*` | Live |

```http
POST /api/v1/clinical/medication-reconciliations
{ "patient_id": "CL-00001234", "visit_id": "VIS-2026-001245",
  "reconciliation_type": "ADMISSION",          // ADMISSION | TRANSFER | DISCHARGE
  "performed_by_user_id": 104 }
→ 201 { "data": { "status": "IN_PROGRESS", ... } }

POST /api/v1/clinical/medication-reconciliations/{id}/items
{ "source": "PATIENT_REPORT", "medication_name": "Amlodipine", "dose": "5mg" }

POST /api/v1/clinical/medication-reconciliation-items/{itemId}/decision
{ "decision": "CONTINUE", "decided_by_user_id": 104 }   // CONTINUE|MODIFY|HOLD|STOP|SUBSTITUTE|DEFER_REVIEW|NOT_CURRENT

POST /api/v1/clinical/medication-reconciliations/{id}/complete
// 422 RECONCILIATION_ITEMS_UNDECIDED if any item still has decision: null
```

```http
POST /api/v1/clinical/medication-adverse-events
{ "patient_id": "CL-00001234", "visit_id": "VIS-2026-001245",
  "event_type": "ADVERSE_DRUG_REACTION",   // ADVERSE_DRUG_REACTION|SIDE_EFFECT|MEDICATION_ERROR|NEAR_MISS|THERAPEUTIC_FAILURE
  "description": "Widespread urticaria within 10 minutes of ceftriaxone infusion.",
  "severity": "MODERATE", "reported_by_user_id": 208 }
→ 201 { "data": { "status": "OPEN", "escalated": false, ... } }

POST /api/v1/clinical/medication-adverse-events/{id}/response   { "clinical_response": "...", "outcome": "RESOLVED" }
POST /api/v1/clinical/medication-adverse-events/{id}/escalate   // → status: UNDER_REVIEW, escalated: true
POST /api/v1/clinical/medication-adverse-events/{id}/close      { "outcome": "RESOLVED_NO_SEQUELAE" }
```

Deliberately not built this pass: an independent-double-check requirement for high-alert
medications as its own workflow — `MarAdministration.witnessed_by_user_id` already exists on the
live administration record for this purpose, so no new capability was needed there.

### Phase 7 — Observations

The existing CDE/Template/Schedule pipeline (`Cde`, `CdeGroup`, `CdeTemplate`, `CdeObservation`,
`ObservationSchedule`, `CdeDeviceReading`, plus the Unit Engine and `CdeExecutionEngine`) already
covers most of this phase's structural requirements — the CDE registry, groups/flowsheets, unit
conversion and manual/device capture were **not** rebuilt. One genuine, well-specified gap was
found and closed: `CdeObservation` had only a 2-state `validation_status` (VALIDATED/UNVALIDATED —
a device-import review concept, still unchanged), not the SRD's 8-state clinical-standing
lifecycle.

| Capability | Endpoints | Status |
| --- | --- | --- |
| Observation status lifecycle (REGISTERED/PRELIMINARY/FINAL/AMENDED/CORRECTED/CANCELLED/ENTERED_IN_ERROR/UNKNOWN) | `/clinical/observations/{id}/correct`, `/entered-in-error`, `/cancel` | Live |

```http
POST /api/v1/clinical/observations/{id}/correct
{ "reason": "Transcription error, actual reading was 7.4", "value_numeric": 7.4 }
→ 201 { "data": { "status": "CORRECTED", "supersedes_observation_id": <original id>, ... } }
// the original observation is preserved unchanged and marked AMENDED — never overwritten in place

POST /api/v1/clinical/observations/{id}/entered-in-error   { "reason": "Captured against the wrong patient chart." }
POST /api/v1/clinical/observations/{id}/cancel             { "reason": "Ordered in error; patient was never drawn." }
// both are terminal — a second status change on an already-terminal observation returns
// 422 OBSERVATION_STATUS_TERMINAL
```

Every new observation still defaults to `FINAL` on capture (the existing manual/device flow
already commits a complete, clinically-available value atomically, so no caller needed to change).
`GET /clinical/patients/{patientId}/observations` (the flowsheet/trend view) now excludes
`ENTERED_IN_ERROR`/`CANCELLED` records, satisfying the SRD's "shall not drive ... trends"
requirement for that view. Retrofitting every other consumer (scoring, alerts, decision support,
the FHIR mappers) to the same exclusion is **not** done this pass — no existing row can carry
either status except through this new workflow, so nothing already running is affected; it's
flagged here rather than silently left unstated.

Two more of the Phase's own correction rules (§17, CLN-P7-COR-004/005) are genuine, larger
integration work deliberately not attempted this pass, and are called out rather than silently
skipped: automatically placing dependent calculated observations, alerts and care-plan outcomes
"into review" after a material correction (no "review" state exists yet on those other models to
place them into), and emitting an idempotent downstream correction event through the outbox for
external consumers. Both would mean touching several other mature subsystems' own models, not
just this one's.

### Phase 8 — Results, diagnostic reports and closed-loop follow-up

The live LIMS/RIS webhook ingestion pipeline (`LimsIntegrationProxyService`,
`RisIntegrationProxyService` — schema validation, patient/order matching, unit resolution) is
mature and was **not** rebuilt; it already covers most of this Phase's ingestion-pipeline concepts.
Two genuine gaps in what happens *after* a result lands were found and closed:

| Capability | Endpoints | Status |
| --- | --- | --- |
| Diagnostic report status lifecycle (Clinical's own review layer, distinct from the source report) | `/clinical/diagnostic-reports/{id}/correct`, `/entered-in-error`, `/cancel` | Live |
| Closed-loop critical-alert follow-up: acknowledgement → review → action → closure as distinct, separately-recorded states | `/clinical/critical-alerts/{id}/review`, `/action`, `/close` (alongside the existing `/acknowledge`) | Live |

```http
POST /api/v1/clinical/diagnostic-reports/{id}/correct   { "reason": "Wrong lung field described; corrected impression issued." }
→ 201 { "data": { "status": "CORRECTED", "supersedes_report_id": <original id>, "report_version": 2, ... } }
// the original report is preserved unchanged and marked AMENDED

POST /api/v1/clinical/diagnostic-reports/{id}/entered-in-error   { "reason": "..." }
POST /api/v1/clinical/diagnostic-reports/{id}/cancel              { "reason": "..." }
// both terminal — a second change returns 422 REPORT_STATUS_TERMINAL
```

```http
POST /api/v1/clinical/critical-alerts/{id}/acknowledge   {}
POST /api/v1/clinical/critical-alerts/{id}/review        { "review_notes": "Repeat sample sent; renal team notified." }
// 422 ALERT_NOT_ACKNOWLEDGED if called before acknowledgement (CLN-P8-GOV-002:
// technical receipt is not clinical review)
POST /api/v1/clinical/critical-alerts/{id}/action        { "action_taken": "IV calcium gluconate given per protocol." }
POST /api/v1/clinical/critical-alerts/{id}/close         { "closure_reason": "Potassium normalized on repeat." }
// 422 ALERT_NOT_REVIEWED if called before review — closure always needs a documented rationale
```

Lab results that arrive as atomic values are ingested straight into `cde_observations`
(`CdeExecutionEngine::captureObservation`, called from `LimsIntegrationProxyService`), so they
already inherit the Phase 7 observation status lifecycle above — no separate report-status
handling was needed for that path. Deliberately not attempted this pass, and flagged rather than
silently left undone: automatically placing dependent calculations/alerts/care-plan outcomes "into
review" after a material correction (CLN-P8-GOV-007-equivalent), and breaking the ingestion
pipeline's existing validation/rejection handling out into the SRD's own named
RECEIVED→…→QUARANTINED/REJECTED stage labels — today's webhook-level HTTP validation and logging
already perform the equivalent job without those exact state names, and relabelling a live,
integrated pipeline is a bigger rework than this pass's scope.

### Phase 9 — Handover, transitions, discharge and continuity of care

Internal transfer, discharge readiness and attested discharge documents are already substantially
covered by the earlier EDD Volume 8 binding pass — `POST /care-transitions*` above ([§1](#1-live-now-care-transitions-volume-8))
— and were **not** revisited here. Volume 8's own 8-state internal-transfer model
(REQUESTED→…→COMPLETED, Phase 9 §7) is richer than what `CareTransitionController` builds on top
of the live bed-management pipeline; rebuilding that pipeline's state machine is out of scope for
the same reason it was out of scope for Volume 8 — it would be a breaking rework of a live system,
not a bug fix.

The one clean, well-specified, genuinely uncovered gap: a structured, accountable **Handover
Record** (§2/§4) for shift and service handover — the point the SRD makes explicitly is that a
patient movement or a sent message is not proof that clinical responsibility actually transferred
(CLN-P9-GOV-003).

| Capability | Endpoints | Status |
| --- | --- | --- |
| Handover Record: prepare → send → acknowledge, with versioned amendment | `/clinical/handovers*` | Live |

```http
POST /api/v1/clinical/handovers
{ "patient_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "encounter_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
  "intended_receiver_id": "01ARZ3NDEKTSV4RRFFQ69G5FAX",
  "content": { "situation": "Post-op day 1, stable", "plan": "Continue current management" } }
→ 201 { "data": { "status": "DRAFT", "version_no": 1, ... } }

POST /api/v1/clinical/handovers/{id}/send          {}
→ { "data": { "status": "SENT", "sent_at": "..." } }   // sending is not acceptance (CLN-P9-GOV-003)

POST /api/v1/clinical/handovers/{id}/acknowledge   { "note": "Reviewed, no questions." }
→ { "data": { "acknowledged_by": "...", "acknowledged_at": "..." } }   // this is what actually transfers responsibility

POST /api/v1/clinical/handovers/{id}/amend         { "content": { "situation": "Correction: afebrile" } }
→ 201 { "data": { "version_no": 2, "supersedes_public_id": "<original id>", ... } }
// the original is never edited in place (CLN-P9-HND-004) — only a DRAFT handover with nothing
// sent yet can be edited by simply preparing a fresh one instead
```

This is distinct from the existing `GET /clinical/handover` (API_GUIDE.md §2.2) — that one is a
live, stateless ward projection ("what does the outgoing shift need to know right now"), computed
fresh on every call and never itself accepted by anyone. The Handover Record above is the
accountable event: who prepared it, who it was sent to, and whether the receiver actually accepted
it. Both stay in place, answering different questions.

`clinical_handovers`/`clinical_handover_acknowledgements` are pre-existing EDD Volume 8 package
tables that shipped with no Action ever touching them; `transition_public_id` was also NOT NULL in
the shipped schema, which only fits a formal transfer/discharge — made nullable so a routine shift
handover (no transition, no movement) doesn't need to fabricate one. Discharge summary/patient
instructions versioning, external-transfer disclosure, and continuity-task survival past encounter
closure (§9–§13) are not attempted this pass — each is a substantial capability in its own right,
and the discharge-document half of it already has a start via `IssueDischargeDocument`
([§1](#1-live-now-care-transitions-volume-8)).

### Phase 10 — Specialty extensions, interoperability, reporting and release assurance

No new code from this pass. Phase 10 is explicitly a consolidation chapter — its own Document
Control table says so directly: **"Implementation: Functional specification only. Laravel
migrations, policies, services, APIs, queues and deployment topology belong in the EDD"**, and its
"Consolidation rule" states it "may strengthen cross-cutting controls but shall not create
alternate authorization, signature, correction, unit, order, medication, result or transition
semantics" beyond Phases 1–9. Its 24 sections (specialty-extension governance, FHIR/interoperability
conformance, reporting/analytics, privacy, zero-trust security, audit/observability, performance,
resilience/DR, data retention, change governance, deployment/release gates, migration, testing
strategy, accessibility, operations/incident management, final permissions, traceability) describe
*properties the whole system must have*, not a discrete feature to add — and every one of them is
already satisfied by something that exists:

- The architectural discipline every phase above already follows: tenant scoping (`BelongsToTenant`
  + `TenantScope` on every model), permission checks (`ClinicalIdentity::hasPermission()`),
  tamper-evident hash-chained audit (`AuditEntry`/`AuditTrailService`), idempotent writes
  (`EnforceIdempotency`), opaque identifiers (ULID for EDD-package records, tenant-scoped
  auto-increment elsewhere — never a bare sequential ID used as an authorization boundary), and
  versioned/effective-dated configuration (`ManagesDictionaryEntries`, `HasActivationStatus`).
- The EDD Volumes already completed earlier in this engagement that this Phase's sections map onto
  almost one-to-one: Volume 9 (Assurance — break-glass, consent, downtime), Volume 10 (Release
  Assurance — deployment gates, migration rehearsal), Volume 11 (Operations — AI use-case
  governance), Volume 12 (Interoperability — FHIR exchange, cohorts, quality measures), Volume 14
  (Content Governance — versioning, promotion, localization, accessibility), Volume 15 (DHIS2 —
  public-health reporting).
- Its own "Registered Phase 10 Gaps" table — like every prior phase's gap register — marks its
  items (approve the specialty-extension catalogue, approve interoperability profiles, ...) as
  requiring organizational governance sign-off, not code, and "Blocking" in the same sense as
  every other phase's deferred items: a decision for the PM/governance body, not a bug.

This closes the phase-by-phase SRD v6.1 audit (Phases 1–10).

---

## Getting help

- **Full v6.0-era API contract:** [API_GUIDE.md](API_GUIDE.md) — read this first if you haven't.
- **Why something differs from a specification:** [DECISION_REGISTER.md](DECISION_REGISTER.md)
- **Overall build status:** [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md)

If you need one of the "not yet exposed" volumes prioritised for a real endpoint, say which
screen you're trying to build — that determines which Actions actually need a Controller in front
of them, which is usually a small, scoped piece of work once the target UI is known.
