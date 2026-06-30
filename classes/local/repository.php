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
 * Database access for the sent-mail audit.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {
    /** @var string */
    public const TABLE = 'tool_mailaudit_mail';

    /**
     * Known status values.
     *
     * @return array
     */
    public static function status_options(): array {
        return [
            '' => get_string('allstatuses', 'tool_mailaudit'),
            'queued' => get_string('status_queued', 'tool_mailaudit'),
            'sent' => get_string('status_sent', 'tool_mailaudit'),
            'failed' => get_string('status_failed', 'tool_mailaudit'),
        ];
    }

    /**
     * Known kind values.
     *
     * @return array
     */
    public static function kind_options(): array {
        $kinds = ['' => get_string('allkinds', 'tool_mailaudit')];
        foreach (self::kind_keys() as $kind) {
            $kinds[$kind] = self::kind_label($kind);
        }
        return $kinds;
    }

    /**
     * Return supported kind keys.
     *
     * @return string[]
     */
    public static function kind_keys(): array {
        return [
            'course_registration',
            'password_reset',
            'student_risk_alert',
            'failed_login_digest',
            'new_ip_login',
            'account_security',
            'course_bulk_mail',
            'message_digest',
            'mailtest',
            'other',
        ];
    }

    /**
     * Human label for kind.
     *
     * @param string $kind Kind key.
     * @return string
     */
    public static function kind_label(string $kind): string {
        $key = 'kind_' . $kind;
        return get_string_manager()->string_exists($key, 'tool_mailaudit') ? get_string($key, 'tool_mailaudit') : $kind;
    }

    /**
     * Search mail audit records.
     *
     * @param filters $filters Filters.
     * @param int $page Zero-based page.
     * @param array|null $visibility Optional [$sql, $params] access predicate.
     * @return array [records, totalcount]
     */
    public static function search(filters $filters, int $page, ?array $visibility = null): array {
        global $DB;

        [$where, $params] = self::where_sql($filters, false, $visibility);
        $total = $DB->count_records_select(self::TABLE, $where, $params);
        $order = self::order_sql($filters);
        $records = $DB->get_records_select(
            self::TABLE,
            $where,
            $params,
            $order,
            '*',
            max(0, $page) * $filters->perpage,
            $filters->perpage,
        );

        return [$records, $total];
    }

    /**
     * Return SQL parts suitable for table_sql.
     *
     * @param filters $filters Filters.
     * @param array|null $visibility Optional visibility predicate.
     * @return array [fields, from, where, params]
     */
    public static function table_sql(filters $filters, ?array $visibility = null): array {
        [$where, $params] = self::where_sql($filters, false, $visibility);
        return ['*', '{' . self::TABLE . '}', $where, $params];
    }

    /**
     * Sortable columns exposed to the UI.
     *
     * @return string[]
     */
    public static function sortable_columns(): array {
        return [
            'timecreated',
            'coursefullname',
            'kind',
            'status',
            'fromfirstname',
            'fromlastname',
            'fromemail',
            'tofirstname',
            'tolastname',
            'toemail',
            'subject',
            'bodybytes',
        ];
    }

    /**
     * Fetch one record.
     *
     * @param int $id Audit id.
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Count matching active records.
     *
     * @param filters $filters Filters.
     * @return int
     */
    public static function count_active(filters $filters): int {
        global $DB;

        [$where, $params] = self::where_sql($filters, true);
        return (int)$DB->count_records_select(self::TABLE, $where, $params);
    }

    /**
     * Count matching records using the filter's deleted-record setting.
     *
     * @param filters $filters Filters.
     * @return int
     */
    public static function count_matching(filters $filters): int {
        global $DB;

        [$where, $params] = self::where_sql($filters);
        return (int)$DB->count_records_select(self::TABLE, $where, $params);
    }

    /**
     * Count matching deleted records.
     *
     * @param filters $filters Filters.
     * @return int
     */
    public static function count_deleted(filters $filters): int {
        global $DB;

        [$where, $params] = self::where_sql($filters);
        $where = '(' . $where . ') AND deleted = 1';
        return (int)$DB->count_records_select(self::TABLE, $where, $params);
    }

    /**
     * Soft-delete selected active ids.
     *
     * @param int[] $ids Selected audit ids.
     * @param int $userid Current user id.
     * @return int Number of records affected.
     */
    public static function soft_delete_ids(array $ids, int $userid): int {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mailid');
        $where = "id $insql AND deleted = 0";
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }

        $params['deleted'] = 1;
        $params['timedeleted'] = time();
        $params['deletedby'] = $userid;
        $params['deletebatch'] = random_string(20);
        $DB->execute("UPDATE {" . self::TABLE . "}
                         SET deleted = :deleted,
                             timemodified = :timedeleted,
                             timedeleted = :timedeleted,
                             deletedby = :deletedby,
                             deletebatch = :deletebatch
                       WHERE $where", $params);

        return $count;
    }

    /**
     * Permanently purge selected already-deleted ids.
     *
     * @param int[] $ids Selected audit ids.
     * @return int Number of records removed.
     */
    public static function purge_ids(array $ids): int {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'purgeid');
        $where = "id $insql AND deleted = 1";
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }
        $DB->delete_records_select(self::TABLE, $where, $params);

        return $count;
    }

    /**
     * Soft-delete matching active records.
     *
     * @param filters $filters Filters.
     * @param int $userid Deleting administrator id.
     * @return int Number of records affected.
     */
    public static function soft_delete(filters $filters, int $userid): int {
        global $DB;

        [$where, $params] = self::where_sql($filters, true);
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }

        $params['deleted'] = 1;
        $params['timedeleted'] = time();
        $params['deletedby'] = $userid;
        $params['deletebatch'] = random_string(20);

        $sql = "UPDATE {" . self::TABLE . "}
                   SET deleted = :deleted,
                       timemodified = :timedeleted,
                       timedeleted = :timedeleted,
                       deletedby = :deletedby,
                       deletebatch = :deletebatch
                 WHERE $where";
        $DB->execute($sql, $params);

        return $count;
    }

    /**
     * Permanently delete matching records using the filter's deleted-record setting.
     *
     * @param filters $filters Filters.
     * @return int Number of records removed.
     */
    public static function hard_delete(filters $filters): int {
        global $DB;

        [$where, $params] = self::where_sql($filters);
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }
        $DB->delete_records_select(self::TABLE, $where, $params);

        return $count;
    }

    /**
     * Permanently purge all mail audit records older than a cutoff.
     *
     * @param int $cutoff Unix timestamp cutoff.
     * @param bool $active When true, purge active records by timecreated; otherwise purge deleted records by timedeleted.
     * @return int Number of records removed.
     */
    public static function purge_before(int $cutoff, bool $active = false): int {
        global $DB;

        $where = $active ? 'timecreated < :cutoff' : 'deleted = 1 AND timedeleted < :cutoff';
        $params = ['cutoff' => $cutoff];
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }
        $DB->delete_records_select(self::TABLE, $where, $params);

        return $count;
    }

    /**
     * Permanently purge deleted records matching filters.
     *
     * @param filters $filters Filters.
     * @return int Number of records removed.
     */
    public static function purge_matching_deleted(filters $filters): int {
        global $DB;

        [$where, $params] = self::where_sql($filters);
        $where = '(' . $where . ') AND deleted = 1';
        $count = (int)$DB->count_records_select(self::TABLE, $where, $params);
        if (!$count) {
            return 0;
        }
        $DB->delete_records_select(self::TABLE, $where, $params);

        return $count;
    }

    /**
     * Build SQL where clause from filters.
     *
     * @param filters $filters Filters.
     * @param bool $forceactive Force deleted = 0.
     * @param array|null $visibility Optional [$sql, $params] access predicate.
     * @return array
     */
    public static function where_sql(filters $filters, bool $forceactive = false, ?array $visibility = null): array {
        global $DB;

        $conditions = [];
        $params = [];

        if (!$filters->includedeleted || $forceactive) {
            $conditions[] = 'deleted = 0';
        }
        if ($filters->datefrom) {
            $conditions[] = 'timecreated >= :datefrom';
            $params['datefrom'] = $filters->datefrom;
        }
        if ($filters->dateto) {
            $conditions[] = 'timecreated <= :dateto';
            $params['dateto'] = $filters->dateto;
        }
        if ($filters->kind !== '') {
            $conditions[] = 'kind = :kind';
            $params['kind'] = $filters->kind;
        }
        if ($filters->kinds) {
            self::add_in_condition($conditions, $params, 'kind', $filters->kinds, false, 'kindlist');
        }
        if ($filters->status !== '') {
            $conditions[] = 'status = :status';
            $params['status'] = $filters->status;
        }
        if ($filters->statuses) {
            self::add_in_condition($conditions, $params, 'status', $filters->statuses, false, 'statuslist');
        }
        if ($filters->courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $filters->courseid;
        }
        if ($filters->courseids) {
            self::add_in_condition($conditions, $params, 'courseid', $filters->courseids, $filters->courseidsnot, 'courseids');
        }
        if ($filters->fromuserids) {
            self::add_in_condition(
                $conditions,
                $params,
                'fromuserid',
                $filters->fromuserids,
                $filters->fromuseridsnot,
                'fromuserids'
            );
        }
        if ($filters->touserids) {
            self::add_in_condition(
                $conditions,
                $params,
                'touserid',
                $filters->touserids,
                $filters->touseridsnot,
                'touserids'
            );
        }

        self::add_like(
            $conditions,
            $params,
            $DB->sql_concat(
                "COALESCE(fromname, '')",
                "' '",
                "COALESCE(fromemail, '')",
                "' '",
                "COALESCE(fromusername, '')",
                "' '",
                "COALESCE(fromfirstname, '')",
                "' '",
                "COALESCE(fromlastname, '')"
            ),
            $filters->sender,
            $filters->sendernot,
            'sender',
        );
        self::add_like(
            $conditions,
            $params,
            $DB->sql_concat(
                "COALESCE(toname, '')",
                "' '",
                "COALESCE(toemail, '')",
                "' '",
                "COALESCE(tousername, '')",
                "' '",
                "COALESCE(tofirstname, '')",
                "' '",
                "COALESCE(tolastname, '')"
            ),
            $filters->recipient,
            $filters->recipientnot,
            'recipient',
        );
        self::add_like($conditions, $params, "COALESCE(subject, '')", $filters->subject, $filters->subjectnot, 'subject');
        self::add_like(
            $conditions,
            $params,
            $DB->sql_concat("COALESCE(bodytext, '')", "' '", "COALESCE(bodyhtml, '')"),
            $filters->body,
            $filters->bodynot,
            'body',
        );

        if ($visibility !== null) {
            [$visibilitysql, $visibilityparams] = $visibility;
            $conditions[] = '(' . $visibilitysql . ')';
            $params += $visibilityparams;
        }

        return [$conditions ? implode(' AND ', $conditions) : '1 = 1', $params];
    }

    /**
     * Return overview statistics for the current filter scope.
     *
     * @param filters $filters Filters.
     * @param array|null $visibility Optional visibility predicate.
     * @return array
     */
    public static function stats_overview(filters $filters, ?array $visibility = null): array {
        global $DB;

        [$where, $params] = self::where_sql($filters, false, $visibility);
        $now = time();
        $daystart = usergetmidnight($now);
        $monthstart = make_timestamp((int)date('Y', $now), (int)date('n', $now), 1);

        $stats = [
            'total' => (int)$DB->count_records_select(self::TABLE, $where, $params),
            'today' => (int)$DB->count_records_select(
                self::TABLE,
                '(' . $where . ') AND timecreated >= :today',
                $params + ['today' => $daystart]
            ),
            'month' => (int)$DB->count_records_select(
                self::TABLE,
                '(' . $where . ') AND timecreated >= :monthstart',
                $params + ['monthstart' => $monthstart]
            ),
            'peak_hour' => null,
            'top_sender' => null,
            'top_recipient' => null,
            'top_course' => null,
            'table_size' => self::table_size_stats(),
        ];

        $stats['peak_hour'] = self::peak_hour($where, $params);

        $stats['top_sender'] = self::top_group($where, $params, 'fromuserid', 'fromname', 'fromemail');
        $stats['top_recipient'] = self::top_group($where, $params, 'touserid', 'toname', 'toemail');
        $stats['top_course'] = self::top_group($where, $params, 'courseid', 'coursefullname', 'courseshortname');

        return $stats;
    }

    /**
     * Return the most frequent send hour for the current scope using a single grouped query.
     *
     * The hour bucket is computed in SQL from the stored epoch second, offset to the viewing
     * user's timezone. DST transitions within the range are not modelled; this is an overview stat.
     *
     * @param string $where Where SQL.
     * @param array $params SQL params.
     * @return \stdClass|null
     */
    private static function peak_hour(string $where, array $params): ?\stdClass {
        global $DB;

        $tz = new \DateTimeZone(\core_date::get_user_timezone());
        // Integer offset is inlined (not bound): the bucket expression is repeated in SELECT and
        // GROUP BY, and some drivers (e.g. pgsql) reject reusing a single named placeholder.
        $offset = (int)$tz->getOffset(new \DateTime('@' . time()));
        $bucket = "FLOOR(((timecreated + $offset) % 86400) / 3600)";
        $records = $DB->get_records_sql(
            "SELECT $bucket AS hourbucket, COUNT(1) AS mailcount
                                           FROM {" . self::TABLE . "}
                                          WHERE $where
                                       GROUP BY $bucket
                                       ORDER BY mailcount DESC, hourbucket ASC",
            $params,
            0,
            1
        );
        if (!$records) {
            return null;
        }

        $row = reset($records);
        return (object)['hour' => (int)$row->hourbucket, 'count' => (int)$row->mailcount];
    }

    /**
     * Return distinct audited course options, scoped to the courses a user may browse.
     *
     * @param int[]|null $allowedcourseids Null for all courses, or the course ids the viewer may see.
     * @return array
     */
    public static function course_options(?array $allowedcourseids = null): array {
        global $DB;

        if ($allowedcourseids !== null && !$allowedcourseids) {
            return [];
        }

        $where = 'courseid IS NOT NULL AND courseid > 0';
        $params = [];
        if ($allowedcourseids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($allowedcourseids, SQL_PARAMS_NAMED, 'allowedcourse');
            $where .= " AND courseid $insql";
            $params += $inparams;
        }

        $options = [];
        $records = $DB->get_records_sql("SELECT DISTINCT courseid, coursefullname, courseshortname
                                           FROM {" . self::TABLE . "}
                                          WHERE $where
                                       ORDER BY courseshortname, coursefullname", $params, 0, 500);
        foreach ($records as $record) {
            $label = trim(($record->courseshortname ?? '') . ' ' . ($record->coursefullname ?? ''));
            $options[(int)$record->courseid] = $label !== '' ? $label : get_string('course') . ' ' . (int)$record->courseid;
        }

        return $options;
    }

    /**
     * Return table size information where supported.
     *
     * @return array
     */
    public static function table_size_stats(): array {
        global $CFG, $DB;

        $stats = [
            'bytes' => null,
            'label' => get_string('unknown', 'tool_mailaudit'),
        ];

        if ($DB->get_dbfamily() === 'postgres') {
            $tablename = $CFG->prefix . self::TABLE;
            try {
                $bytes = (int)$DB->get_field_sql('SELECT pg_total_relation_size(:tablename)', ['tablename' => $tablename]);
                $stats['bytes'] = $bytes;
                $stats['label'] = display_size($bytes);
            } catch (\Throwable $e) {
                // Size query failed on this database; keep the row-sum fallback below.
                unset($e);
            }
        }

        if ($stats['bytes'] === null) {
            $bytes = (int)$DB->get_field_sql("SELECT COALESCE(SUM(bodybytes), 0) FROM {" . self::TABLE . "}");
            $stats['bytes'] = $bytes;
            $stats['label'] = display_size($bytes);
        }

        return $stats;
    }

    /**
     * Return top grouped stat row.
     *
     * @param string $where Where SQL.
     * @param array $params SQL params.
     * @param string $idfield Group id field.
     * @param string $namefield Name field.
     * @param string $fallbackfield Fallback field.
     * @return \stdClass|null
     */
    private static function top_group(
        string $where,
        array $params,
        string $idfield,
        string $namefield,
        string $fallbackfield
    ): ?\stdClass {
        global $DB;

        $records = $DB->get_records_sql("SELECT $idfield AS groupid,
                                                MAX(COALESCE($namefield, $fallbackfield, '')) AS label,
                                                COUNT(1) AS mailcount
                                           FROM {" . self::TABLE . "}
                                          WHERE $where AND $idfield IS NOT NULL
                                       GROUP BY $idfield
                                       ORDER BY mailcount DESC", $params, 0, 1);
        if (!$records) {
            return null;
        }

        return reset($records);
    }

    /**
     * Return a safe ORDER BY clause.
     *
     * @param filters $filters Filters.
     * @return string
     */
    private static function order_sql(filters $filters): string {
        $sort = in_array($filters->sort, self::sortable_columns(), true) ? $filters->sort : 'timecreated';
        $dir = strtoupper($filters->dir) === 'ASC' ? 'ASC' : 'DESC';
        return $sort . ' ' . $dir . ', id DESC';
    }

    /**
     * Add a SQL LIKE or NOT LIKE filter.
     *
     * @param array $conditions SQL conditions.
     * @param array $params SQL params.
     * @param string $field Field expression.
     * @param string $value Search value.
     * @param bool $negate Negate the match.
     * @param string $prefix Param prefix.
     */
    private static function add_like(
        array &$conditions,
        array &$params,
        string $field,
        string $value,
        bool $negate,
        string $prefix
    ): void {
        global $DB;

        if ($value === '') {
            return;
        }

        $param = $prefix . count($params);
        $params[$param] = '%' . $DB->sql_like_escape($value) . '%';
        $like = $DB->sql_like($field, ':' . $param, false, false);
        $conditions[] = $negate ? "NOT ($like)" : $like;
    }

    /**
     * Add an IN or NOT IN condition.
     *
     * @param array $conditions SQL conditions.
     * @param array $params SQL params.
     * @param string $field Field name.
     * @param array $values Values.
     * @param bool $negate Whether to negate.
     * @param string $prefix Parameter prefix.
     */
    private static function add_in_condition(
        array &$conditions,
        array &$params,
        string $field,
        array $values,
        bool $negate,
        string $prefix
    ): void {
        global $DB;

        if (!$values) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED, $prefix, !$negate);
        $conditions[] = $negate ? "($field IS NULL OR $field $insql)" : "$field $insql";
        $params += $inparams;
    }
}
