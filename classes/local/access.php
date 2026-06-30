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
 * Access control and audit logging for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /** Capability to view all audited mail. */
    public const CAP_VIEWALL = 'tool/mailaudit:view';

    /** Capability to view own sent mail. */
    public const CAP_VIEWOWN = 'tool/mailaudit:viewown';

    /** Capability to view course-level sent mail. */
    public const CAP_VIEWCOURSE = 'tool/mailaudit:viewcourse';

    /** Capability to permanently delete audited mail. */
    public const CAP_DELETE = 'tool/mailaudit:delete';

    /** Security-sensitive mail kinds that must remain system-admin only. */
    public const ADMIN_ONLY_KINDS = [
        'password_reset',
        'new_ip_login',
        'failed_login_digest',
        'account_security',
    ];

    /**
     * Require read access to the browser.
     *
     * @param int $courseid Optional course filter.
     */
    public static function require_view(int $courseid = 0): void {
        require_login();

        if (self::can_view_all()) {
            return;
        }

        if ($courseid > 0) {
            $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
            if (
                $coursecontext && (has_capability(self::CAP_VIEWCOURSE, $coursecontext)
                    || has_capability(self::CAP_VIEWOWN, $coursecontext))
            ) {
                return;
            }
        } else if (self::has_any_limited_view()) {
            return;
        }

        $context = \context_system::instance();
        self::log('access_denied', null, ['capability' => self::CAP_VIEWALL, 'courseid' => $courseid]);
        throw new \required_capability_exception($context, self::CAP_VIEWALL, 'nopermissions', '');
    }

    /**
     * Require read access to a specific mail record.
     *
     * @param \stdClass $mail Mail audit record.
     */
    public static function require_view_record(\stdClass $mail): void {
        global $USER;

        require_login();

        if (self::can_view_all()) {
            return;
        }

        if (!self::kind_visible_to_current_user((string)($mail->kind ?? ''))) {
            $context = \context_system::instance();
            self::log('record_access_denied_sensitive', (int)$mail->id, ['kind' => (string)($mail->kind ?? '')]);
            throw new \required_capability_exception($context, self::CAP_VIEWALL, 'nopermissions', '');
        }

        $userid = (int)($USER->id ?? 0);
        if (!empty($mail->fromuserid) && (int)$mail->fromuserid === $userid && self::has_any_own_view()) {
            return;
        }

        if (!empty($mail->courseid)) {
            $coursecontext = \context_course::instance((int)$mail->courseid, IGNORE_MISSING);
            if ($coursecontext && has_capability(self::CAP_VIEWCOURSE, $coursecontext)) {
                return;
            }
        }

        $context = \context_system::instance();
        self::log('record_access_denied', (int)$mail->id, ['courseid' => (int)($mail->courseid ?? 0)]);
        throw new \required_capability_exception($context, self::CAP_VIEWALL, 'nopermissions', '');
    }

    /**
     * Require permanent delete access.
     */
    public static function require_delete(): void {
        require_login();
        $context = \context_system::instance();
        if (!has_capability(self::CAP_DELETE, $context)) {
            self::log('delete_denied', null, ['capability' => self::CAP_DELETE]);
            throw new \required_capability_exception($context, self::CAP_DELETE, 'nopermissions', '');
        }
    }

    /**
     * Return the SQL predicate restricting visible mail for the current user.
     *
     * @param string $alias Optional table alias, without trailing dot.
     * @return array [$sql, $params]
     */
    public static function visibility_sql(string $alias = ''): array {
        global $DB, $USER;

        $prefix = $alias !== '' ? $alias . '.' : '';
        if (self::can_view_all()) {
            return ['1 = 1', []];
        }

        $conditions = [];
        $params = [];
        $userid = (int)($USER->id ?? 0);

        if (self::has_any_own_view()) {
            $conditions[] = $prefix . 'fromuserid = :mailauditvisibleuserid';
            $params['mailauditvisibleuserid'] = $userid;
        }

        $courseids = self::courseids_for_capability(self::CAP_VIEWCOURSE);
        if ($courseids) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'mailauditcourse');
            $conditions[] = $prefix . "courseid $insql";
            $params += $inparams;
        }

        if (!$conditions) {
            return ['0 = 1', []];
        }

        $visiblekinds = self::non_admin_visible_kinds();
        if (!$visiblekinds) {
            return ['0 = 1', []];
        }
        [$kindinsql, $kindparams] = $DB->get_in_or_equal($visiblekinds, SQL_PARAMS_NAMED, 'mailauditkind');
        $params += $kindparams;

        return ['(' . implode(' OR ', $conditions) . ') AND ' . $prefix . "kind $kindinsql", $params];
    }

    /**
     * Return configured admin-only kind keys.
     *
     * @return string[]
     */
    public static function admin_only_kinds(): array {
        $configured = get_config('tool_mailaudit', 'sensitivekinds');
        if ($configured !== false && $configured !== '') {
            return array_values(array_filter(explode(',', (string)$configured)));
        }
        return self::ADMIN_ONLY_KINDS;
    }

    /**
     * Return kind keys visible to limited non-admin viewers.
     *
     * @return string[]
     */
    public static function non_admin_visible_kinds(): array {
        $configured = get_config('tool_mailaudit', 'nonadminvisiblekinds');
        $kinds = $configured !== false && $configured !== ''
            ? array_values(array_filter(explode(',', (string)$configured)))
            : ['course_registration', 'course_bulk_mail', 'message_digest', 'mailtest', 'other'];

        return array_values(array_diff($kinds, self::admin_only_kinds()));
    }

    /**
     * Return true if the current user can view a mail kind.
     *
     * @param string $kind Kind key.
     * @return bool
     */
    public static function kind_visible_to_current_user(string $kind): bool {
        if (self::can_view_all()) {
            return true;
        }
        return in_array($kind, self::non_admin_visible_kinds(), true);
    }

    /**
     * Return the course ids whose mail the current user may browse, for filter UI scoping.
     *
     * @return int[]|null Null when the user may see all courses, otherwise the allowed course ids.
     */
    public static function viewable_course_ids(): ?array {
        if (self::can_view_all()) {
            return null;
        }
        return array_values(array_unique(self::courseids_for_capability(self::CAP_VIEWCOURSE)));
    }

    /**
     * Return true when current user can view all records.
     *
     * @return bool
     */
    public static function can_view_all(): bool {
        return has_capability(self::CAP_VIEWALL, \context_system::instance());
    }

    /**
     * Return true when current user can permanently delete audit records.
     *
     * @return bool
     */
    public static function can_delete(): bool {
        return has_capability(self::CAP_DELETE, \context_system::instance());
    }

    /**
     * Return true when current user has any limited read capability.
     *
     * @return bool
     */
    private static function has_any_limited_view(): bool {
        return self::has_any_own_view() || (bool)self::courseids_for_capability(self::CAP_VIEWCOURSE, 1);
    }

    /**
     * Return true when current user has own-mail view capability in at least one course.
     *
     * @return bool
     */
    private static function has_any_own_view(): bool {
        return (bool)self::courseids_for_capability(self::CAP_VIEWOWN, 1);
    }

    /**
     * Get course ids where the current user has a capability.
     *
     * @param string $capability Capability name.
     * @param int $limit Optional limit.
     * @return int[]
     */
    private static function courseids_for_capability(string $capability, int $limit = 0): array {
        // Read the course id from each returned record's id property, not from the array key:
        // Core's get_user_capability_course() returns a sequentially-indexed list, not a keyed map.
        $courses = get_user_capability_course($capability, null, true, '', '', $limit);
        if (!$courses) {
            return [];
        }
        return array_map(static function ($course): int {
            return (int)$course->id;
        }, array_values($courses));
    }

    /**
     * Record access to this tool and trigger an event for Moodle's own logs.
     *
     * @param string $action Action name.
     * @param int|null $mailid Optional mail audit record id.
     * @param array $criteria Filter or action criteria.
     * @return int Inserted access audit id.
     */
    public static function log(string $action, ?int $mailid = null, array $criteria = []): int {
        global $DB, $USER;

        $record = (object) [
            'timecreated' => time(),
            'userid' => (int)($USER->id ?? 0),
            'action' => substr($action, 0, 40),
            'mailid' => $mailid,
            'criteria' => json_encode($criteria, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'requestip' => getremoteaddr() ?: null,
            'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        $id = (int)$DB->insert_record('tool_mailaudit_access', $record);

        $eventdata = [
            'context' => \context_system::instance(),
            'objectid' => $id,
            'other' => [
                'action' => $action,
                'mailid' => (int)($mailid ?? 0),
                'deletedcount' => $criteria['deletedcount'] ?? 0,
            ],
        ];

        if ($action === 'list_viewed') {
            \tool_mailaudit\event\mail_list_viewed::create($eventdata)->trigger();
        } else if ($action === 'mail_viewed' && $mailid) {
            \tool_mailaudit\event\mail_viewed::create([
                'context' => \context_system::instance(),
                'objectid' => $mailid,
                'other' => ['accessid' => $id],
            ])->trigger();
        } else if (in_array($action, ['mail_deleted', 'mail_purged'], true)) {
            \tool_mailaudit\event\mail_deleted::create($eventdata)->trigger();
        } else {
            \tool_mailaudit\event\access_logged::create($eventdata)->trigger();
        }

        return $id;
    }
}
