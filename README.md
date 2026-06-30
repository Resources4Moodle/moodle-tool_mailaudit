# Sent email audit (tool_mailaudit)

[![Moodle Plugin CI](https://github.com/Resources4Moodle/moodle-tool_mailaudit/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/Resources4Moodle/moodle-tool_mailaudit/actions/workflows/moodle-ci.yml)

An admin tool that audits outbound Moodle mail. It records the notifications and
messages Moodle dispatches (plus failed direct email and password-reset mail) into
a searchable, exportable audit table, with per-course visibility for teachers and
site-wide visibility for administrators.

- **Plugin type:** Admin tool (`admin/tool/mailaudit`), component `tool_mailaudit`
- **Supported Moodle:** 5.2 (`MOODLE_502_STABLE`)
- **Supported PHP:** 8.2 – 8.4
- **Databases:** PostgreSQL and MySQL/MariaDB
- **Maturity:** Stable · **Release:** 1.0.0
- **License:** [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html)

## Features

- Event-driven capture (no core patches, no neighbouring-plugin coupling).
- Mailbox-style browser: filters, active-filter chips, drill-down stat cards,
  CSV export, bulk and whole-result-set soft-delete / purge.
- Capability-scoped visibility (`:view`, `:viewown`, `:viewcourse`, `:delete`) with
  an always-admin-only gate for sensitive mail (password reset, new-IP login, etc.).
- Full GDPR privacy provider; body storage **off by default**.
- Scheduled retention purge and operator-visible capture-health panel.

## Installation

1. Copy the plugin to `admin/tool/mailaudit` in your Moodle 5.2 site
   (`public/admin/tool/mailaudit` on public-dir 5.x layouts).
2. Visit **Site administration → Notifications** to complete the database install.
3. Configure under **Site administration → Plugins → Admin tools → Sent email audit**.

Or install the release zip via **Site administration → Plugins → Install plugins**.

## Capture coverage and the channel limitation

See [docs/README.md](docs/README.md). In short: message capture is event-driven from
`notification_sent` / `message_sent` / `group_message_sent`; Moodle 5.2 exposes no
event/Hook API identifying the delivery channel of a *successful* send, so the
channel is recorded best-effort in metadata. Password-reset mail (no event/Hook
seam) is captured via the single `post_forgot_password_requests` callback with the
token redacted.

## Testing

See [docs/TESTING.md](docs/TESTING.md). CI runs Moodle Plugin CI (phplint, phpcs,
phpdoc, validate, savepoints, mustache, grunt, PHPUnit, Behat) against Moodle 5.2 on
PostgreSQL and MariaDB via [.github/workflows/moodle-ci.yml](.github/workflows/moodle-ci.yml).
