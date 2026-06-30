# Testing `tool_mailaudit` on a parallel server

These steps assume a separate (non-production) Moodle 5.2+ checkout with the plugin
installed at `public/admin/tool/mailaudit/` (dirroot may be `.../public` on 5.x
public-dir installs — adjust paths accordingly). Run everything as the web user.

> The plugin ships PHPUnit tests under `tests/`. Behat is provided as guidance
> below (no feature files are bundled yet — add under `tests/behat/` as needed).

## 1. PHPUnit

Prerequisites: a dedicated test database configured in `config.php`
(`$CFG->phpunit_prefix`, `$CFG->phpunit_dataroot`).

```bash
# From dirroot (e.g. /var/www/html/moodle/public on 5.x public-dir layout):
php admin/tool/phpunit/cli/init.php          # (re)initialise the PHPUnit environment
# or, on older trees:  php admin/cli/phpunit_init.php

# Run only this plugin's suite:
vendor/bin/phpunit --filter tool_mailaudit
# or by path:
vendor/bin/phpunit admin/tool/mailaudit/tests/
```

Bundled test classes:
- `tests/repository_test.php` — `where_sql` building, negation, includedeleted,
  visibility predicate, `course_options` scoping, `stats_overview` shape.
- `tests/access_test.php` — admin tautology, no-capability lockout, teacher course
  scope, sensitive-kind exclusion, sensitive-record block.
- `tests/capture_test.php` — mail-kind classifier (`infer_kind`) data provider.

Re-run `init.php` whenever `db/install.xml`, `db/access.php` or version changes.

## 2. Behat (acceptance)

Prerequisites: `$CFG->behat_prefix`, `$CFG->behat_dataroot`, `$CFG->behat_wwwroot`
set, plus a Selenium/Chromedriver for JS scenarios (the selection UI is JS-driven).

```bash
php admin/tool/behat/cli/init.php
# Headless chrome example:
vendor/bin/behat --config $CFG->behat_dataroot/behat/behat.yml --tags @tool_mailaudit
```

Suggested scenarios to author under `tests/behat/`:
- Admin browses, applies filters, sees the filter chips and result count.
- Clicking a stat card (Top sender / Top course / Today) drills the list down.
- Bulk select-all-on-page → selected count updates → sticky bar appears (@javascript).
- "Select all matching" → confirm → soft delete → records hidden.
- CSV export returns a file with the matching rows.
- A teacher sees only their course's non-sensitive mail; cannot open a sensitive
  record.

## 3. Coding-style / compliance checks (recommended before upload)

```bash
# Moodle Code Checker (PHP_CodeSniffer with moodle standard)
php admin/tool/phpcs/... # or: vendor/bin/phpcs --standard=moodle admin/tool/mailaudit

# PHPDoc checker / mustache / savepoints
php admin/tool/phpunit/cli/init.php && vendor/bin/phpunit --filter tool_mailaudit

# JS: rebuild AMD properly on a build host (replaces the hand-authored build file)
npx grunt amd --root=admin/tool/mailaudit
# Lint JS/CSS:
npx grunt eslint:amd stylelint --root=admin/tool/mailaudit
```

## 4. Notes

- The bundled `amd/build/selection.min.js` is hand-authored and functional; for a
  Moodle.org plugins-directory release, regenerate it with `grunt amd` on a host
  that has Node + the Moodle grunt toolchain so the build matches `amd/src/`.
- No Behat feature files are bundled yet; CI that runs `--tags @tool_mailaudit`
  will simply find none until they are added.
