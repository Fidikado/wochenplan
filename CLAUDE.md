# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start local dev server
php -S 127.0.0.1:8080

# Lint a PHP file
php -l <file.php>

# Lint the frontend script
node --check js/app.js

# Smoke-test an API endpoint
curl http://127.0.0.1:8080/api/get-recipes.php

# Run the test suites (no package manager involved)
php tests/run-php.php
node --test "tests/*.test.js"

# Deploy to the live site (see Deployment below before using this)
./scripts/deploy.sh --dry-run
```

No build step, no package manager. PHP syntax errors surface at runtime, so lint every touched `.php` file before committing.

## Architecture

### Request flow

Each page (`index.php`, `rezepte.php`, `wochenplan.php`, `einkaufsliste.php`, `admin.php`) is a self-contained HTML document. All dynamic behaviour is in `js/app.js` which calls `api/*.php` endpoints via `fetch`. There is no client-side router — navigation is full-page.

`kochen.php` is the one exception: it loads `js/cook-format.js` + `js/cook.js` instead of `app.js`, because the kitchen view has no use for the plan, shopping-list and admin logic that `app.js` carries.

### PHP layer

```
config.php          → loads .env, returns $config array
bootstrap.php       → logging helpers
db.php              → PDO singleton, schema creation, auto-migrations via ALTER TABLE
sqlite-store.php    → all DB read/write functions (recipes, plan, shopping list, confirmed plans)
recipe-utils.php    → recipe_to_api_array(), body/markdown helpers, tag parsing
category-utils.php  → normalize_category(), category CRUD helpers
```

`sqlite-store.php` is the single source of truth for persistence. Every API endpoint requires `config.php` + `sqlite-store.php` and calls functions from there — never raw PDO directly.

### Data model

| Table | Key fields |
|---|---|
| `recipes` | `type` (normal/thermomix/airfryer), `rating` (0–5), `plan_appearances` |
| `current_plan_days` | `position`, `day_name`, `recipe_snapshot` (JSON), `is_disabled` |
| `confirmed_plans` | `week_label`, `plan_json` (full plan snapshot), `confirmed_at` |
| `categories` | `id` (slug), `label`, `is_default` |
| `cook_progress` | single row, `recipe_id`, `step_index`, `servings`, `timer_ends_at` |
| `app_settings` | key/value pairs for Gemini model, template path, etc. |

Schema changes go in `db_initialize_schema()` in `db.php`. Always add a migration guard:
```php
if (!in_array('new_column', $cols, true)) {
    $pdo->exec('ALTER TABLE tbl ADD COLUMN new_column ...');
}
```

### API conventions

- All endpoints return `Content-Type: application/json`
- Errors: `{"error": "message"}` with an appropriate HTTP status code
- Success: `{"message": "...", "data": ...}` or a direct domain object
- GET = read, POST = write — no PUT/PATCH/DELETE

### Frontend

`js/app.js` is one large `DOMContentLoaded` closure (plus a small IIFE at the top for theme initialisation). All pages share it. Guard feature code with `if (element)` checks — most elements only exist on one page.

`fetchJsonOrThrow(url, options)` is the central fetch wrapper; use it everywhere instead of raw `fetch`.

### Darkmode

Applied via `html.dark` class. A tiny inline `<script>` in each page's `<head>` reads `localStorage` to set the class before first paint. The toggle button (`#btn-theme-toggle`) is wired up inside `DOMContentLoaded`. All dark-mode overrides live at the bottom of `css/style.css` under `/* ===== Dark Mode ===== */`.

### Wochenplan logic

- **Generate**: `api/generate-plan.php` picks 7 recipes weighted by `rating` (weight 1–8, unrated = 3). Uses `weighted_random_pick()` defined in that file.
- **Confirm**: `api/confirm-plan.php` snapshots the current plan to `confirmed_plans` and increments `plan_appearances` for each active (non-disabled) recipe.
- **Disabled days**: stored as `is_disabled = 1` in `current_plan_days`; the shopping-list generator skips them.

### Cook mode

`cook-utils.php` owns **all** parsing. The API hands the frontend ingredients
already split into `{qty, qty_max, unit, rest, raw, ambiguous}`, so `js/cook.js`
only multiplies and formats — the German quantity grammar exists in exactly one
language. Never reimplement that parsing in JS.

- Steps come from `recipes.instructions_text`, one line per step. There is no
  step↔ingredient annotation in the schema; `cook_match_ingredients()` guesses
  from the names, in both directions so "Sahne" matches "Schlagsahne".
- Timers are detected from free text and returned as a **list** per step, never
  auto-started. Ranges take the upper bound.
- `ambiguous` marks bundled lines like "1 Ei, 2-3 TL Senf" — those must never be
  scaled, the leading quantity only covers the first part.
- Progress persists server-side in `cook_progress`; a running timer is stored as
  an absolute `timer_ends_at` so a page reload does not swallow elapsed time.

When touching the parsers, add a case to `tests/cook-utils.test.php` first.

### Gemini / KI pipeline

`api/gemini.php` wraps all Gemini calls. The plan-print export looks async to the frontend: `generate-plan-print.php` only **creates** the job and returns, `get-plan-print-status.php` is polled every 2s, and the first poll that claims the job also runs it — `plan_print_claim_job()` takes an exclusive `flock` for the `queued → running` transition so two parallel polls cannot spend the Gemini quota twice.

Do **not** turn this back into a background process. It used to spawn one via `exec()`, which is disabled on most shared hosting: the job was created and then sat in `data/print-jobs/` forever while the frontend reported "Print-Job konnte nicht gestartet werden". `process-plan-print-job.php` still exists as a CLI entry point for running a job by hand.

Image-model selection lives in `plan_print_resolve_image_model()` / `plan_print_available_models()` in `api/plan-print-lib.php`. It maps retired preview aliases to current GA IDs (default `gemini-3.1-flash-image`) and returns a **fallback chain** — the resolved model, then `gemini-2.5-flash-image`; `gemini_generate_content()` walks that list and tries the next entry on HTTP 404/429/403. When Google renames or retires a model, update the alias map and the available-models list there — the saved value in `app_settings` may still hold an old ID. `plan_print_image_config()` adds `imageSize => 2K` only for the 3.x models, so it needs the concrete model (`$imageModels[0]`), not the chain. Text-model defaults are in `config.php` (`GEMINI_MODEL`, comma-separated fallback list). Do not set `maxOutputTokens` on image requests — a generated image costs ~1300 output tokens and a low cap silently suppresses the image.

## Deployment

Targets any plain PHP 8.1+ shared host (Apache or LiteSpeed). Host, path and
credentials come from `deploy.env` — copy `deploy.env.example` and fill it in.

```bash
./scripts/deploy.sh --dry-run   # inspect first, always
./scripts/deploy.sh             # update
```

Credentials live in `deploy.env` (gitignored, `deploy.env.example` is the
template). `./scripts/set-deploy-password.sh` writes the FTP password without
it passing through the shell history or the process list.

### Rules that must not be broken

The deploy is an **update of a live site that holds data the repo does not**.
The server's database, recipe images and `.env` are the authoritative copies —
the repo only ships a small demo database.

- **Never `--delete` by default.** Deletion is opt-in via `--delete-remote`.
  An earlier version ran `mirror --delete` and would have wiped the server's
  database, recipe images and `.env`, because those are excluded from upload.
- **`data/`, `rezepte/` and `.env` are never uploaded.** They belong to the
  server. `--with-database` exists but overwrites the live database — it is
  for a first install only, never for an update.
- **Excludes are applied by rsync into a staging dir, then that dir is
  mirrored.** Do not move the excludes into lftp: its `--exclude-glob` matches
  file names only, not directories, so `.git/`, `.claude/` and `tests/` slipped
  through and would have exposed the full repo history at `/.git/`.
- The script aborts if anything forbidden ends up in the staging dir.

### Server layout

`DEPLOY_PATH` depends on the host. Many FTP accounts are chrooted to the app
directory, in which case `DEPLOY_PATH=/` is correct and there is no visible
`public_html` from the FTP side. Check with a manual FTP login before the first
deploy.

`DEPLOY_TLS_VERIFY=no` exists because some shared hosts serve a wildcard
certificate that matches none of the account's host names. Setting it keeps the
transfer encrypted but unauthenticated — only use it when the host actually
forces it.

### Access control

The app has no user management, and the site is publicly reachable. Protection
is done in `.htaccess`:

- All dotfiles, `*.md`, `*.sqlite`, `*.log`, `*.zip` and the `data/`, `tests/`,
  `scripts/` directories return 403. Without this rule `.env` — and with it the
  Gemini API key — is downloadable over plain HTTP. Verify it after every
  deploy; a host that ignores `.htaccess` (nginx) needs equivalent rules in the
  server config instead.
- Reading (browsing, cook mode, all `get-*` endpoints plus
  `save-cook-progress` / `finish-cook-session`) stays open.
- Everything that writes, deletes or costs Gemini quota sits behind Basic Auth,
  in the block between `# BEGIN Schreibschutz` and `# END Schreibschutz`.
  Toggle it with `./scripts/set-edit-password.sh [--off]`.

Two traps that already cost time here:

- **`.htpasswd` must be mode 644.** At 600 the web server cannot read it and
  rejects *every* password, correct or not, which looks exactly like a wrong
  password. It stays unreachable over HTTP through the dotfile rule.
- **An unbalanced `<FilesMatch>` takes down the whole site with HTTP 500.**
  The toggle in `set-edit-password.sh` counts opening and closing directives
  and refuses to write the file if they do not match. Keep that check.

## Coding conventions

- 4-space indentation in PHP, JS, and CSS
- API filenames: kebab-case (`rate-recipe.php`)
- Category IDs: lowercase slugs (`hauptspeise`, `fruehstueck`)
- Recipe `typ` values: `normal`, `thermomix`, `airfryer` — validated in `sqlite_recipe_payload()`
- When adding a new recipe field: update `recipe_to_api_array()`, `sqlite_recipe_payload()`, the relevant INSERT/UPDATE statement, and all form selects that expose it
