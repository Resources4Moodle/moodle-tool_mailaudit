# Changelog — tool_mailaudit (Sent email audit)

All notable changes to this plugin are documented here.

## v1.0.0 (2026) — 2026063002

First stable release for the Moodle plugins directory. Targets Moodle 5.2 (`supported = [502, 502]`).

- **Event-based capture:** records outbound notifications, private and group messages from core
  events (reading core tables for content) instead of a message send-path callback — no
  `debug_backtrace` on the send path. Retains the `post_forgot_password_requests` callback for
  password-reset mail (no 5.2 event/hook seam). Delivery channel recorded best-effort in metadata.
- **Privacy:** complete privacy provider declaring every stored personal-data column; message-body
  storage is off by default; a privacy notice and capture-health panel are shown on the settings page.
- **Searchable, exportable audit:** filter by kind/status/date; per-record view; CSV export; bulk and
  purge tools with a configurable retention window.
- **Schema:** trimmed redundant indexes and added a `messageid` index (install.xml + upgrade step).
- **Operator visibility:** capture-failure reporting (`lastcaptureerror`) surfaced for debugging.
- **Quality/shipping:** `pix/icon.svg`; moodle-plugin-ci clean (phpcs, phpdoc, validate, savepoints,
  mustache, grunt); PHPUnit + a Behat feature; GitHub Actions matrix on PostgreSQL and MariaDB.
