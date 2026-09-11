---
name: run-kashtre
description: Build, run, and drive the KashTre Laravel/Livewire web app. Use when asked to start KashTre, run its dev server, take a screenshot of its UI, log in and click through a page, or verify a Livewire/Filament-table feature actually renders.
---

KashTre is a Laravel 10 + Livewire 3 app (Filament's Table/Forms packages
used standalone inside plain Livewire components — not a full Filament
panel). There's no `chromium-cli` on this machine (Windows, no tmux
either) — drive it instead via the Playwright script at
`.claude/skills/run-kashtre/driver.mjs`, piping a line-oriented command
script to its stdin. All paths below are relative to the repo root.

## Prerequisites

PHP 8.4 + Composer deps and Node 22 + npm deps are already set up in this
repo (`vendor/`, `node_modules/`). One extra dependency for driving the
app headlessly:

```bash
npm install -D playwright
npx playwright install chromium
```

MySQL must be running and `.env` pointed at a real dev database — this
app has no sqlite/in-memory test mode, `php artisan serve` talks to
whatever `DB_DATABASE` in `.env` says.

## Run (agent path)

1. Start the app server (Vite's dev server for Alpine/Livewire/Filament
   JS is usually already running in the background on this machine —
   check before starting a second one):

   ```bash
   netstat -ano | grep ":5173" | grep LISTENING   # Vite dev server (asset HMR)
   # if nothing listed:
   npm run dev &

   (php artisan serve --port=8123 > /tmp/artisan_serve.log 2>&1 &)
   timeout 30 bash -c 'until curl -sf http://127.0.0.1:8123 >/dev/null; do sleep 1; done'
   ```

2. Drive it — pipe a script to the driver's stdin. This baseline example
   only needs a login, no feature-specific permissions, so it always
   works as-is:

   ```bash
   node .claude/skills/run-kashtre/driver.mjs <<'EOF'
   login admin@kashtremedicalcenter.com password
   wait-for text=Select Your Room
   select select 1
   click-text Confirm
   wait-for text=Welcome to Kashtre
   screenshot dashboard
   console --errors
   EOF
   ```

   To reach a specific feature page, `nav` there after login — but check
   the Auth section below first if it's permission-gated (most
   non-trivial pages are).

   The whole script runs in one browser instance, sequentially, then
   exits — there's no persistent REPL/tmux session to attach to (no tmux
   on this machine). To iterate, just re-run with a longer/edited script;
   launch is a couple seconds, not worth keeping a session alive for.

3. Stop the server when done:

   ```bash
   netstat -ano | grep ":8123" | grep LISTENING   # find the PID
   taskkill //F //PID <pid>
   ```

Screenshots land in (override with `SCREENSHOT_DIR` env var):
`C:/Users/Personal/AppData/Local/Temp/claude/c--Users-Personal-projects-kashtre/6cd8a2c6-5da7-4949-a292-c3494f706876/scratchpad/shots/`

### Driver commands

| command | what it does |
|---|---|
| `nav <url>` | navigate |
| `login <email> <password>` | KashTre-specific: fills and submits `/login`, waits for redirect |
| `wait-for <css-selector>` / `wait-for text=<text>` | wait up to 15s for an element |
| `screenshot [name]` | full-page screenshot → `<SHOT_DIR>/<name>.png` |
| `click <css-selector>` / `click text=<text>` | real Playwright click (mouse event, not DOM `.click()`) |
| `click-text <text>` | click the element whose visible text matches (exact, falls back to partial) |
| `select <css-selector> <label>` | `<select>` element → pick option by visible label |
| `select modal-select:N <label>` | pick option on the Nth `<select>` inside `.fi-modal`, in visual order — use this for Filament action-form fields, whose real ids are Livewire-generated with dots (`mountedTableActionsData.0.field_name`) and don't play nicely with CSS id selectors |
| `fill <css-selector> <text>` | fill a text input |
| `type <text>` / `press <key>` | keyboard input |
| `eval <js>` | evaluate in the page, prints JSON |
| `text [css-selector]` | print `innerText` |
| `sleep <ms>` | plain wait, use sparingly — prefer `wait-for` |
| `console --errors` | print captured `console.error`/`pageerror` since launch |
| `quit` | close the browser (also runs automatically at end of script) |

### Auth

`admin@kashtremedicalcenter.com` / `password` is a real seeded account
(business_id 1 — the Kashtre-internal super-admin business, can see/act
across every business). It logs in successfully but for *most* accounts
in this app — including this one — the first post-login page is gated by
a "Select Your Room" modal (see Gotchas) before the rest of the UI is
usable.

Permissions are a flat JSON array column (`users.permissions`), not a
role/policy system a route can be bypassed with — a feature's nav
link/buttons stay invisible until the string is present in that array
(e.g. `'View Imaging Orders'`). To test a permission-gated feature:

```bash
php artisan tinker --execute="
\$u = App\Models\User::find(1);
file_put_contents('/tmp/orig_perms.json', json_encode(\$u->permissions));
\$perms = \$u->permissions ?? [];
\$perms[] = 'View Imaging Orders';
\$u->permissions = \$perms;
\$u->save();
"
# ...drive the app...
php artisan tinker --execute="
\$u = App\Models\User::find(1);
\$u->permissions = json_decode(file_get_contents('/tmp/orig_perms.json'), true);
\$u->save();
"
```

Always revert afterward — this touches a real seeded user row, not a
disposable test fixture.

## Run (human path)

```bash
npm run dev          # Vite HMR server, leave running
php artisan serve    # http://127.0.0.1:8000, Ctrl-C to stop
```

## Test

```bash
./vendor/bin/phpunit tests/Unit          # `php artisan test` errors — that
                                          # command name isn't registered here
```

---

## Gotchas

- **A SweetAlert2-style "Done" modal (title + message + OK button) can
  cover the page after a `redirect()->with('success', ...)`/`with('error',
  ...)` — it's app-wide layout behavior, not specific to any one feature.**
  It only appears when a flash-message session key is actually present on
  the page render (a fresh `nav` to a URL with no pending flash won't show
  it), so it's easy to be confused by a `click text=OK` that times out
  because there was nothing to dismiss. If a script's next action after a
  form POST redirect seems to hang/miss, screenshot first to check whether
  this modal is sitting on top of what you're trying to click — dismiss it
  (`click text=OK`) before continuing, or just re-`nav` to the same URL to
  get a clean render without the flash replaying.

- **Plain `onsubmit="return confirm(...)"` forms (not every confirm dialog
  in this app is SweetAlert2) silently fail to submit under Playwright**
  unless something handles the `dialog` event — by default Playwright
  auto-dismisses native dialogs, so `confirm()` returns `false` and the
  submit never happens (no error, the form just doesn't post). The driver
  registers `page.on('dialog', d => d.accept())` in `ensureLaunched()` to
  always accept; there was no need yet for a script that needs the
  "Cancel" path exercised, but if one comes up, that's a one-line change
  to make conditional instead of blanket-accept.

- **`nav` can occasionally race and abort** (`net::ERR_ABORTED`) right
  after the post-login "Select Your Room" modal's Confirm click, even
  after `wait-for text=Welcome to Kashtre` succeeded — that text
  appearing doesn't mean the page has fully settled (Livewire seems to
  still be finishing a redirect/reload from the room confirmation). A
  fixed `sleep` before the next `nav` reduces this but does **not**
  reliably eliminate it — hit it again in a later session despite an
  800ms sleep. The driver's `nav` command now retries once automatically
  on `ERR_ABORTED` (1s pause, then retry) instead of failing the script;
  no workaround needed in scripts you write, just be aware a `nav` may
  legitimately take a bit longer right after that modal.

- **Modals render at ~10% opacity and never finish fading in, in headless
  Chromium — unless the browser context sets `reducedMotion: 'reduce'`.**
  This is the single biggest trap here. Filament/Alpine's `x-transition`
  on `.fi-modal` gets stuck mid-animation under headless Chromium; a
  screenshot taken after the click shows a barely-visible ghost of the
  modal (you can just make out field labels if you squint) rather than
  the real thing, no matter how long you `sleep`. Fix: pass
  `reducedMotion: 'reduce'` to `browser.newContext()` — the driver
  already does this. If you ever see a "ghost modal" screenshot, this is
  why.

- **`element.click()` (raw DOM click) does not open Filament action
  modals — only a real Playwright click (synthetic mouse event) does.**
  The button reports success either way (`el.click()` doesn't throw),
  and no console error appears — it just silently does nothing. Confirmed
  by checking the Livewire network request: a real Playwright
  `locator.click()` fires `POST /livewire/update` and the modal element
  (`.fi-modal.fi-modal-open`) appears in the DOM; `element.click()` fires
  nothing. Always use `click`/`click-text` (both use real Playwright
  clicks in this driver), never `eval ...click()`.

- **Filament's "searchable" Select fields are still real `<select>`
  elements underneath**, not a custom combobox widget — `selectOption()`
  works directly, no need to simulate typing into a search box. Their
  `id` attributes are Livewire-generated with literal dots
  (`mountedTableActionsData.0.business_id`), which breaks a plain CSS id
  selector (`#foo.bar` parses as element `foo` with class `bar`). Use
  `select modal-select:N <label>` (position within `.fi-modal`, 0-indexed
  in visual field order) instead of trying to construct/escape the real
  id. One open gap: for *async-search* selects (options fetched via
  `getSearchResultsUsing`, e.g. the Client picker on the Imaging Orders
  page), the underlying `<select>` has **zero preloaded `<option>`s** —
  `selectOption({label: ...})` will find nothing until the visible
  search UI actually triggers Livewire's search request first. Driving
  that end-to-end wasn't finished; if you need it, start by finding
  what's clickable/typeable to trigger the search (likely a
  Choices.js-rendered sibling element, not the select itself).

- **Vite dev server binds to `[::1]:5173` (IPv6 loopback only), not
  `127.0.0.1:5173`.** A `curl http://127.0.0.1:5173` will report
  "connection refused" even while the server is perfectly healthy and
  serving the browser fine (Chromium resolves `localhost`/bare host to
  IPv6 first). Don't take that curl failure as "Vite isn't running" —
  check `netstat -ano | grep :5173` instead, or just check `public/hot`
  for the URL it's actually advertising.

- **`readline`'s `close` event fires before async `line` handlers
  finish, when stdin is a heredoc (not a TTY).** The first version of
  this driver processed each line in an async `'line'` handler with
  `rl.pause()`/`rl.resume()` around it — looked reasonable, but
  `'close'` fired as soon as all lines were read off the pipe, not after
  they'd all been *processed*, and the `process.exit(0)` in the `close`
  handler killed the browser mid-launch. Symptom: script exits almost
  instantly, only the last command or two ever prints anything, zero
  screenshots written despite no errors. Fix (already in the driver):
  buffer every line into an array first, then `await` them one at a time
  in a plain `for` loop *inside* the `close` handler.

- **No tmux on this machine.** The upstream `run`/`run-skill-generator`
  skills assume a Linux container with tmux for REPL-style drivers
  (send-keys/capture-pane). This driver is a one-shot script instead —
  pipe the whole command sequence to stdin per invocation. Fine for this
  app's launch speed (~1-2s); would need reworking into a real
  long-lived REPL only if a much slower app made relaunching per attempt
  too costly.

- **`chromium-cli` referenced by the generic `/run` skill isn't a real
  installable tool** (`npm view chromium-cli` → 404). It's bundled only
  in Anthropic's cloud sandbox containers. On a real dev machine like
  this one, install Playwright directly instead — that's what this skill
  does.

- **Opening a Filament action modal via `click-text` is occasionally
  flaky, and not only right after the room-select modal** — hit it again
  on a plain fresh `nav` straight to a list page with no prior modal in
  the picture. Same click, same script, sometimes the modal just doesn't
  open (no error, `click-text` reports `OK`, but the modal never
  appears and a later `wait-for`/`fill`/`select` inside it times out
  after 30s waiting for an element that was never rendered). A blind
  `sleep` after the click doesn't fix this — it's not slowness, the
  click just occasionally doesn't register as a "real" interaction.
  `wait-for` on a concrete selector/text inside the modal (not a fixed
  sleep) at least fails fast and tells you clearly what happened. When
  it does fail, don't burn multiple retries chasing it in the same
  investigation — one retry of the whole `nav` → `click-text` → `wait-for`
  sequence is usually enough to tell whether it's this known flake or a
  real regression; if a single retry doesn't clear it, stop and look at
  what else changed instead of hammering the same script.

- **The Vite dev server can die mid-session without anything else
  crashing** — the app keeps responding (200s, no PHP errors), but every
  CSS/JS asset 404s/`ERR_CONNECTION_REFUSED`s and the page renders as
  giant unstyled black boxes and oversized broken SVG icons. Easy to
  mistake for an actual app bug. Check `netstat -ano | grep ":5173"`
  first if a screenshot suddenly looks like that; restart with
  `npm run dev &` (takes ~3s) and re-run the script.

## Troubleshooting

- **`EADDRINUSE` on `php artisan serve`**: something's already listening
  on that port. `netstat -ano | grep ":8123" | grep LISTENING`, then
  `taskkill //F //PID <pid>` (Git Bash's `pkill`/`kill` don't reliably
  hit Windows PHP processes launched via a backgrounded subshell — use
  `taskkill` instead).
- **`node .../driver.mjs` throws `ERR_MODULE_NOT_FOUND: playwright`**:
  you ran it from outside the repo (e.g. `/tmp`). Playwright is a repo
  devDependency, not global — always run the driver with the repo root
  as cwd.
- **Screenshot shows the dashboard but every nav item under a group is
  missing, sidebar looks empty-ish**: you're logged in as a user without
  the permission string for that feature. Check/grant via the
  `permissions` JSON column (see Auth above), not a route guard — this
  app doesn't have route-level permission middleware, only UI-level
  hiding (a direct `nav <url>` to a gated page still 200s, it just
  renders an empty/action-less version).
