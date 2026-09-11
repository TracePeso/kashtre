# System Logging & Audit Trail Review

**Date:** 2026-07-12
**Scope:** Application-wide audit of how user actions and system events are logged.
**Verdict:** ⚠️ **The audit trail is largely non-functional.** A dedicated audit-log
infrastructure exists but is wired to only 3 of ~107 models, authentication events
are never recorded, and the secondary audit helper is broken code that cannot run.
Most "logging" in the codebase is unstructured debug output to `laravel.log`, which is
not a queryable, tamper-evident user-action audit trail.

---

## 1. Logging Architecture (as designed)

The app contains **two separate, overlapping audit mechanisms** plus general file logging:

| Mechanism | Storage | Trigger | Status |
|-----------|---------|---------|--------|
| `ActivityLog` model + `ModelActivityObserver` | `activity_logs` table | Eloquent model events (created/updated/deleted) | **Partially working** – only 3 models registered |
| `AuditTrait::createAudit()` | `App\Models\AuditLog` (`audit_logs` table) | Manual calls in controllers | **Broken** – model & table do not exist |
| `Log::info/warning/error(...)` | `storage/logs/laravel.log` | Manual, scattered | Working, but **not an audit trail** (1,666 calls, debug-oriented) |

The intended user-facing audit view is the Filament table in
[app/Livewire/AuditLogs.php](app/Livewire/AuditLogs.php), reached via
`/audit-logs` ([routes/web.php:660](routes/web.php#L660)). It reads from `activity_logs`.

---

## 2. Critical Findings

### 2.1 🔴 The activity observer is registered on only 3 of ~107 models
[app/Providers/AppServiceProvider.php:103-105](app/Providers/AppServiceProvider.php#L103-L105)

```php
User::observe(ModelActivityObserver::class);
Business::observe(ModelActivityObserver::class);
Transaction::observe(ModelActivityObserver::class);
```

`ActivityLog::create(...)` is called **from exactly one place** in the entire codebase —
the observer ([app/Observers/ModelActivityObserver.php:37](app/Observers/ModelActivityObserver.php#L37)).
Therefore **only** create/update/delete of `User`, `Business`, and `Transaction` are ever
recorded. Every other financially- and operationally-significant model has **no audit
trail at all**, including:

- `BalanceHistory`, `BusinessBalanceHistory`, `ContractorBalanceHistory`, `ThirdPartyPayerBalanceHistory` (money movement)
- `Invoice`, `CreditNote`, `Quotation`, `PackageSales`
- `WithdrawalRequest` / approvals, `CreditLimitChangeRequest`, `MoneyTransfer`, `MoneyAccount`
- `Client`, `Item`, `Role`, `Branch`, `ServicePoint`, all Inventory models, etc.

**Impact:** No accountability for approvals, refunds, balance adjustments, pricing
changes, role/permission changes, or client data edits.

### 2.2 🔴 Authentication events (login / logout) are never logged
The audit UI advertises `login` and `logout` log types
([app/Livewire/AuditLogs.php:75-76](app/Livewire/AuditLogs.php#L75)), but **no code ever
writes an `action_type` of `login` or `logout`** — this was confirmed by searching the
whole `app/` tree. Contributing causes:

- The `Login` event listener is **commented out** in
  [app/Providers/EventServiceProvider.php:23-25](app/Providers/EventServiceProvider.php#L23-L25),
  and even `SendLoginAlert` only sends a notification — it writes no log.
- The multiple logout paths (`CashierAuth\LoginController`, `ThirdPartyPayerAuth\LoginController`,
  `Handler::Auth::logout()`) record nothing.

**Impact:** No record of who logged in, when, from what IP, or of failed login attempts —
a baseline requirement for any security audit trail.

### 2.3 🔴 `AuditTrait` / `createAudit()` is broken and cannot execute
[app/Traits/AuditTrait.php](app/Traits/AuditTrait.php)

```php
use App\Models\AuditLog;   // <-- class does NOT exist (no model, no migration, no table)

public function createAudit(Request $request, $description, $event_type) { ... }
```

Problems:
1. `App\Models\AuditLog` does not exist, and there is no `audit_logs` migration/table.
2. Callers pass **5 arguments** to a **3-parameter** method, e.g.
   [app/Http/Controllers/RoleController.php:116](app/Http/Controllers/RoleController.php#L116):
   `$this->createAudit($request, 'Created Role', 'Create', $role->getTable(), $role->id);`
3. The columns it writes (`event_type`, `method`, `portal_type`) don't match any table.

**Impact:** Every call to `createAudit()` throws `Class "App\Models\AuditLog" not found`.
In `RoleController::store/update/destroy` this exception is swallowed by the surrounding
`try/catch`, so **role create/update/delete silently report an error to the user** even
when the role change itself succeeded — and nothing is audited.

### 2.4 🟠 System / console / queue actions are silently excluded
[app/Observers/ModelActivityObserver.php:29-31](app/Observers/ModelActivityObserver.php#L29-L31)

```php
if (!Auth::check()) {
    return;   // skips logging entirely
}
```

Any change made by a console command, scheduled job, queue worker, webhook, or
payment-gateway callback is **not logged**, even for the 3 observed models. Automated
balance/transaction mutations (a large part of this system) leave no trace.

### 2.5 🟠 Bulk / query-builder writes bypass the observer even for observed models
Eloquent model events only fire on model instances. The codebase makes heavy use of
patterns that **bypass** observers (~344 occurrences): `Model::query()->update([...])`,
`DB::table(...)`, `::insert(...)`, `insertGetId(...)`, `updateQuietly()`, `saveQuietly()`.
So even `Transaction`/`User`/`Business` changes done via bulk update or the query builder
produce **no audit record**.

### 2.6 🟠 Sensitive fields are written to the audit table in the clear
[app/Observers/ModelActivityObserver.php:44-45](app/Observers/ModelActivityObserver.php#L44)

```php
'old_values' => $oldData ? json_encode($oldData) : null,
'new_values' => ... json_encode($model->getAttributes()) ...,
```

`getAttributes()` / `getOriginal()` include **all** columns. For `User` this means the
`password` hash, `remember_token`, `two_factor_secret`, and `two_factor_recovery_codes`
(all listed in `User::$hidden`, [app/Models/User.php:64-68](app/Models/User.php#L64-L68))
get copied into `activity_logs`. The whole attribute set is stored on every update rather
than a diff of changed fields, bloating the table and duplicating secrets.

### 2.7 🟠 Audit log viewer has no authorization or tenant scoping
The `/audit-logs` route sits inside the generic `['auth','verified']` group
([routes/web.php:148](routes/web.php#L148)) with **no role/permission check**, and the
Livewire query is not scoped to the user's business
([app/Livewire/AuditLogs.php:23](app/Livewire/AuditLogs.php#L23)):

```php
->query(ActivityLog::with(['user', 'business'])->latest())
```

Any authenticated, verified user can open the audit page and read **every business's**
activity logs (a multi-tenant data-exposure issue), and audit records themselves are
soft-deletable (`SoftDeletes` on `ActivityLog`) — the audit trail is neither access-
controlled nor tamper-evident.

---

## 3. Minor Findings

- **`AuditLogController` is an empty stub** — `index()` returns a view; all other REST
  methods are empty ([app/Http/Controllers/AuditLogController.php](app/Http/Controllers/AuditLogController.php)).
  A full `Route::resource` is registered for it, exposing dead endpoints.
- **`date` column default is frozen at migration time** —
  `$table->date('date')->default(now())`
  ([migration:30](database/migrations/2025_11_23_203441_create_activity_logs_table.php#L30))
  bakes a single constant date into the schema default instead of defaulting per-row, and
  the observer never sets `date`, so it is meaningless. Use `created_at` instead.
- **No diffing** — updates store the entire before/after attribute set rather than only
  changed keys, making the log noisy and hard to review.
- **`action_type` is never populated** by the observer (it only sets `action`), so the
  UI's "Log Type" column/filter is always empty/`System`.
- **Unstructured logging is inconsistent** — 1,666 `Log::*` calls concentrate in a few
  services (`MoneyTrackingService` 238, `InvoiceController` 212, `ClientController` 112)
  and are absent elsewhere; they are debug traces, not a structured audit trail, and are
  subject to log rotation.

---

## 4. Coverage Summary

| Event / Action | Logged today? | Where |
|----------------|---------------|-------|
| User created / updated / deleted (via model) | ✅ Partial | observer (secrets leaked, no diff) |
| Business created / updated / deleted (via model) | ✅ Partial | observer |
| Transaction created / updated / deleted (via model instance) | ✅ Partial | observer |
| Login / logout / failed login | ❌ No | — |
| Role / permission changes | ❌ No | `createAudit()` throws |
| Balance / money-account changes | ❌ No | — |
| Invoices, credit notes, quotations, package sales | ❌ No | — |
| Withdrawals, credit-limit changes, approvals | ❌ No | — |
| Client / Item / Branch / ServicePoint edits | ❌ No | — |
| Inventory operations | ❌ No | — |
| Console/cron/queue/webhook mutations | ❌ No | observer skips unauthenticated |
| Bulk / query-builder updates (even observed models) | ❌ No | events bypassed |

---

## 5. Recommendations (priority order)

1. **Log authentication events.** Register listeners for Laravel's `Login`, `Logout`,
   `Failed`, and `Registered` auth events (and the cashier/third-party guards) and write
   `ActivityLog` rows with `action_type` `login`/`logout`/`login_failed`, IP, and user-agent.
2. **Fix or remove `AuditTrait`.** Either create the `AuditLog` model + `audit_logs`
   migration and correct the method signature, or delete the trait and route
   `RoleController` (and any future callers) through the `ActivityLog` mechanism. Right
   now it is guaranteed-throwing dead code hidden behind `try/catch`.
3. **Broaden model coverage deliberately.** Register the observer on all audit-relevant
   models (money, invoices, approvals, clients, roles, items, inventory). Consider a
   `LogsActivity` trait on the base model or a curated allow-list rather than 3 ad-hoc calls.
4. **Capture system/automated actions.** Replace the `if (!Auth::check()) return;` guard
   with a fallback actor (e.g. `user_id = null`, `actor = 'system'/command name`) so cron,
   jobs, and webhooks are still recorded.
5. **Handle bulk writes.** Audit the important query-builder/bulk paths explicitly, or
   convert them to model operations where feasible.
6. **Redact secrets and store diffs.** Exclude `$hidden`/sensitive columns and store only
   changed attributes (`getChanges()` vs `getOriginal()` intersection).
7. **Lock down the audit viewer.** Add a permission/role gate to `/audit-logs`, scope the
   query to the current business for non-super-admins, and prevent deletion/mutation of
   audit rows (drop `SoftDeletes` or block the delete path) to keep the trail tamper-evident.
8. **Set `date`/timestamps correctly** and remove the frozen `default(now())`; rely on
   `created_at`.

---

## 6. Files Reviewed

- [app/Models/ActivityLog.php](app/Models/ActivityLog.php)
- [app/Observers/ModelActivityObserver.php](app/Observers/ModelActivityObserver.php)
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)
- [app/Providers/EventServiceProvider.php](app/Providers/EventServiceProvider.php)
- [app/Traits/AuditTrait.php](app/Traits/AuditTrait.php)
- [app/Http/Controllers/AuditLogController.php](app/Http/Controllers/AuditLogController.php)
- [app/Http/Controllers/RoleController.php](app/Http/Controllers/RoleController.php)
- [app/Livewire/AuditLogs.php](app/Livewire/AuditLogs.php)
- [app/Listeners/SendLoginAlert.php](app/Listeners/SendLoginAlert.php)
- [database/migrations/2025_11_23_203441_create_activity_logs_table.php](database/migrations/2025_11_23_203441_create_activity_logs_table.php)
- [routes/web.php](routes/web.php)
