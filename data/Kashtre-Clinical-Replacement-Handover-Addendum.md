# KashTre Clinical replacement — handover addendum

27 September 2026

This addendum answers the outstanding inspection items. Where the detail already lives in a repository, this note points to the file rather than repeating it. No live patient data, passwords, API keys, or `.env` values are included.

**Standing constraints (unchanged)**

- The current Clinical installation stays running until the replacement is tested and a switch is agreed.
- Main’s existing units functionality stays unchanged.
- The new Clinical module will contain its own units engine.

---

## Status at a glance

| Item | Status | Remaining |
|---|---|---|
| Repository access (all five apps, branches + PRs) | Partial | Invite the GitHub user to Main plus write access on the private repos. Username not received yet. |
| Development branch for each repository | Ready | See §1 |
| Inventory exact commit / preserve uncommitted demo files | Partial | Inventory source of truth is Main `demo`. `inventory_module` is not yet a usable baseline. Copy the two named demo-host files before any hard reset. |
| Running apps vs stated SHAs | Not yet confirmed on the hosts | Same day the dedicated account exists |
| Dedicated development/deployment account | Outstanding | Credentials on a separate secure channel |
| Paths, URLs, deploy commands, queues, scheduler, runtimes | Partial | Main is documented. Clinical / HR / Inventory host paths and public URLs need a host listing. |
| Integration docs, migrations, test data, config names, JWT | Ready in repos | See §4 |
| Demo isolation (DB, stock, billing, queues, storage, cron) | Not yet verified on the hosts | Same day as the dedicated account |
| Demo backup / restore procedure and whether it was tested | Procedure written; restore not tested | Test restore after the account exists |

---

## 1. Repository access and development branches

Please send the GitHub username. We will invite it to the TracePeso organisation with permission to create development branches and open pull requests.

Main was omitted from the earlier invitation list. That access will be included.

| Application | Repository | Development branch | Do not use for this work |
|---|---|---|---|
| Main | https://github.com/TracePeso/kashtre | `demo` | production `main` |
| Clinical | https://github.com/TracePeso/clinical_module | `main` (only branch today) | — |
| HR | https://github.com/TracePeso/HR_MODULE | `main` (only branch today) | — |
| Inventory | https://github.com/TracePeso/inventory_module | `main` once source is present | — |
| Inventory (working source today) | same Main repo, `demo` | `demo` | production `main` |
| AI Gateway | https://github.com/TracePeso/Kashtre-AI-gateway | `main` | — |

Please open feature branches from the development branch above and submit pull requests. Do not push directly to production `main`.

---

## 2. Complete source baseline

Stated repository versions as of 27 September 2026:

| Application | Branch | Commit |
|---|---|---|
| Main | `demo` | `cd4c13eb6e8c0f697d410257940c41c24ae8980f` |
| Clinical | `main` | `87e4a509c6ba0da75a8a7017ee8ea59635462b76` |
| HR | `main` | `56f009c1ffb0981b89e93392517abecf67b9d2a3` |
| Inventory (TracePeso `inventory_module`) | `main` | `a99b268cee6306dd83ecda28f2ef2cc021cef805` — initial commit only; not a working baseline |
| Inventory (code that actually runs with Main) | Main `demo` | `cd4c13eb6e8c0f697d410257940c41c24ae8980f` |
| AI Gateway | `main` | `153ca155854d486d280d0cffae72417ecc091d1f` |

Production Main is a different branch and commit (`main` / `b41fe604160bb734c6a807c78c2c09dc2eaad593`). Do not use it for replacement work.

**Preserve before any hard reset.** The Main demo deploy script resets hard to `origin/demo`:

- `.github/workflows/deploy-demo.yml`

Please take current demo-host copies of the files named in the first handover, and any other source edits on that host, **before** another deploy or reset:

- `package-lock.json`
- `bootstrap/cache/.gitignore`

Do not include `.env`, keys, or database dumps in that copy.

**Running vs stated SHA.** We have not yet compared `git rev-parse HEAD` on each demo host with the table above. That check will be done with the dedicated account and the result sent as a short SHA list. Until then, treat the table as the repository statement, not as a confirmed match to every running process.

---

## 3. Demo deployment access

A dedicated development/deployment account will be arranged, limited to the demo host and the non-production branches above. Connection details and credentials will be sent on a **separate secure channel**, not in this note.

Required permissions: inspect, build, deploy, and recover the demo applications only.

---

## 4. Application locations, deploy, queues, scheduler, runtimes

### Documented now (Main)

| | Value | Where it is written |
|---|---|---|
| Demo URL | `https://demo.kashtre.com` | `DEMO_DEPLOYMENT.md` |
| Demo branch | `demo` | `DEMO_DEPLOYMENT.md`, `.github/workflows/deploy-demo.yml` |
| Observed demo app path | `/var/www/demo-kashtre` | used by the demo workbook import on that host |
| Workflow default path if the variable is unset | `/var/www/demo` | `.github/workflows/deploy-demo.yml` (`DEMO_DEPLOY_PATH`) |
| Production Main path (contrast only) | `/var/www/my-app` | `.github/workflows/deploy-main.yml` |
| PHP | 8.2+ required; deploy restarts `php8.3-fpm` | `composer.json`, `.github/workflows/deploy-demo.yml` |
| Database | MySQL 8.0+ | `DEMO_DEPLOYMENT.md` |
| Node build | `npm install` then `npm run build` | `.github/workflows/deploy-demo.yml` |

**Main demo deploy (no secrets)** — same sequence as `.github/workflows/deploy-demo.yml`:

```text
cd "$DEPLOY_PATH"
git fetch origin
git reset --hard origin/demo
composer install --no-dev --optimize-autoloader --no-interaction
npm install --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
systemctl restart php8.3-fpm
```

Do not run that reset until the uncommitted demo-host files in §2 have been copied.

**Scheduler (Main)** — `routes/console.php`, invoked by `php artisan schedule:run` (see `CRON_JOB_SETUP.md` and `setup-cron-job.sh`):

- every minute: `payments:check-status`, `payments:check-responsibility-status`, `payments:complete-pay-back`, `inventory:process-main-outbox`
- every four minutes: `hr:refresh-navigation`
- hourly: `service-queue:process-extended-items`, `withdrawals:auto-reject-overdue`, `service-charge:release-matured`, `time:materialize-schedules`
- daily: `inventory:verify-forensic-audit`, `visits:expire`, `time:day-boundary`, `time:cutover --check`

Queue workers are restarted with `php artisan queue:restart` in the demo workflow.

### Clinical, HR, Inventory, AI Gateway — what is in the repos vs what still needs the host

| Application | Public URL / path in repos | Runtime in repo docs | Still needed from the host |
|---|---|---|---|
| Clinical | Local default `http://127.0.0.1:8000` in `clinical_module` README | PHP 8.5, Composer 2, MySQL | Demo URL, filesystem path, queue unit, crontab, installed `php -v` / `mysql --version` |
| HR | `.env.example` in `HR_MODULE` | Laravel app | Same host listing |
| Inventory | Working UI/API lives under Main `/inventory*` | Same as Main | Confirm whether a second Inventory process exists besides Main |
| AI Gateway | `https://ai.kashtre.com` is the name used by Main’s AI client config; contract in gateway `README.md` | Laravel 11, see gateway `.env.example` | Demo/gateway path, queue, crontab |

Those host facts will be provided as a directory listing and `php -v` / `nginx` site list through the dedicated account. They are not rewritten here because they are not yet confirmed on the machines.

---

## 5. Integration, migrations, test data, configuration names, JWT

### Documents (no secrets)

| Topic | File |
|---|---|
| Main ↔ Clinical seam, `CLINICAL_DRIVER`, identity headers vs JWT | Main `docs/clinical module/MAIN_TO_CLINICAL_INTEGRATION.md` |
| Inventory / EndStore ↔ Clinical HTTP | Main `INTEGRATION_README.md` |
| AI Gateway contract | Main `docs/clinical module/KashTre AI Gateway Integration Specification v1.0.md` and gateway `README.md`, `openapi/ai-module-v1.yaml` |
| Clinical local setup and service-key auth | `clinical_module` `README.md` |
| Demo deploy overview | Main `DEMO_DEPLOYMENT.md` |
| Scheduler / cron | Main `CRON_JOB_SETUP.md`, `routes/console.php` |
| Demo queue/reset helpers | Main `QUEUE_RESET_README.md`, Main `README.md` |

### Working today vs planned

| Integration | State |
|---|---|
| Clinical → Main API key (`X-Service-Key` / `X-API-KEY`) | Working |
| Main → Clinical service key + `X-Tenant-Id` | Working when `CLINICAL_DRIVER=api` and URL/key are set |
| Current Clinical UI / `/clinical/*` inside Main | Working — keep it running |
| Inventory EndStore tote stage / validate | Working — see `INTEGRATION_README.md` |
| HR → Main (`X-API-Key` / `X-HR-API-Key`), `hr:refresh-navigation` | Working |
| Inventory → AI Gateway capability invoke | Working over HTTP (Sanctum token + tenant headers) |
| Identity JWT (`CLINICAL_IDENTITY_TRANSPORT=jwt`) | **Planned, not implemented.** Main `ClinicalRequestContext::identityToken()` returns null. Switching to JWT before minting is in place degrades to unattributed module traffic. See `MAIN_TO_CLINICAL_INTEGRATION.md` §8–9. |
| AI Gateway optional module JWT | **Off by default.** Gateway `.env.example`: `AI_JWT_ENABLED=false`. Sanctum PATs remain the working contract. |

Known differences between those documents and the running Main Clinical code are listed in `MAIN_TO_CLINICAL_INTEGRATION.md` §9 (notifier not wired, JWT minting missing, some Livewire screens still on Eloquent, consumption double-count risk if both paths fire).

### Migrations

- Main / current Clinical-in-Main: `database/migrations/` in the Main repo, including `database/migrations/2026_08_08_140000_create_clinical_module_integration_tables.php`
- New Clinical service: migrations in `clinical_module`
- Demo deploy runs `php artisan migrate --force` (see the workflow)

### Synthetic test data (demo only)

```text
php artisan kashtre:import-demo-workbook
# optional: --only=MCC,LSH,CCTH
```

Workbook: Main `data/Kashtre Import Data.xlsx`. Workflow: `.github/workflows/import-demo-workbook.yml`.

Inventory consumption backfill (synthetic, capped): Inventory “Generate test data” on `/inventory/consumption` (`InventoryConsumptionSampleDataService`).

Testing resets (demo / local only): Main `README.md` and `QUEUE_RESET_README.md`. Never run those against production.

### Configuration template — names only, no values

Safe templates in git:

- Main: `demo.env.example`
- AI Gateway: `.env.example`

Additional **names** used by Main for module wiring (do not copy secrets from any example that is not a template):

```text
# Main / demo
APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL
DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
QUEUE_CONNECTION SESSION_DRIVER CACHE_DRIVER FILESYSTEM_DISK
DEMO_MODE

# Clinical (current + split)
CLINICAL_DRIVER                 # local | api   — keep local until cutover
CLINICAL_MODULE_URL
CLINICAL_SERVICE_KEY
CLINICAL_MODULE_INBOUND_API_KEY
CLINICAL_INBOUND_SERVICE_KEYS
CLINICAL_IDENTITY_TRANSPORT     # headers today; jwt is planned
CLINICAL_DEFAULT_TENANT
CLINICAL_TIMEOUT
CLINICAL_MODULE_ENCOUNTER_WEBHOOK_ENABLED
DISPATCH_DRIVER                 # local | http
CLINICAL_DB_HOST CLINICAL_DB_PORT CLINICAL_DB_DATABASE CLINICAL_DB_USERNAME CLINICAL_DB_PASSWORD

# HR
HR_MODULE_URL
HR_MODULE_API_KEY
HR_MODULE_SYNC_ENABLED

# Inventory (when split)
INVENTORY_MODULE_URL
INVENTORY_MODULE_API_KEY

# AI Gateway (consumer side)
AI_GATEWAY_URL
AI_GATEWAY_TOKEN
AI_GATEWAY_INVENTORY_TOKEN
AI_GATEWAY_TENANT_ID
AI_GATEWAY_TIMEOUT

# Units on Main — leave as-is
UNIT_ENGINE_ENABLED
```

Gateway-side names are in `Kashtre-AI-gateway/.env.example` (`AI_*`, `GEMINI_*`, Sanctum). Do not put provider keys in Clinical, HR, Inventory, or Main.

---

## 6. Demo isolation

What we can state from the repositories without a host login:

- Demo and production are **different git branches and different documented app paths** (`demo` + `/var/www/demo-kashtre` vs `main` + `/var/www/my-app`).
- Demo deploy and production deploy are **separate GitHub Actions workflows**.
- `demo.env.example` uses a demo database name (`demo_kashtre`) and `APP_ENV=demo`.
- Synthetic organisations (MCC, LSH, CCTH) are imported for demonstration, not live patients.

What is **not yet verified on the hosts** (will be done with the dedicated account):

- Demo and production MySQL instances / schemas do not overlap
- Demo stock and billing ledgers are not the production ledgers
- Demo does not call live payment or other external production services
- Redis/queue connections, `storage/` disks, and crontab entries are not shared with production

Anything still shared after that check will be named explicitly.

---

## 7. Backup and recovery

Documented outline (no credentials):

1. **Backup (demo only)**  
   - Application trees excluding `.env` and `storage/logs`  
   - MySQL dump of **demo** databases only  
   - Keep `.env` and credentials in the organisation’s secret store, not in git

2. **Restore**  
   - Restore files to the demo paths  
   - Restore demo databases only  
   - `composer install` / `npm install && npm run build`  
   - `php artisan migrate --force`  
   - recache config/routes/views  
   - `php artisan queue:restart`  
   - restart php-fpm

3. **Never** restore a demo dump onto production, and never restore a production dump onto demo.

**Tested?** A restore has not been run as a rehearsed drill. That is a remaining limitation. We will run one demo-only restore after the dedicated account exists and report the result (success, time taken, and any leftover gaps).

---

## 8. What you can start on now

With the stated SHAs and the files listed above you can inspect Main, Clinical, HR, and the AI Gateway, and you can see how Main talks to the current Clinical module.

Please send the GitHub username so repository write access can be completed. The dedicated account, host path/URL listing, SHA-vs-running check, isolation check, and a tested restore will follow on that account and will be sent without passwords in the same style as this note.
