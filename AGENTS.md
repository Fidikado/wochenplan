# Repository Guidelines

## Project Structure & Module Organization
This repository is a plain-PHP recipe app. Root pages are `index.php` (recipe import/create), `rezepte.php` (list, detail, edit), `wochenplan.php`, `monatsplanung.php`, `einkaufsliste.php`, `kochen.php` (cook mode), and `admin.php`. APIs live in `api/`; shared helpers are in `config.php`, `bootstrap.php`, `category-utils.php`, `recipe-utils.php`, `cook-utils.php`, `db.php`, and `sqlite-store.php`. Gemini plan-print orchestration is split between `api/generate-plan-print.php`, `api/process-plan-print-job.php`, `api/get-plan-print-status.php`, and `api/plan-print-lib.php`. Frontend logic is centralized in `js/app.js`, styling in `css/style.css`, assets in `assets/`. The cook mode ships its own `js/cook.js` and `js/cook-format.js` and is the only page that does not load `app.js`. Recipe files are stored as Markdown in `rezepte/`; runtime JSON, logs, templates, SQLite data, and print jobs/output are stored in `data/`.

## Build, Test, and Development Commands
- `php -S 127.0.0.1:8000` starts the app locally from the repo root.
- `php -l api/save-recipe.php` checks syntax for a changed PHP file.
- `php -l category-utils.php` validates shared helpers before commit.
- `php -l api/plan-print-lib.php` checks the Gemini print pipeline after backend changes.
- `node --check js/app.js` validates the central frontend script after UI updates.
- `php tests/run-php.php` runs the PHP tests for the cook-mode logic.
- `node --test "tests/*.test.js"` runs the JS tests for scaling and formatting.
- `http://127.0.0.1:8000/api/get-recipes.php` is a useful smoke test for API output.

There is no framework build step or package manager.

## Coding Style & Naming Conventions
Use 4-space indentation in PHP, JS, and CSS. Keep the architecture simple and framework-free unless explicitly requested. API responses should stay JSON-based with `error` or domain payloads such as `message`, `data`, or objects. Use kebab-case for PHP endpoint filenames, e.g. `generate-plan-print.php`. Category IDs should stay lowercase and slug-like, e.g. `hauptspeise`, `fruehstueck`.

## Testing Guidelines
There is no automated test suite yet. Validate changes by linting touched PHP files, loading the affected page locally, and calling the related API endpoint directly. When changing recipe parsing or frontmatter handling, verify both existing recipes in `rezepte/` and create/edit flows. For plan or shopping-list changes, test both page rendering and saved JSON output in `data/`.

## Data & Configuration Notes
`config.php` loads `.env`; relevant keys include `GEMINI_API_KEY`, `GEMINI_MODEL`, and optional `GEMINI_NANO_BANANA_MODEL`. The active Gemini image model for plan export can also be overridden in SQLite settings via the admin UI. Recipe Markdown frontmatter is parser-sensitive. If you change fields like `title`, `kategorie`, `typ`, `tags`, `image`, or `kochzeit`, update all affected CRUD, planning, Thermomix-conversion, and shopping-list endpoints consistently. Always keep file operations under `rezepte/`, `rezepte/images/`, and `data/` path-safe.

## Commit & Pull Request Guidelines
Follow the current history style: short, imperative commit subjects like `Add recipe categories` or `Improve admin category layout`. Keep commits focused by feature or fix. PRs should briefly describe the user-visible change, list affected pages or endpoints, and include screenshots for UI changes or notes for data/config migrations.
