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

namespace tool_mailaudit\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use xmldb_table;

/**
 * Privacy metadata provider for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_mailaudit_mail', [
            'timecreated' => 'privacy:metadata:tool_mailaudit_mail:timecreated',
            'courseid' => 'privacy:metadata:tool_mailaudit_mail:courseid',
            'moodlemessageid' => 'privacy:metadata:tool_mailaudit_mail:moodlemessageid',
            'moodlemessagetable' => 'privacy:metadata:tool_mailaudit_mail:moodlemessagetable',
            'fromuserid' => 'privacy:metadata:tool_mailaudit_mail:fromuserid',
            'fromemail' => 'privacy:metadata:tool_mailaudit_mail:fromemail',
            'fromusername' => 'privacy:metadata:tool_mailaudit_mail:fromusername',
            'fromfirstname' => 'privacy:metadata:tool_mailaudit_mail:fromfirstname',
            'fromlastname' => 'privacy:metadata:tool_mailaudit_mail:fromlastname',
            'touserid' => 'privacy:metadata:tool_mailaudit_mail:touserid',
            'toemail' => 'privacy:metadata:tool_mailaudit_mail:toemail',
            'tousername' => 'privacy:metadata:tool_mailaudit_mail:tousername',
            'tofirstname' => 'privacy:metadata:tool_mailaudit_mail:tofirstname',
            'tolastname' => 'privacy:metadata:tool_mailaudit_mail:tolastname',
            'fromname' => 'privacy:metadata:tool_mailaudit_mail:fromname',
            'toname' => 'privacy:metadata:tool_mailaudit_mail:toname',
            'subject' => 'privacy:metadata:tool_mailaudit_mail:subject',
            'bodytext' => 'privacy:metadata:tool_mailaudit_mail:bodytext',
            'bodyhtml' => 'privacy:metadata:tool_mailaudit_mail:bodyhtml',
            'bodybytes' => 'privacy:metadata:tool_mailaudit_mail:bodybytes',
            'replyto' => 'privacy:metadata:tool_mailaudit_mail:replyto',
            'attachmentnames' => 'privacy:metadata:tool_mailaudit_mail:attachmentnames',
            'requestuserid' => 'privacy:metadata:tool_mailaudit_mail:requestuserid',
            'requestip' => 'privacy:metadata:tool_mailaudit_mail:requestip',
            'useragent' => 'privacy:metadata:tool_mailaudit_mail:useragent',
            'stackjson' => 'privacy:metadata:tool_mailaudit_mail:stackjson',
            'metadata' => 'privacy:metadata:tool_mailaudit_mail:metadata',
        ], 'privacy:metadata:tool_mailaudit_mail');

        $collection->add_database_table('tool_mailaudit_access', [
            'userid' => 'privacy:metadata:tool_mailaudit_access:userid',
            'action' => 'privacy:metadata:tool_mailaudit_access:action',
            'criteria' => 'privacy:metadata:tool_mailaudit_access:criteria',
            'requestip' => 'privacy:metadata:tool_mailaudit_access:requestip',
            'useragent' => 'privacy:metadata:tool_mailaudit_access:useragent',
        ], 'privacy:metadata:tool_mailaudit_access');

        return $collection;
    }

    /**
     * Return contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if (!self::table_exists('tool_mailaudit_mail') && !self::table_exists('tool_mailaudit_access')) {
            return $contextlist;
        }

        $hasmail = self::table_exists('tool_mailaudit_mail') && $DB->record_exists_select(
            'tool_mailaudit_mail',
            'fromuserid = :fromuserid OR touserid = :touserid OR requestuserid = :requestuserid',
            ['fromuserid' => $userid, 'touserid' => $userid, 'requestuserid' => $userid]
        );
        $hasaccess = self::table_exists('tool_mailaudit_access') && $DB->record_exists(
            'tool_mailaudit_access',
            ['userid' => $userid]
        );

        if ($hasmail || $hasaccess) {
            $contextlist->add_from_sql(
                'SELECT id FROM {context} WHERE id = :contextid',
                ['contextid' => context_system::instance()->id]
            );
        }

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;
        $context = context_system::instance();
        $data = new \stdClass();

        if (self::table_exists('tool_mailaudit_mail')) {
            $records = $DB->get_records_select(
                'tool_mailaudit_mail',
                'fromuserid = :fromuserid OR touserid = :touserid OR requestuserid = :requestuserid',
                ['fromuserid' => $userid, 'touserid' => $userid, 'requestuserid' => $userid],
                'timecreated DESC, id DESC'
            );
            $data->mail = array_values(array_map([self::class, 'export_record'], $records));
        }

        if (self::table_exists('tool_mailaudit_access')) {
            $records = $DB->get_records('tool_mailaudit_access', ['userid' => $userid], 'timecreated DESC, id DESC');
            $data->access = array_values(array_map([self::class, 'export_record'], $records));
        }

        if (!empty($data->mail) || !empty($data->access)) {
            writer::with_context($context)->export_data([get_string('pluginname', 'tool_mailaudit')], $data);
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        if (self::table_exists('tool_mailaudit_mail')) {
            $DB->delete_records('tool_mailaudit_mail');
        }
        if (self::table_exists('tool_mailaudit_access')) {
            $DB->delete_records('tool_mailaudit_access');
        }
    }

    /**
     * Delete user data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;
        if (self::table_exists('tool_mailaudit_mail')) {
            $DB->delete_records_select(
                'tool_mailaudit_mail',
                'fromuserid = :fromuserid OR touserid = :touserid OR requestuserid = :requestuserid',
                ['fromuserid' => $userid, 'touserid' => $userid, 'requestuserid' => $userid]
            );
        }
        if (self::table_exists('tool_mailaudit_access')) {
            $DB->delete_records('tool_mailaudit_access', ['userid' => $userid]);
        }
    }

    /**
     * Remove the id from exported records.
     *
     * @param \stdClass $record DB record.
     * @return \stdClass
     */
    private static function export_record(\stdClass $record): \stdClass {
        unset($record->id);
        return $record;
    }

    /**
     * Check table existence.
     *
     * @param string $tablename Table name.
     * @return bool
     */
    private static function table_exists(string $tablename): bool {
        global $DB;

        try {
            return $DB->get_manager()->table_exists(new xmldb_table($tablename));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
