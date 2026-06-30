# Sent email audit (`tool_mailaudit`) — capture coverage

> **Design rule:** this plugin never modifies Moodle core and never reads or writes
> another plugin's tables. All capture happens through core **events**, one core
> **forgot-password callback**, and the public `record_email_send()` API, so core and
> neighbouring plugins stay clean across upgrades.

## What is captured automatically (Moodle 5.2, no core changes)

| Mail flow | Mechanism | Status recorded |
|-----------|-----------|-----------------|
| Notifications (enrolment, forum, assignment, security, etc.) | `\core\event\notification_sent` observer, reading the core `notifications` row | sent |
| Private (1:1) messages | `\core\event\message_sent` observer, reading the core `messages` row | sent |
| Group-conversation messages | `\core\event\group_message_sent` observer (queued for the email digest task) | queued |
| Failed direct `email_to_user()` | `\core\event\email_failed` observer | failed |
| Forgot-password / password-change-info mail | `post_forgot_password_requests` callback (token redacted) | sent |

The message capture is **event-driven** — no `lib.php` message callback and no
`debug_backtrace()` on the send path. The observers read the persisted core
notifications/messages row for subject, body, component and context.

## Delivery-channel limitation (by Moodle design)

Moodle decides **per recipient at send time** whether a notification/message is
delivered by the email processor (vs popup or mobile), and exposes **no event or
Hook API** that identifies the channel of a *successful* send. The plugin therefore
records the message Moodle dispatched and annotates the delivery channel best-effort
in each row's `metadata` (`deliverychannel`, `channelnote`). Successful direct
`email_to_user()` calls that bypass the message API (e.g. **block_quickmail**) leave
no core event or database trace and are out of scope without coupling to core or
another plugin — explicitly **not** adopted, to keep the plugin standalone.

### The one remaining `lib.php` seam
Successful password-reset mail also has no event/Hook API seam in 5.2, so it is
captured through the single `post_forgot_password_requests` callback. The reset
**token is never stored** (redacted to `[redacted]`).

### Forward-compatible API
If a future Moodle release fires a Hook API event on successful `email_to_user()`,
the plugin's existing public API plugs straight in with no coupling:

```php
\tool_mailaudit\local\capture::record_email_send(
    $user, $from, $mailerlike, $subject, $messagetext, $messagehtml,
    $attachment, $attachname, $replyto, $replytoname, 'sent', '');
```

(`$mailerlike` need only expose `Body`, `AltBody`, `ContentType`, `Subject`,
`MessageID`.)

## Privacy posture

Body storage (`storebodytext`, `storebodyhtml`) is **disabled by default** to
minimise retained personal data; enable it deliberately. The privacy provider
declares every stored personal-data column and supports export/delete. Capture
failures are surfaced to the operator via `debugging()` and the **Capture health**
panel on the settings page rather than failing silently.

## Course attribution (`courseid`)

Course-scoped visibility depends on `courseid` being set on each record. The capture
layer recovers it best-effort from the event, message `customdata`, and `contexturl`
(course/cmid/contextid). Mail with no course context stays site-level (`courseid`
null) and is visible only to site-level viewers, not course-scoped teachers.
