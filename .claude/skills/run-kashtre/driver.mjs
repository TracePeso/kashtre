// Headless-Chromium driver for KashTre (Laravel + Livewire/Filament web app).
// No chromium-cli / tmux on this machine (Windows, no tmux binary) — this
// script is the substitute: pipe a line-oriented command script to stdin,
// it drives one Playwright browser for the whole run and exits.
//
// Usage:
//   node .claude/skills/run-kashtre/driver.mjs <<'EOF'
//   nav http://127.0.0.1:8123/login
//   ...
//   EOF
import { chromium } from 'playwright';
import * as fs from 'node:fs';
import * as path from 'node:path';
import * as readline from 'node:readline';

const BASE_URL = process.env.KASHTRE_BASE_URL || 'http://127.0.0.1:8123';
const SHOT_DIR = process.env.SCREENSHOT_DIR
  || 'C:/Users/Personal/AppData/Local/Temp/claude/c--Users-Personal-projects-kashtre/6cd8a2c6-5da7-4949-a292-c3494f706876/scratchpad/shots';
fs.mkdirSync(SHOT_DIR, { recursive: true });

const consoleErrors = [];

let browser = null;
let page = null;

async function ensureLaunched() {
  if (page) return;
  browser = await chromium.launch({ headless: true });
  // reducedMotion: 'reduce' is required, not cosmetic — see Gotchas. Without
  // it, Filament/Alpine's x-transition on modals gets stuck mid-fade in
  // headless Chromium (screenshots show a near-invisible ghost modal at
  // ~10% opacity that never finishes animating in).
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  page = await context.newPage();
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push('pageerror: ' + err.message));
  // Several forms in this app use a plain `onsubmit="return confirm(...)"`
  // guard (not SweetAlert2). Playwright dismisses native dialogs by default
  // when nothing listens for them, which makes confirm() return false and
  // silently blocks the submit. Auto-accept always — no script here needs
  // the "cancel" path exercised.
  page.on('dialog', (d) => d.accept());
}

function resolveLocator(sel) {
  if (sel.startsWith('text=')) {
    return page.getByText(sel.slice(5), { exact: false }).first();
  }
  return page.locator(sel).first();
}

const COMMANDS = {
  async nav(url) {
    await ensureLaunched();
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    } catch (e) {
      // net::ERR_ABORTED happens occasionally right after a prior
      // Livewire-driven redirect (e.g. the room-select modal) is still
      // settling — see Gotchas. A fixed sleep before nav reduces it but
      // doesn't eliminate it, so retry once after a short pause instead
      // of failing the whole script over a timing fluke.
      if (!String(e.message).includes('ERR_ABORTED')) throw e;
      await new Promise((r) => setTimeout(r, 1000));
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    }
    console.log('nav ->', page.url());
  },

  async 'wait-for'(sel) {
    await ensureLaunched();
    try {
      await resolveLocator(sel).waitFor({ timeout: 15_000 });
      console.log('found:', sel);
    } catch {
      console.log('TIMEOUT:', sel);
    }
  },

  async screenshot(name) {
    await ensureLaunched();
    const f = path.join(SHOT_DIR, (name || `ss-${Date.now()}`) + '.png');
    await page.screenshot({ path: f, fullPage: true });
    console.log('screenshot:', f);
  },

  async click(sel) {
    await ensureLaunched();
    try {
      await resolveLocator(sel).click({ timeout: 10_000 });
      console.log('click', sel, '-> OK');
    } catch (e) {
      console.log('click', sel, '-> ERROR:', e.message.split('\n')[0]);
    }
  },

  // Filament buttons/table actions often have dynamic classes with no
  // stable CSS hook — matching by visible text avoids depending on those.
  // Uses a REAL Playwright click (mouse down/up), not element.click() — see
  // Gotchas: Alpine's x-on:click handlers on Filament's action buttons do
  // not fire from a raw DOM .click(), only from a genuine dispatched event.
  async 'click-text'(text) {
    await ensureLaunched();
    try {
      await page.getByText(text, { exact: true }).first().click({ timeout: 5000 });
      console.log('click-text', JSON.stringify(text), '-> OK (exact)');
    } catch {
      try {
        await page.getByText(text, { exact: false }).first().click({ timeout: 5000 });
        console.log('click-text', JSON.stringify(text), '-> OK (partial)');
      } catch (e) {
        console.log('click-text', JSON.stringify(text), '-> ERROR:', e.message.split('\n')[0]);
      }
    }
  },

  async fill(rest) {
    await ensureLaunched();
    const sp = rest.indexOf(' ');
    const sel = sp === -1 ? rest : rest.slice(0, sp);
    const value = sp === -1 ? '' : rest.slice(sp + 1);
    await resolveLocator(sel).fill(value);
    console.log('fill', sel, '->', JSON.stringify(value));
  },

  async type(text) {
    await ensureLaunched();
    await page.keyboard.type(text, { delay: 20 });
    console.log('type ->', JSON.stringify(text));
  },

  async press(key) {
    await ensureLaunched();
    await page.keyboard.press(key);
    console.log('press ->', key);
  },

  async sleep(ms) {
    await new Promise((r) => setTimeout(r, Number(ms) || 500));
    console.log('slept', ms || 500, 'ms');
  },

  async eval(expr) {
    await ensureLaunched();
    try {
      console.log(JSON.stringify(await page.evaluate(expr)));
    } catch (e) {
      console.log('ERROR:', e.message);
    }
  },

  async text(sel) {
    await ensureLaunched();
    const out = await page.evaluate(
      (s) => (s ? document.querySelector(s) : document.body)?.innerText ?? '(null)',
      sel || null,
    );
    console.log(out);
  },

  async console(mode) {
    if (mode === '--errors' || mode === 'errors') {
      if (consoleErrors.length === 0) {
        console.log('no console errors captured');
      } else {
        console.log('console errors:');
        for (const e of consoleErrors) console.log(' -', e);
      }
    } else {
      console.log(consoleErrors.length, 'errors captured (use "console --errors" to list)');
    }
  },

  // KashTre-specific: standard session-auth login form at /login.
  async login(rest) {
    await ensureLaunched();
    const [email, password] = rest.split(' ');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => {}),
      page.click('button[type="submit"]'),
    ]);
    console.log('login ->', page.url());
  },

  // Filament's Select fields render as real <select> elements underneath
  // (even the "searchable" ones), so selectOption() works directly — no
  // need to fake typing into a combobox widget. Their ids are
  // Livewire-generated with dots (mountedTableActionsData.0.field_name),
  // which breaks plain CSS id selectors, so this also accepts the
  // convention "modal-select:N" for "the Nth <select> inside .fi-modal,
  // in DOM/visual order" — simpler than fighting the id escaping every time.
  async select(rest) {
    await ensureLaunched();
    const sp = rest.indexOf(' ');
    const sel = sp === -1 ? rest : rest.slice(0, sp);
    const value = sp === -1 ? '' : rest.slice(sp + 1);
    const locator = sel.startsWith('modal-select:')
      ? page.locator('.fi-modal select').nth(Number(sel.slice('modal-select:'.length)))
      : page.locator(sel).first();
    const picked = await locator.selectOption({ label: value }).catch(() => null)
      ?? await locator.selectOption(value);
    console.log('select', sel, '->', JSON.stringify(picked));
  },

  async quit() {
    if (browser) await browser.close().catch(() => {});
    browser = null;
    page = null;
    console.log('quit');
  },

  help() {
    console.log('commands:', Object.keys(COMMANDS).join(', '));
  },
};

// Buffer every line first, then run them strictly sequentially. Reacting to
// 'line' events with an async handler is a trap here: stdin is a heredoc,
// not a TTY, so all lines arrive and the stream's 'close' event fires
// before slow commands (browser launch, page.goto) resolve — rl.pause()
// does not hold back an already-buffered 'close'. That raced the process
// exit ahead of every command on the first attempt (no screenshots, no
// output past the last two lines). Collecting into an array and awaiting
// them one by one in a plain loop after 'close' avoids the race entirely.
const lines = [];
const rl = readline.createInterface({ input: process.stdin, terminal: false });
rl.on('line', (line) => lines.push(line));

rl.on('close', async () => {
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const sp = trimmed.indexOf(' ');
    const cmd = sp === -1 ? trimmed : trimmed.slice(0, sp);
    const rest = sp === -1 ? '' : trimmed.slice(sp + 1);
    const fn = COMMANDS[cmd];
    if (!fn) {
      console.log('unknown command:', cmd, '- try: help');
      continue;
    }
    try {
      await fn(rest);
    } catch (e) {
      console.log('ERROR running', cmd, '->', e.message);
    }
  }
  await COMMANDS.quit();
  process.exit(0);
});
