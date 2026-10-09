# Replacement Clinical worklist bridge

Isolated, disabled by default. This is only Main's layout, session and CSRF bridge for the replacement Clinical worklist. It changes no finance, catalogue, registration, queue status, HR, permissions or business workflow.

When explicitly commissioned, `CLINICAL_REPLACEMENT_UI_ENABLED=true` makes the existing Clinical My Tasks entry redirect to `/clinical/replacement`. That page uses the current Main login, existing `View Ward Census` permission and shared layout. It does not render legacy Clinical task components. POSTs stay same-origin with Main's normal web/CSRF middleware. Main derives tenant and user from the authenticated active user. Service keys are server-only. Redirects and automatic command retries are disabled; the replacement UI retains the original operation identifier to recover an uncertain result.

Only the five existing `tasks.*` operations and four named replacement JavaScript assets are proxied. Clinical independently resolves current Main directory identity and enforces its own task, source and patient authorization. No header or browser-supplied actor grants domain authority.

Activation is a separate reviewed deployment: install the companion Clinical `WorkflowHttpRoutes` and private host composition, configure existing Clinical HTTPS origin/service key and authorized Main directory credential server-side, verify actual source/storage/readiness, then enable this Main flag. The private bootstrap must use replacement services and actual admitted task sources, never test fixtures or old Clinical code. This commit neither deploys nor creates that production qualification. Rollback of this bridge is disabling its flag; no database migration is involved.

Validation: branch CI tests the actual Laravel controller, current identity forwarding, failure/recovery semantics, fixed assets and real CSRF middleware entirely in memory. Clinical's companion suite tests the gateway and router through actual PHP/SQLite tasks with synthetic Main sessions, response loss, reopen, milestones and Chrome desktop/mobile. Live Main login and live task/provider commissioning are not claimed.
