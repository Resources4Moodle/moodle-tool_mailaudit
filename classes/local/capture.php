<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_mailaudit\local;

/**
 * Captures outbound Moodle email into the audit table.
 *
 * Design (Moodle 5.2): this plugin never patches core and never reads another plugin's
 * tables. Message-API mail (notifications, private messages, group conversations) is captured
 * from the core {@see \core\event\notification_sent}, {@see \core\event\message_sent} and
 * {@see \core\event\group_message_sent} events, reading the persisted core notifications/messages
 * row for content. Failed direct mail is captured from {@see \core\event\email_failed}.
 *
 * Channel note: Moodle decides per recipient at send time whether a notification/message is
 * delivered by the email processor (vs popup/mobile), and exposes no event/hook identifying the
 * channel of a *successful* send. We therefore record the message Moodle dispatched and annotate
 * the delivery channel best-effort in metadata. The single remaining lib.php callback,
 * post_forgot_password_requests, captures password-reset mail, which has no event or hook seam.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capture {
    /**
     * Capture a notification dispatched by Moodle (core notifications table).
     *
     * @param int $notificationid Notification id (event objectid).
     * @param int $courseid Course id from the event (SITEID when unrelated).
     */
    public static function capture_notification(int $notificationid, int $courseid): void {
        global $DB;

        if ($notificationid <= 0 || !self::capture_enabled()) {
            return;
        }

        try {
            $row = $DB->get_record('notifications', ['id' => $notificationid], '*', IGNORE_MISSING);
            if (!$row) {
                return;
            }
            self::capture_message_row(
                'notifications',
                $notificationid,
                $row,
                (int)($row->useridfrom ?? 0),
                (int)($row->useridto ?? 0),
                $courseid,
                'sent',
                true
            );
        } catch (\Throwable $e) {
            self::report_failure('capture_notification', $e);
        }
    }

    /**
     * Capture a private (1:1) message dispatched by Moodle (core messages table).
     *
     * @param int $messageid Message id (event objectid).
     * @param int $touserid Recipient id (event relateduserid).
     * @param int $courseid Course id from the event.
     */
    public static function capture_message(int $messageid, int $touserid, int $courseid): void {
        global $DB;

        if ($messageid <= 0 || !self::capture_enabled()) {
            return;
        }

        try {
            $row = $DB->get_record('messages', ['id' => $messageid], '*', IGNORE_MISSING);
            if (!$row) {
                return;
            }
            self::capture_message_row(
                'messages',
                $messageid,
                $row,
                (int)($row->useridfrom ?? 0),
                $touserid,
                $courseid,
                'sent',
                false
            );
        } catch (\Throwable $e) {
            self::report_failure('capture_message', $e);
        }
    }

    /**
     * Capture a group-conversation message dispatched by Moodle.
     *
     * Group conversation email is queued by the email processor into message_email_messages and
     * sent later by a digest task, so it is recorded as queued with no single recipient.
     *
     * @param int $messageid Message id (event objectid).
     * @param int $conversationid Conversation id from the event.
     * @param int $courseid Course id from the event.
     */
    public static function capture_group_message(int $messageid, int $conversationid, int $courseid): void {
        global $DB;

        if ($messageid <= 0 || !self::capture_enabled()) {
            return;
        }

        try {
            $row = $DB->get_record('messages', ['id' => $messageid], '*', IGNORE_MISSING);
            if (!$row) {
                return;
            }
            self::capture_message_row(
                'messages',
                $messageid,
                $row,
                (int)($row->useridfrom ?? 0),
                0,
                $courseid,
                'queued',
                false,
                [
                    'conversationid' => $conversationid,
                    'conversationtype' => 'group',
                    'channelnote' => 'Group conversation email is queued for Moodle\'s digest task; '
                        . 'SMTP success is not exposed to plugins.',
                ]
            );
        } catch (\Throwable $e) {
            self::report_failure('capture_group_message', $e);
        }
    }

    /**
     * Build and insert an audit row from a core notifications/messages record.
     *
     * @param string $table Core source table (notifications|messages).
     * @param int $messageid Core message/notification id.
     * @param \stdClass $row Core message/notification record.
     * @param int $fromuserid Sender id.
     * @param int $touserid Recipient id (zero for group conversations).
     * @param int $eventcourseid Course id from the event.
     * @param string $status Audit status (sent|queued|failed).
     * @param bool $isnotification Whether this is a notification (vs private message).
     * @param array $extrameta Additional metadata to merge.
     */
    private static function capture_message_row(
        string $table,
        int $messageid,
        \stdClass $row,
        int $fromuserid,
        int $touserid,
        int $eventcourseid,
        string $status,
        bool $isnotification,
        array $extrameta = []
    ): void {
        global $DB, $CFG;

        // Idempotent: one audit row per (source table, source id, recipient).
        if (
            $DB->record_exists(repository::TABLE, [
                'moodlemessagetable' => $table,
                'moodlemessageid' => $messageid,
                'touserid' => $touserid ?: null,
            ])
        ) {
            return;
        }

        $component = self::short_string($row->component ?? 'core', 100);
        $messagename = self::short_string($row->eventtype ?? ($isnotification ? '' : 'instantmessage'), 100);
        $subject = self::string_value($row->subject ?? '');
        $courseid = self::resolve_courseid_from_row($eventcourseid, $row);

        $metadata = [
            'source' => $isnotification ? 'notification_sent_event' : 'message_sent_event',
            'capturemode' => 'plugin_event_observer',
            'deliverychannel' => 'messaging',
            'channelnote' => $extrameta['channelnote']
                ?? 'Delivery channel (email vs popup/mobile) is chosen per recipient by Moodle at send '
                    . 'time and is not exposed to plugins; this records the dispatched message.',
            'contexturl' => self::string_value($row->contexturl ?? ''),
            'contexturlname' => self::string_value($row->contexturlname ?? ''),
            'courseid' => $courseid,
            'wwwroot' => $CFG->wwwroot ?? '',
        ];
        foreach (['conversationid', 'conversationtype'] as $key) {
            if (isset($extrameta[$key])) {
                $metadata[$key] = $extrameta[$key];
            }
        }

        $kind = self::short_string(self::infer_kind($component, $messagename, $subject, [], $metadata), 40);
        if (!self::should_capture_kind($kind)) {
            return;
        }

        $bodytext = self::string_value($row->fullmessage ?? '');
        $bodyhtml = self::string_value($row->fullmessagehtml ?? '');

        $record = self::base_row([
            'timecreated' => isset($row->timecreated) ? (int)$row->timecreated : time(),
            'status' => $status,
            'kind' => $kind,
            'component' => $component,
            'messagename' => $messagename,
            'originfile' => 'message/output/email/message_output_email.php',
            'moodlemessageid' => $messageid,
            'moodlemessagetable' => $table,
            'subject' => $subject,
            'bodytext' => self::store_bodytext() ? $bodytext : '',
            'bodyhtml' => self::store_bodyhtml() ? $bodyhtml : '',
            'bodybytes' => strlen($bodytext) + strlen($bodyhtml),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        self::apply_user($record, 'from', $fromuserid);
        self::apply_user($record, 'to', $touserid ?: null);
        self::apply_course($record, $courseid);

        $DB->insert_record(repository::TABLE, (object) $record, false);
    }

    /**
     * Record or mark a failed email event emitted by Moodle core.
     *
     * @param \core\event\email_failed $event Email failed event.
     */
    public static function record_email_failed_event(\core\event\email_failed $event): void {
        global $DB, $CFG;

        if (!self::capture_enabled()) {
            return;
        }

        try {
            $subject = self::string_value($event->other['subject'] ?? '');
            $message = self::string_value($event->other['message'] ?? '');
            $errorinfo = self::string_value($event->other['errorinfo'] ?? '');

            if (self::mark_matching_failure((int)$event->userid, (int)$event->relateduserid, $subject, $errorinfo)) {
                return;
            }

            $metadata = [
                'source' => 'core_email_failed_event',
                'capturemode' => 'plugin_event_observer',
                'errorinfo' => $errorinfo,
                'wwwroot' => $CFG->wwwroot ?? '',
            ];
            $kind = self::short_string(self::infer_kind('core', '', $subject, [], $metadata), 40);
            if (!self::should_capture_kind($kind)) {
                return;
            }

            $record = self::base_row([
                'status' => 'failed',
                'kind' => $kind,
                'component' => 'core',
                'originfile' => 'core/event/email_failed',
                'subject' => $subject,
                'bodytext' => self::store_bodytext() ? $message : '',
                'bodybytes' => strlen($message),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            self::apply_user($record, 'from', (int)$event->userid);
            self::apply_user($record, 'to', (int)$event->relateduserid);

            $DB->insert_record(repository::TABLE, (object) $record, false);
        } catch (\Throwable $e) {
            self::report_failure('record_email_failed_event', $e);
        }
    }

    /**
     * Record a cooperative direct email_to_user() attempt.
     *
     * Not called from Moodle core; kept as an opt-in API for local plugins (or a future core hook)
     * that choose to report direct email attempts without patching lib/moodlelib.php.
     *
     * @param object $user Recipient user.
     * @param object|string $from Sender user or sender name.
     * @param object $mail PHPMailer-like instance exposing Body/AltBody/ContentType/Subject/MessageID.
     * @param string $rawsubject Original subject passed to email_to_user().
     * @param string $messagetext Final text body.
     * @param string $messagehtml Final HTML body.
     * @param string $attachment Attachment path if one was supplied.
     * @param string $attachname Attachment name if one was supplied.
     * @param string $replyto Reply-to email.
     * @param string $replytoname Reply-to display name.
     * @param string $status sent|failed.
     * @param string $errorinfo Optional mailer error.
     */
    public static function record_email_send(
        $user,
        $from,
        object $mail,
        string $rawsubject,
        string $messagetext = '',
        string $messagehtml = '',
        string $attachment = '',
        string $attachname = '',
        string $replyto = '',
        string $replytoname = '',
        string $status = 'sent',
        string $errorinfo = ''
    ): void {
        global $DB, $CFG;

        if (!self::capture_enabled()) {
            return;
        }

        try {
            $stack = self::store_stacktrace() ? self::normalise_stack(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40)) : [];
            $origin = $stack ? self::find_origin($stack) : [];
            $component = self::component_from_path($origin['file'] ?? '');
            $kind = self::infer_kind($component, '', $rawsubject, $stack, []);
            if (!self::should_capture_kind($kind)) {
                return;
            }
            $courseid = self::resolve_courseid([]);

            $contenttype = self::short_string($mail->ContentType ?? '');
            if ($contenttype === 'text/html') {
                $bodyhtml = self::string_value($mail->Body ?? $messagehtml);
                $bodytext = self::string_value($mail->AltBody ?? $messagetext);
            } else {
                $bodytext = self::string_value($mail->Body ?? $messagetext);
                $bodyhtml = self::string_value($messagehtml);
            }

            $metadata = [
                'source' => 'record_email_send_api',
                'capturemode' => 'cooperative_api',
                'rawsubject' => $rawsubject,
                'contenttype' => $contenttype,
                'errorinfo' => $errorinfo,
                'courseid' => $courseid,
                'wwwroot' => $CFG->wwwroot ?? '',
            ];

            $record = self::base_row([
                'status' => self::short_string($status, 16),
                'kind' => self::short_string($kind, 40),
                'component' => self::short_string($component, 100),
                'originfile' => self::short_string($origin['file'] ?? '', 255),
                'originline' => isset($origin['line']) ? (int)$origin['line'] : null,
                'messageid' => self::short_string($mail->MessageID ?? '', 255),
                'subject' => self::string_value($mail->Subject ?? $rawsubject),
                'bodytext' => self::store_bodytext() ? $bodytext : '',
                'bodyhtml' => self::store_bodyhtml() ? $bodyhtml : '',
                'replyto' => self::short_string(trim($replyto . ($replytoname ? ' (' . $replytoname . ')' : '')), 255),
                'attachmentcount' => $attachname !== '' ? 1 : 0,
                'attachmentnames' => $attachname,
                'bodybytes' => strlen($bodytext) + strlen($bodyhtml),
                'stackjson' => $stack ? json_encode($stack, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            self::apply_user($record, 'from', $from);
            self::apply_user($record, 'to', $user);
            self::apply_course($record, $courseid);

            $DB->insert_record(repository::TABLE, (object) $record, false);
        } catch (\Throwable $e) {
            self::report_failure('record_email_send', $e);
        }
    }

    /**
     * Record password-reset email accepted by Moodle's forgot-password flow.
     *
     * Moodle 5.2 exposes no event or Hook API seam for a successful password-reset send, so this
     * single lib.php callback (post_forgot_password_requests) remains the only way to audit it.
     * The reset token is never stored.
     *
     * @param \stdClass $data Submitted forgot password form data.
     */
    public static function record_forgot_password_request(\stdClass $data): void {
        global $DB;

        if (!self::capture_enabled()) {
            return;
        }

        try {
            $user = self::resolve_forgot_password_user($data);
            if (!$user || empty($user->confirmed) || empty($user->email)) {
                return;
            }

            $requesttime = isset($_SERVER['REQUEST_TIME']) ? (int)$_SERVER['REQUEST_TIME'] : time();
            if (self::uses_password_change_info($user)) {
                self::record_password_change_info($user, $requesttime);
                return;
            }

            $resetrecord = $DB->get_record('user_password_resets', ['userid' => (int)$user->id]);
            if (!$resetrecord) {
                return;
            }

            $send = self::recent_reset_send_time($resetrecord, $requesttime);
            if (!$send) {
                return;
            }

            self::record_password_reset_confirmation($user, $resetrecord, $send['time'], $send['field'], $requesttime);
        } catch (\Throwable $e) {
            self::report_failure('record_forgot_password_request', $e);
        }
    }

    /**
     * Resolve the most reliable course id available for a cooperative direct send.
     *
     * @param array $pending Pending message metadata.
     * @return int Course id, or zero when no course context is known.
     */
    private static function resolve_courseid(array $pending): int {
        global $COURSE;

        $candidates = [
            $pending['courseid'] ?? 0,
            is_object($COURSE ?? null) ? ($COURSE->id ?? 0) : 0,
            $_REQUEST['courseid'] ?? 0,
            self::courseid_from_contexturl($pending['contexturl'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $courseid = self::normalise_courseid($candidate);
            if ($courseid) {
                return $courseid;
            }
        }

        return 0;
    }

    /**
     * Resolve the best course id for a captured core message/notification row.
     *
     * @param int $eventcourseid Course id supplied by the event.
     * @param \stdClass $row Core message/notification record.
     * @return int Course id, or zero.
     */
    private static function resolve_courseid_from_row(int $eventcourseid, \stdClass $row): int {
        $candidates = [
            self::normalise_courseid($eventcourseid),
            self::courseid_from_customdata($row->customdata ?? null),
            self::courseid_from_contexturl(self::string_value($row->contexturl ?? '')),
        ];
        foreach ($candidates as $courseid) {
            if ($courseid) {
                return $courseid;
            }
        }
        return 0;
    }

    /**
     * Resolve a course id from message customdata when components provide it there.
     *
     * @param mixed $customdata Message custom data.
     * @return int Course id, or zero.
     */
    private static function courseid_from_customdata($customdata): int {
        if (is_string($customdata)) {
            $decoded = json_decode($customdata);
            if (json_last_error() === JSON_ERROR_NONE) {
                $customdata = $decoded;
            }
        }

        if (!is_array($customdata) && !is_object($customdata)) {
            return 0;
        }

        foreach (['courseid', 'course_id'] as $key) {
            $value = is_array($customdata) ? ($customdata[$key] ?? null) : ($customdata->$key ?? null);
            $courseid = self::normalise_courseid($value);
            if ($courseid) {
                return $courseid;
            }
        }

        $course = is_array($customdata) ? ($customdata['course'] ?? null) : ($customdata->course ?? null);
        if (is_array($course) || is_object($course)) {
            $value = is_array($course) ? ($course['id'] ?? null) : ($course->id ?? null);
            return self::normalise_courseid($value);
        }

        return 0;
    }

    /**
     * Resolve a course id from a context URL query string.
     *
     * @param string $url URL.
     * @return int Course id, or zero.
     */
    private static function courseid_from_contexturl(string $url): int {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return 0;
        }

        $params = [];
        parse_str($query, $params);
        if (strpos($path, '/course/view.php') !== false) {
            $courseid = self::normalise_courseid($params['id'] ?? 0);
            if ($courseid) {
                return $courseid;
            }
        }
        if (strpos($path, '/mod/') !== false) {
            $courseid = self::courseid_from_cmid((int)($params['id'] ?? 0));
            if ($courseid) {
                return $courseid;
            }
        }
        foreach (['cmid', 'coursemoduleid'] as $key) {
            $courseid = self::courseid_from_cmid((int)($params[$key] ?? 0));
            if ($courseid) {
                return $courseid;
            }
        }
        if (!empty($params['contextid'])) {
            $courseid = self::courseid_from_contextid((int)$params['contextid']);
            if ($courseid) {
                return $courseid;
            }
        }
        foreach (['courseid', 'course'] as $key) {
            $courseid = self::normalise_courseid($params[$key] ?? 0);
            if ($courseid) {
                return $courseid;
            }
        }

        return 0;
    }

    /**
     * Resolve a course id from a course module id.
     *
     * @param int $cmid Course module id.
     * @return int Course id, or zero.
     */
    private static function courseid_from_cmid(int $cmid): int {
        global $DB;

        if ($cmid <= 0) {
            return 0;
        }

        try {
            return self::normalise_courseid($DB->get_field('course_modules', 'course', ['id' => $cmid], IGNORE_MISSING));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Resolve a course id from a context id.
     *
     * @param int $contextid Context id.
     * @return int Course id, or zero.
     */
    private static function courseid_from_contextid(int $contextid): int {
        global $DB;

        if ($contextid <= 0) {
            return 0;
        }

        try {
            $context = $DB->get_record('context', ['id' => $contextid], 'id, contextlevel, instanceid', IGNORE_MISSING);
            if (!$context) {
                return 0;
            }
            if ((int)$context->contextlevel === CONTEXT_COURSE) {
                return self::normalise_courseid($context->instanceid);
            }
            if ((int)$context->contextlevel === CONTEXT_MODULE) {
                return self::courseid_from_cmid((int)$context->instanceid);
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }

    /**
     * Convert a possible course id to a real (non-site) course id.
     *
     * @param mixed $value Candidate value.
     * @return int Course id, or zero.
     */
    private static function normalise_courseid($value): int {
        if (!is_numeric($value)) {
            return 0;
        }

        $courseid = (int)$value;
        $siteid = defined('SITEID') ? SITEID : 1;
        return $courseid > $siteid ? $courseid : 0;
    }

    /**
     * Mark a recently captured row as failed when core emits email_failed.
     *
     * @param int $fromuserid Sender id.
     * @param int $touserid Recipient id.
     * @param string $subject Subject.
     * @param string $errorinfo Error info.
     * @return bool True if a matching row was updated.
     */
    private static function mark_matching_failure(
        int $fromuserid,
        int $touserid,
        string $subject,
        string $errorinfo
    ): bool {
        global $DB;

        $conditions = ['deleted = 0', 'timecreated >= :cutoff', "status <> 'failed'"];
        $params = ['cutoff' => time() - 3600];

        if ($fromuserid > 0) {
            $conditions[] = 'fromuserid = :fromuserid';
            $params['fromuserid'] = $fromuserid;
        }
        if ($touserid > 0) {
            $conditions[] = 'touserid = :touserid';
            $params['touserid'] = $touserid;
        }
        if ($subject !== '') {
            $conditions[] = 'subject = :subject';
            $params['subject'] = $subject;
        }

        $records = $DB->get_records_select(
            repository::TABLE,
            implode(' AND ', $conditions),
            $params,
            'id DESC',
            'id, metadata',
            0,
            10
        );
        if (!$records) {
            return false;
        }

        foreach ($records as $record) {
            $metadata = self::decode_metadata($record->metadata ?? '');
            $metadata['errorinfo'] = $errorinfo;
            $metadata['confirmedby'] = 'core_email_failed_event';
            $metadata['statushistory'][] = ['time' => time(), 'status' => 'failed', 'source' => 'core_email_failed_event'];

            $DB->update_record(repository::TABLE, (object) [
                'id' => $record->id,
                'timemodified' => time(),
                'status' => 'failed',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        return true;
    }

    /**
     * Resolve the user targeted by a forgot-password submission using the same query shape as core.
     *
     * @param \stdClass $data Submitted forgot password form data.
     * @return \stdClass|null Matching user, or null.
     */
    private static function resolve_forgot_password_user(\stdClass $data): ?\stdClass {
        global $CFG, $DB;

        $username = trim((string)($data->username ?? ''));
        if ($username !== '') {
            $params = [
                'username' => \core_text::strtolower($username),
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted' => 0,
                'suspended' => 0,
            ];
            $user = $DB->get_record('user', $params);
            return $user ?: null;
        }

        $email = trim((string)($data->email ?? ''));
        if ($email === '') {
            return null;
        }

        $sql = "SELECT *
                  FROM {user}
                 WHERE " . $DB->sql_equal('email', ':email1', false, true) . "
                   AND id IN (SELECT id
                                FROM {user}
                               WHERE mnethostid = :mnethostid
                                 AND deleted = 0
                                 AND suspended = 0
                                 AND " . $DB->sql_equal('email', ':email2', false, false) . ")";
        $params = [
            'email1' => $email,
            'email2' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];

        $user = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        return $user ?: null;
    }

    /**
     * Check whether core sends password-change info instead of a reset-token mail.
     *
     * @param \stdClass $user User record.
     * @return bool True when core sends informational mail.
     */
    private static function uses_password_change_info(\stdClass $user): bool {
        try {
            $userauth = get_auth_plugin($user->auth);
            $systemcontext = \context_system::instance();
            return !$userauth->can_reset_password()
                || !is_enabled_auth($user->auth)
                || !has_capability('moodle/user:changeownpassword', $systemcontext, $user->id);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Find the reset timestamp touched by the current request.
     *
     * @param \stdClass $resetrecord Password reset record.
     * @param int $requesttime Request start timestamp.
     * @return array|null Timestamp data, or null when no email was sent during this request.
     */
    private static function recent_reset_send_time(\stdClass $resetrecord, int $requesttime): ?array {
        $lowerbound = max(0, $requesttime - 10);
        $upperbound = time() + 10;
        $candidates = [];

        foreach (['timererequested', 'timerequested'] as $field) {
            $value = isset($resetrecord->{$field}) ? (int)$resetrecord->{$field} : 0;
            if ($value >= $lowerbound && $value <= $upperbound) {
                $candidates[] = ['time' => $value, 'field' => $field];
            }
        }

        if (!$candidates) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            return $b['time'] <=> $a['time'];
        });

        return $candidates[0];
    }

    /**
     * Record a password reset confirmation mail with its token redacted.
     *
     * @param \stdClass $user Recipient user.
     * @param \stdClass $resetrecord Password reset record.
     * @param int $senttime Time Moodle accepted the reset email.
     * @param string $sentfield Reset timestamp field used.
     * @param int $requesttime Request start timestamp.
     */
    private static function record_password_reset_confirmation(
        \stdClass $user,
        \stdClass $resetrecord,
        int $senttime,
        string $sentfield,
        int $requesttime
    ): void {
        global $CFG;

        $site = get_site();
        $supportuser = \core_user::get_support_user();
        $pwresetmins = isset($CFG->pwresettime) ? floor($CFG->pwresettime / MINSECS) : 30;

        $maildata = new \stdClass();
        foreach (\core_user::get_name_placeholders($user) as $field => $value) {
            $maildata->{$field} = $value;
        }
        $maildata->username = $user->username;
        $maildata->sitename = format_string($site->fullname);
        $maildata->link = $CFG->wwwroot . '/login/forgot_password.php?token=[redacted]';
        $maildata->admin = generate_email_signoff();
        $maildata->resetminutes = $pwresetmins;

        $message = get_string('emailresetconfirmation', '', $maildata);
        $subject = get_string('emailresetconfirmationsubject', '', format_string($site->fullname));
        $messageid = 'forgot-password:' . (int)$resetrecord->id . ':' . $senttime;
        $metadata = [
            'source' => 'post_forgot_password_requests',
            'capturemode' => 'plugin_callback',
            'directemail' => 'password_reset_confirmation',
            'resetid' => (int)$resetrecord->id,
            'senttimestamp' => $senttime,
            'sentfield' => $sentfield,
            'requesttime' => $requesttime,
            'tokenredacted' => true,
            'note' => 'Recorded after core forgot-password processing returned without cannotmailconfirm.',
        ];

        self::insert_forgot_password_audit_record(
            $user,
            $supportuser,
            $subject,
            $message,
            $messageid,
            'forgot_password',
            $senttime,
            169,
            $metadata,
            (int)$resetrecord->id,
            'user_password_resets'
        );
    }

    /**
     * Record a password-change information mail for auth methods that cannot use reset tokens.
     *
     * @param \stdClass $user Recipient user.
     * @param int $requesttime Request start timestamp.
     */
    private static function record_password_change_info(\stdClass $user, int $requesttime): void {
        $site = get_site();
        $supportuser = \core_user::get_support_user();

        $maildata = new \stdClass();
        foreach (\core_user::get_name_placeholders($user) as $field => $value) {
            $maildata->{$field} = $value;
        }
        $maildata->username = $user->username;
        $maildata->sitename = format_string($site->fullname);
        $maildata->admin = generate_email_signoff();

        if (!is_enabled_auth($user->auth)) {
            $message = get_string('emailpasswordchangeinfodisabled', '', $maildata);
            $subject = get_string('emailpasswordchangeinfosubject', '', format_string($site->fullname));
        } else {
            try {
                $mailinfo = get_auth_plugin($user->auth)->get_password_change_info($user);
            } catch (\Throwable $e) {
                return;
            }
            $message = self::string_value($mailinfo['message'] ?? '');
            $subject = self::string_value($mailinfo['subject'] ?? '');
        }

        if ($message === '' && $subject === '') {
            return;
        }

        $messageid = 'forgot-password-info:' . (int)$user->id . ':' . $requesttime;
        $metadata = [
            'source' => 'post_forgot_password_requests',
            'capturemode' => 'plugin_callback',
            'directemail' => 'password_change_info',
            'requesttime' => $requesttime,
            'auth' => self::short_string($user->auth ?? '', 40),
            'note' => 'Recorded after core forgot-password processing returned without cannotmailconfirm.',
        ];

        self::insert_forgot_password_audit_record(
            $user,
            $supportuser,
            $subject,
            $message,
            $messageid,
            'password_change_info',
            $requesttime,
            116,
            $metadata
        );
    }

    /**
     * Insert a direct forgot-password audit row.
     *
     * @param \stdClass $user Recipient user.
     * @param \stdClass $supportuser Sender user.
     * @param string $subject Email subject.
     * @param string $message Email text body.
     * @param string $messageid Synthetic id used for deduplication.
     * @param string $messagename Internal message name.
     * @param int $senttime Send timestamp.
     * @param int $originline Core line that sends this email in Moodle 5.2.
     * @param array $metadata Audit metadata.
     * @param int|null $moodlemessageid Related Moodle record id.
     * @param string|null $moodlemessagetable Related Moodle table.
     */
    private static function insert_forgot_password_audit_record(
        \stdClass $user,
        \stdClass $supportuser,
        string $subject,
        string $message,
        string $messageid,
        string $messagename,
        int $senttime,
        int $originline,
        array $metadata,
        ?int $moodlemessageid = null,
        ?string $moodlemessagetable = null
    ): void {
        global $DB, $CFG;

        if ($DB->record_exists(repository::TABLE, ['messageid' => $messageid])) {
            return;
        }
        if (!self::should_capture_kind('password_reset')) {
            return;
        }

        $stack = self::store_stacktrace() ? self::normalise_stack(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30)) : [];
        $metadata['wwwroot'] = $CFG->wwwroot ?? '';
        $metadata['statusassertion'] =
            'Core direct-email success is inferred from the completed forgot-password flow; failed sends emit email_failed.';

        $record = self::base_row([
            'timecreated' => $senttime,
            'status' => 'sent',
            'kind' => 'password_reset',
            'component' => 'core',
            'messagename' => self::short_string($messagename, 100),
            'originfile' => 'login/lib.php',
            'originline' => $originline,
            'messageid' => self::short_string($messageid, 255),
            'moodlemessageid' => $moodlemessageid,
            'moodlemessagetable' => $moodlemessagetable,
            'subject' => $subject,
            'bodytext' => self::store_bodytext() ? $message : '',
            'bodybytes' => strlen($message),
            'stackjson' => $stack ? json_encode($stack, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        self::apply_user($record, 'from', $supportuser);
        self::apply_user($record, 'to', $user);

        $DB->insert_record(repository::TABLE, (object) $record, false);
    }

    /**
     * Build a fresh audit row with safe defaults, overlaying the supplied values.
     *
     * @param array $overrides Field overrides.
     * @return array
     */
    private static function base_row(array $overrides = []): array {
        global $USER;

        $now = time();
        $defaults = [
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'sent',
            'kind' => 'other',
            'courseid' => null,
            'component' => '',
            'messagename' => '',
            'originfile' => '',
            'originline' => null,
            'messageid' => '',
            'moodlemessageid' => null,
            'moodlemessagetable' => null,
            'subject' => '',
            'bodytext' => '',
            'bodyhtml' => '',
            'fromuserid' => null,
            'fromemail' => '',
            'fromname' => '',
            'fromusername' => '',
            'fromfirstname' => '',
            'fromlastname' => '',
            'touserid' => null,
            'toemail' => '',
            'toname' => '',
            'tousername' => '',
            'tofirstname' => '',
            'tolastname' => '',
            'coursefullname' => '',
            'courseshortname' => '',
            'replyto' => '',
            'attachmentcount' => 0,
            'attachmentnames' => '',
            'bodybytes' => 0,
            'classificationnote' => '',
            'requestuserid' => isset($USER->id) ? (int)$USER->id : null,
            'requestip' => getremoteaddr() ?: null,
            'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'stackjson' => '',
            'metadata' => '{}',
            'deleted' => 0,
        ];

        $merged = array_merge($defaults, $overrides);
        $merged['timemodified'] = $now;
        return $merged;
    }

    /**
     * Overlay a user snapshot onto a row using a from/to prefix.
     *
     * @param array $row Row being built.
     * @param string $prefix from|to.
     * @param mixed $user User object, id, string, or null.
     */
    private static function apply_user(array &$row, string $prefix, $user): void {
        $snapshot = self::user_snapshot($user);
        $row[$prefix . 'userid'] = $snapshot['userid'];
        $row[$prefix . 'email'] = self::short_string($snapshot['email'], 255);
        $row[$prefix . 'name'] = self::short_string($snapshot['name'], 255);
        $row[$prefix . 'username'] = self::short_string($snapshot['username'], 100);
        $row[$prefix . 'firstname'] = self::short_string($snapshot['firstname'], 100);
        $row[$prefix . 'lastname'] = self::short_string($snapshot['lastname'], 100);
    }

    /**
     * Overlay a course snapshot onto a row.
     *
     * @param array $row Row being built.
     * @param int $courseid Course id.
     */
    private static function apply_course(array &$row, int $courseid): void {
        $snapshot = self::course_snapshot($courseid);
        $row['courseid'] = $snapshot['courseid'] ?: null;
        $row['coursefullname'] = self::short_string($snapshot['fullname'], 255);
        $row['courseshortname'] = self::short_string($snapshot['shortname'], 255);
    }

    /**
     * Return a user record without throwing from event observers.
     *
     * @param int $userid User id.
     * @return \stdClass|null
     */
    private static function safe_user_record(int $userid): ?\stdClass {
        if ($userid <= 0) {
            return null;
        }

        try {
            return \core_user::get_user($userid);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build a stable snapshot from a user-like value.
     *
     * @param mixed $user User object, user id, string, or null.
     * @return array
     */
    private static function user_snapshot($user): array {
        if (is_numeric($user)) {
            $user = self::safe_user_record((int)$user);
        }

        return [
            'userid' => self::object_id($user),
            'email' => is_object($user) ? self::string_value($user->email ?? '') : '',
            'name' => self::display_name($user),
            'username' => is_object($user) ? self::string_value($user->username ?? '') : '',
            'firstname' => is_object($user) ? self::string_value($user->firstname ?? '') : '',
            'lastname' => is_object($user) ? self::string_value($user->lastname ?? '') : '',
        ];
    }

    /**
     * Build a stable snapshot from a course id.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private static function course_snapshot(int $courseid): array {
        global $DB;

        $courseid = self::normalise_courseid($courseid);
        if (!$courseid) {
            return ['courseid' => 0, 'fullname' => '', 'shortname' => ''];
        }

        try {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', IGNORE_MISSING);
            if ($course) {
                return [
                    'courseid' => (int)$course->id,
                    'fullname' => self::string_value($course->fullname ?? ''),
                    'shortname' => self::string_value($course->shortname ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // Course lookup failed; fall through to the id-only snapshot below.
            unset($e);
        }

        return ['courseid' => $courseid, 'fullname' => '', 'shortname' => ''];
    }

    /**
     * Check whether a classified mail kind should be captured.
     *
     * @param string $kind Kind key.
     * @return bool
     */
    private static function should_capture_kind(string $kind): bool {
        $configured = get_config('tool_mailaudit', 'capturekinds');
        if ($configured === false || $configured === '') {
            return true;
        }

        $allowed = array_filter(explode(',', (string)$configured));
        return in_array($kind, $allowed, true);
    }

    /**
     * Whether new captures should store plain text bodies.
     *
     * @return bool
     */
    private static function store_bodytext(): bool {
        return (int)get_config('tool_mailaudit', 'storebodytext') === 1;
    }

    /**
     * Whether new captures should store HTML bodies.
     *
     * @return bool
     */
    private static function store_bodyhtml(): bool {
        return (int)get_config('tool_mailaudit', 'storebodyhtml') === 1;
    }

    /**
     * Whether new captures should store stack traces.
     *
     * @return bool
     */
    private static function store_stacktrace(): bool {
        return (int)get_config('tool_mailaudit', 'storestacktrace') === 1;
    }

    /**
     * Decode metadata JSON.
     *
     * @param string $json Metadata JSON.
     * @return array
     */
    private static function decode_metadata(string $json): array {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check whether capture is enabled and installed.
     *
     * @return bool
     */
    private static function capture_enabled(): bool {
        $enabled = get_config('tool_mailaudit', 'enabled');
        if ($enabled !== false && (int)$enabled === 0) {
            return false;
        }

        return self::table_exists();
    }

    /**
     * Check the audit table exists.
     *
     * @return bool
     */
    private static function table_exists(): bool {
        global $DB;

        try {
            return $DB->get_manager()->table_exists(new \xmldb_table(repository::TABLE));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Record a capture failure for the operator without breaking mail delivery.
     *
     * @param string $stage Capture stage that failed.
     * @param \Throwable $e Captured throwable.
     */
    private static function report_failure(string $stage, \Throwable $e): void {
        debugging('tool_mailaudit capture failed at ' . $stage . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        try {
            set_config('lastcaptureerror', \core_text::substr($stage . ': ' . $e->getMessage(), 0, 255), 'tool_mailaudit');
            set_config('lastcaptureerrortime', time(), 'tool_mailaudit');
        } catch (\Throwable $ignored) {
            // Never let health bookkeeping break the mail path.
            unset($ignored);
        }
    }

    /**
     * Infer a high-level mail kind.
     *
     * @param string $component Moodle component.
     * @param string $messagename Message provider name.
     * @param string $subject Subject.
     * @param array $stack Normalised stack.
     * @param array $metadata Pending message metadata.
     * @return string
     */
    private static function infer_kind(
        string $component,
        string $messagename,
        string $subject,
        array $stack,
        array $metadata
    ): string {
        $component = \core_text::strtolower($component);
        $messagename = \core_text::strtolower($messagename);
        $subjectlc = \core_text::strtolower($subject);
        $stacktext = \core_text::strtolower(json_encode($stack, JSON_UNESCAPED_SLASHES));

        if (in_array($component, ['core', 'moodle'], true) && $messagename === 'newlogin') {
            return 'new_ip_login';
        }
        if (strpos($subjectlc, 'new sign in') !== false) {
            return 'new_ip_login';
        }
        if (
            strpos($subjectlc, 'locked out') !== false || strpos($subjectlc, 'account locked') !== false
                || strpos($subjectlc, 'security alert') !== false
        ) {
            return 'account_security';
        }
        if (
            $messagename === 'insights' || strpos($component, 'analytics') !== false
                || strpos($component, 'insights') !== false || strpos($subjectlc, 'risk') !== false
        ) {
            return 'student_risk_alert';
        }
        if (
            $messagename === 'enrolcoursewelcomemessage' || strpos($component, 'enrol_') === 0
                || strpos($subjectlc, 'course registration') !== false || strpos($subjectlc, 'enrol') !== false
        ) {
            return 'course_registration';
        }
        if (
            $component === 'block_quickmail' || ($component === 'mod_forum' && in_array($messagename, ['posts', 'digests'], true))
                || strpos($subjectlc, 'quickmail') !== false || strpos($stacktext, '/blocks/quickmail/') !== false
        ) {
            return 'course_bulk_mail';
        }
        if ($component === 'message_email' || strpos($stacktext, 'message_email\\\\task\\\\send_email_task') !== false) {
            return 'message_digest';
        }
        if (
            strpos($stacktext, 'send_failed_login_notifications_task') !== false
                || strpos($subjectlc, 'failed login') !== false
        ) {
            return 'failed_login_digest';
        }
        if (strpos($stacktext, '/local/mailtest/') !== false) {
            return 'mailtest';
        }
        if (
            strpos($stacktext, 'forgot_password') !== false || strpos($stacktext, 'setnew_password') !== false
                || (strpos($stacktext, '/login/') !== false && strpos($subjectlc, 'password') !== false)
                || strpos($subjectlc, 'password reset') !== false || strpos($subjectlc, 'reset password') !== false
        ) {
            return 'password_reset';
        }

        return 'other';
    }

    /**
     * Normalise a PHP stack trace for storage.
     *
     * @param array $trace Raw trace.
     * @return array
     */
    private static function normalise_stack(array $trace): array {
        global $CFG;

        $stack = [];
        foreach ($trace as $frame) {
            $file = self::relative_path($frame['file'] ?? '', $CFG->dirroot ?? '');
            $stack[] = [
                'file' => $file,
                'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                'function' => self::short_string($frame['function'] ?? '', 120),
                'class' => self::short_string($frame['class'] ?? '', 180),
            ];
        }
        return $stack;
    }

    /**
     * Find the first useful origin frame.
     *
     * @param array $stack Normalised stack.
     * @return array
     */
    private static function find_origin(array $stack): array {
        foreach ($stack as $frame) {
            $file = $frame['file'] ?? '';
            if (
                $file === ''
                    || strpos($file, 'admin/tool/mailaudit/') !== false
                    || $file === 'lib/moodlelib.php'
                    || strpos($file, 'message/output/email/message_output_email.php') !== false
            ) {
                continue;
            }
            return $frame;
        }
        return $stack[0] ?? [];
    }

    /**
     * Derive Moodle component from a relative path.
     *
     * @param string $path Relative path.
     * @return string
     */
    private static function component_from_path(string $path): string {
        $parts = explode('/', trim($path, '/'));
        if (count($parts) >= 2 && in_array($parts[0], ['mod', 'blocks', 'local', 'auth', 'enrol', 'report', 'tool'], true)) {
            return $parts[0] . '_' . $parts[1];
        }
        if (count($parts) >= 3 && $parts[0] === 'admin' && $parts[1] === 'tool') {
            return 'tool_' . $parts[2];
        }
        return 'core';
    }

    /**
     * Make a path relative to Moodle dirroot.
     *
     * @param string $path Path.
     * @param string $dirroot Dirroot.
     * @return string
     */
    private static function relative_path(string $path, string $dirroot): string {
        if ($path !== '' && $dirroot !== '' && strpos($path, $dirroot . '/') === 0) {
            return substr($path, strlen($dirroot) + 1);
        }
        return $path;
    }

    /**
     * Return object id when present.
     *
     * @param mixed $value Object or scalar.
     * @return int|null
     */
    private static function object_id($value): ?int {
        if (is_object($value) && isset($value->id)) {
            return (int)$value->id;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return null;
    }

    /**
     * Return display name for a user-like value.
     *
     * @param mixed $value User object or string.
     * @return string
     */
    private static function display_name($value): string {
        if (is_string($value)) {
            return $value;
        }
        if (is_object($value)) {
            if (isset($value->firstname) || isset($value->lastname)) {
                return fullname($value);
            }
            if (isset($value->email)) {
                return $value->email;
            }
        }
        return '';
    }

    /**
     * Convert mixed value to string.
     *
     * @param mixed $value Value.
     * @return string
     */
    private static function string_value($value): string {
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert mixed value to a bounded string.
     *
     * @param mixed $value Value.
     * @param int $length Max length.
     * @return string
     */
    private static function short_string($value, int $length = 255): string {
        return \core_text::substr(self::string_value($value), 0, $length);
    }
}
