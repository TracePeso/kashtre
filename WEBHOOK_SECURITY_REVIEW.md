# Third-Party Integration & Webhook Signature Verification Review

**Date:** 2026-07-12
**Scope:** Inbound webhook / callback endpoints exposed by the Kashtre monolith and the signature-verification behaviour of each third-party integration.
**Reviewer:** Automated code review (Claude Code)

---

## Summary

| Integration | Inbound route | Signature/Auth verified? | Risk |
|---|---|---|---|
| Yo! Payments (mobile money) | *None wired up* (notification URL points to `webhook.site`) | Verification code exists but is **never called** | High |
| Airtel Money callback | `ANY /api/v1/airtel/callback` | **No** — logs only | High |
| MTN MoMo callback | `ANY /api/v1/airtel/mtncallback` | **No** — logs only | High |
| Insurer authorization decision | `POST /api/v1/insurance/authorization-decision` | **No** — mutates invoice financials | Critical |
| HR Module integration API | `GET /api/hr/*` | **Yes** — `X-HR-API-Key` + `hash_equals` | Low |
| Insurer portal / invoice v1 API | `/api/v1/invoices/*`, etc. | **No middleware applied** to the `v1` group | High |

**Bottom line:** None of the payment/insurer *webhook* callbacks verify a signature. Payment confirmation is not actually driven by webhooks at all — it relies on a scheduled polling command (`payments:check-status` every minute). The only integration with real inbound authentication is the HR module API, which is correctly implemented.

---

## 1. Yo! Payments (mobile money — primary payment gateway)

**Files:** [app/Payments/YoAPI.php](app/Payments/YoAPI.php), [app/Payments/YoPayments.php](app/Payments/YoPayments.php), [app/Payments/YoKeep.php](app/Payments/YoKeep.php), [config/payments.php](config/payments.php)

### Signature verification capability EXISTS but is unused
`YoAPI` ships RSA signature verification for inbound notifications:
- [`verify_payment_notification()`](app/Payments/YoAPI.php#L1285) — concatenates `date_time + amount + narrative + network_ref + external_ref + msisdn`, base64-decodes `signature`, and validates it with `openssl_verify()` against `Yo_Uganda_Public_Certificate.crt`.
- [`verify_payment_failure_notification()`](app/Payments/YoAPI.php#L1309) — same pattern for failure notices.
- [`receive_payment_notification()`](app/Payments/YoAPI.php#L1194) wraps the above.

**Problem:** `receive_payment_notification()` / `verify_payment_notification()` are **never invoked anywhere** in the codebase, and **no route maps to them**.

### The webhook is not pointed at this application
- [config/payments.php:6](config/payments.php#L6) advertises a webhook at `APP_URL . '/api/webhooks/yo-payments'`, **but no such route is defined** in `routes/api.php` or `routes/web.php`.
- The instant-notification URL actually sent to Yo is hard-coded to a public inspection service:
  - [YoPayments.php:20](app/Payments/YoPayments.php#L20) → `https://webhook.site/759c7b75-...`
  - [YoKeep.php:105](app/Payments/YoKeep.php#L105) → `https://webhook.site/396126eb-...`

  Real payment notifications are therefore delivered to a third-party test endpoint, not processed by Kashtre. **This should never ship to production** (leaks transaction data to an external service and confirms nothing).

### How payments are actually confirmed
Confirmation is done by **polling**, not webhooks: `app/Console/Kernel.php` schedules `payments:check-status` every minute, which calls [`ac_transaction_check_status()`](app/Payments/YoAPI.php#L609). This side-steps the webhook trust problem but is slower and adds API load.

### Additional weaknesses in the verification code (if it is ever wired up)
- Uses PHP superglobals `$_POST` directly ([lines 1204-1209](app/Payments/YoAPI.php#L1204), [1288](app/Payments/YoAPI.php#L1288)) instead of the Laravel `Request`, so it cannot be unit-tested and ignores framework input handling.
- `openssl_verify()` is called without an explicit algorithm, defaulting to **SHA-1** — matches Yo's legacy spec but is weak by modern standards.
- Outbound HTTP in [`get_xml_response()`](app/Payments/YoAPI.php#L1265) sets `CURLOPT_SSL_VERIFYPEER = 0` and `CURLOPT_SSL_VERIFYHOST = 0` ([lines 1274-1275](app/Payments/YoAPI.php#L1274)), **disabling TLS certificate validation** and exposing credentials/requests to MITM.

**Recommendation:** Either (a) remove the dead webhook code and document the polling model, or (b) implement a real, CSRF-exempt `POST /api/webhooks/yo-payments` route that calls `verify_payment_notification()`, rejects unverified payloads, and enforces idempotency on `external_ref`. Re-enable TLS verification on outbound calls. Never point the notification URL at `webhook.site`.

---

## 2. Airtel Money callback

**Route:** [routes/custom/airtel_routes.php](routes/custom/airtel_routes.php) → `Route::any("callback", ...)` under prefix `airtel`, mounted inside `/api/v1` → **`ANY /api/v1/airtel/callback`**
**Controller:** [AirtelController@airtelCallback](app/Http/Controllers/API/AirtelController.php#L11)

- **No signature verification.** The handler only does `Log::info('Airtel Callback Received:', $request->all())` and returns `{"status":"success"}`.
- Publicly reachable, no auth, no CSRF (API middleware group). Accepts **any** HTTP method (`Route::any`).
- Comment `// Perform any necessary processing here...` confirms it is a stub.

Note: outbound Airtel requests *do* sign correctly — [Airtel::makePayment](app/Payments/Airtel.php#L131) sends `x-signature` / `x-key` headers ([lines 144-145](app/Payments/Airtel.php#L144)) from `config/services.php`. Only the *inbound* callback is unverified.

**Recommendation:** Verify Airtel's callback authenticity (Airtel supports response signing / a shared secret) before trusting any transaction-status field, and restrict to `POST`.

---

## 3. MTN MoMo callback

**Route:** [routes/custom/mtn_routes.php](routes/custom/mtn_routes.php) → `Route::any("mtncallback", ...)` under prefix `airtel` → **`ANY /api/v1/airtel/mtncallback`**
**Controller:** [MTNController@mtnCallback](app/Http/Controllers/API/MTNController.php#L12)

- **No signature verification.** Logs the payload and returns success, identical to the Airtel stub (even the error log says "Airtel Callback Error").
- **Route bug:** the MTN callback is nested under the `airtel` prefix, so the path is `/api/v1/airtel/mtncallback` rather than an `mtn`-namespaced route. Cosmetic but confusing.

**Recommendation:** Add MTN's callback verification (e.g. `X-Callback-Signature` / API-user + API-key validation per MTN Open API), fix the route prefix, restrict to `POST`.

---

## 4. Insurer authorization-decision callback — **most serious**

**Route:** [routes/api.php:115](routes/api.php#L115) → `POST /api/v1/insurance/authorization-decision`
**Controller:** [InvoiceController@receiveAuthorizationDecision](app/Http/Controllers/API/InvoiceController.php#L422)

- **No signature, no API key, no auth of any kind.** The `v1` route group has **no middleware** ([routes/api.php:80](routes/api.php#L80)).
- It validates the *shape* of the body but not its *origin*, then **mutates invoice financials**: sets authorization status to `approved`, writes `insurance_total`, `client_total`, `insurance_authorized_at`, and the authorization reference straight from the request body ([lines 449-478](app/Http/Controllers/API/InvoiceController.php#L449)).

**Impact:** Anyone who can reach the endpoint and guess/enumerate a `kashtre_invoice_id` can approve an insurance authorization and set the insurer-vs-client split to arbitrary amounts — a direct financial-integrity and fraud risk.

**Recommendation:** Require authenticated + signed requests from the insurer (HMAC signature over the raw body with a per-insurer shared secret, or the existing `AuthenticateApiKey` middleware). Validate that the caller is authorized for that specific invoice's insurer.

---

## 5. HR Module integration API — correctly secured (reference example)

**Routes:** [routes/api.php:70-78](routes/api.php#L70) → `/api/hr/*`, protected by the `hr.api` middleware.
**Middleware:** [VerifyHrApiKey](app/Http/Middleware/VerifyHrApiKey.php)

- Reads `X-HR-API-Key`, compares against `config('services.hr_module.api_key')` using **`hash_equals()`** (constant-time) with proper empty/`is_string` guards ([lines 13-18](app/Http/Middleware/VerifyHrApiKey.php#L13)).
- This is the right pattern for a shared-secret integration. Caveat: it is a **static shared key** (no per-request signature or replay protection), and these are read-only GET data endpoints rather than a state-changing webhook, so the risk profile is lower.

There is also a more capable [AuthenticateApiKey](app/Http/Middleware/AuthenticateApiKey.php) middleware (key+secret lookup against the `ApiKey` model) — but note it is **not applied** to the payment/insurer `v1` routes below.

---

## 6. Related exposure — unauthenticated invoice/insurer `v1` endpoints

The entire `Route::prefix('v1')` group ([routes/api.php:80-116](routes/api.php#L80)) has **no middleware**, exposing state-changing endpoints without authentication, including:
- `POST /api/v1/invoices/{invoiceId}/mark-paid`
- `POST /api/v1/businesses/{businessId}/third-party-vendors/{id}/insurer-portal-payment`
- `GET  /api/v1/invoices/insurance-company/{id}` and various financial/ledger reads.

These are described as "for third-party vendors / insurer portal" but are effectively public. While not webhooks, they are third-party integration routes and share the same root cause as finding #4.

**Recommendation:** Wrap the `v1` group in `AuthenticateApiKey` (or equivalent) and scope each request to the caller's `business_id` / insurer.

---

## Consolidated recommendations (priority order)

1. **Authenticate the insurer authorization-decision callback (#4)** — it changes money and is fully open today.
2. **Lock down the `v1` group (#6)** with `AuthenticateApiKey` and per-tenant scoping.
3. **Verify Airtel (#2) and MTN (#3) callbacks** before trusting them; restrict to `POST`.
4. **Fix the Yo! Payments webhook story (#1):** remove `webhook.site` URLs, and either delete the dead verification code or wire a real signature-verified endpoint; re-enable outbound TLS verification.
5. Add **idempotency/replay protection** (dedupe on provider transaction reference) to any callback that records or confirms payment.
6. Standardise on a signature scheme (HMAC-SHA256 over the raw request body + timestamp) for all future partner webhooks.
