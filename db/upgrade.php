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

/**
 * Upgrade steps for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mail audit plugin upgrade steps.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_tool_mailaudit_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061201) {
        $table = new xmldb_table('tool_mailaudit_mail');

        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'kind');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026061201, 'tool', 'mailaudit');
    }

    if ($oldversion < 2026061202) {
        $table = new xmldb_table('tool_mailaudit_mail');

        $field = new xmldb_field('moodlemessageid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'messageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('moodlemessagetable', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'moodlemessageid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('moodlemsg', XMLDB_INDEX_NOTUNIQUE, ['moodlemessagetable', 'moodlemessageid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026061202, 'tool', 'mailaudit');
    }

    if ($oldversion < 2026061203) {
        $table = new xmldb_table('tool_mailaudit_mail');

        $fields = [
            new xmldb_field('fromusername', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'fromname'),
            new xmldb_field('fromfirstname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'fromusername'),
            new xmldb_field('fromlastname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'fromfirstname'),
            new xmldb_field('tousername', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'toname'),
            new xmldb_field('tofirstname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'tousername'),
            new xmldb_field('tolastname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'tofirstname'),
            new xmldb_field('coursefullname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'tolastname'),
            new xmldb_field('courseshortname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'coursefullname'),
            new xmldb_field('bodybytes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attachmentnames'),
            new xmldb_field('classificationnote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'bodybytes'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $indexes = [
            new xmldb_index('deleted_timecreated', XMLDB_INDEX_NOTUNIQUE, ['deleted', 'timecreated']),
            new xmldb_index('kind_timecreated', XMLDB_INDEX_NOTUNIQUE, ['kind', 'timecreated']),
            new xmldb_index('status_timecreated', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']),
            new xmldb_index('course_timecreated', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']),
            new xmldb_index('fromuser_timecreated', XMLDB_INDEX_NOTUNIQUE, ['fromuserid', 'timecreated']),
            new xmldb_index('touser_timecreated', XMLDB_INDEX_NOTUNIQUE, ['touserid', 'timecreated']),
            new xmldb_index('fromusername', XMLDB_INDEX_NOTUNIQUE, ['fromusername']),
            new xmldb_index('tousername', XMLDB_INDEX_NOTUNIQUE, ['tousername']),
            new xmldb_index('fromlastname', XMLDB_INDEX_NOTUNIQUE, ['fromlastname']),
            new xmldb_index('tolastname', XMLDB_INDEX_NOTUNIQUE, ['tolastname']),
        ];

        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $usercache = [];
        $coursecache = [];
        $rs = $DB->get_recordset(
            'tool_mailaudit_mail',
            null,
            'id ASC',
            'id, fromuserid, touserid, courseid, bodytext, bodyhtml, component, messagename, kind'
        );
        foreach ($rs as $record) {
            $update = (object)['id' => $record->id];

            foreach (['from' => 'fromuserid', 'to' => 'touserid'] as $prefix => $fieldname) {
                $userid = (int)($record->{$fieldname} ?? 0);
                if ($userid <= 0) {
                    continue;
                }
                if (!array_key_exists($userid, $usercache)) {
                    $usercache[$userid] = $DB->get_record(
                        'user',
                        ['id' => $userid],
                        'id, username, firstname, lastname',
                        IGNORE_MISSING
                    ) ?: false;
                }
                if ($usercache[$userid]) {
                    $update->{$prefix . 'username'} = core_text::substr((string)$usercache[$userid]->username, 0, 100);
                    $update->{$prefix . 'firstname'} = core_text::substr((string)$usercache[$userid]->firstname, 0, 100);
                    $update->{$prefix . 'lastname'} = core_text::substr((string)$usercache[$userid]->lastname, 0, 100);
                }
            }

            $courseid = (int)($record->courseid ?? 0);
            if ($courseid > 0) {
                if (!array_key_exists($courseid, $coursecache)) {
                    $coursecache[$courseid] = $DB->get_record(
                        'course',
                        ['id' => $courseid],
                        'id, fullname, shortname',
                        IGNORE_MISSING
                    ) ?: false;
                }
                if ($coursecache[$courseid]) {
                    $update->coursefullname = core_text::substr((string)$coursecache[$courseid]->fullname, 0, 255);
                    $update->courseshortname = core_text::substr((string)$coursecache[$courseid]->shortname, 0, 255);
                }
            }

            $update->bodybytes = strlen((string)($record->bodytext ?? '')) + strlen((string)($record->bodyhtml ?? ''));
            if (
                (string)$record->kind === 'other'
                    && (string)$record->messagename === 'newlogin'
                    && in_array((string)$record->component, ['moodle', 'core'], true)
            ) {
                $update->kind = 'new_ip_login';
                $update->classificationnote = 'Backfilled from Moodle newlogin message metadata during 0.2.0 upgrade.';
            }

            $DB->update_record('tool_mailaudit_mail', $update);
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026061203, 'tool', 'mailaudit');
    }

    if ($oldversion < 2026061204) {
        upgrade_plugin_savepoint(true, 2026061204, 'tool', 'mailaudit');
    }

    if ($oldversion < 2026063002) {
        $table = new xmldb_table('tool_mailaudit_mail');

        // Drop single-column indexes now covered by composite *_timecreated indexes, or that cannot
        // serve the substring (LIKE '%..%') and non-sortable lookups they were created for.
        $redundant = [
            new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']),
            new xmldb_index('kind', XMLDB_INDEX_NOTUNIQUE, ['kind']),
            new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']),
            new xmldb_index('fromuserid', XMLDB_INDEX_NOTUNIQUE, ['fromuserid']),
            new xmldb_index('touserid', XMLDB_INDEX_NOTUNIQUE, ['touserid']),
            new xmldb_index('deleted', XMLDB_INDEX_NOTUNIQUE, ['deleted']),
            new xmldb_index('fromemail', XMLDB_INDEX_NOTUNIQUE, ['fromemail']),
            new xmldb_index('toemail', XMLDB_INDEX_NOTUNIQUE, ['toemail']),
            new xmldb_index('fromusername', XMLDB_INDEX_NOTUNIQUE, ['fromusername']),
            new xmldb_index('tousername', XMLDB_INDEX_NOTUNIQUE, ['tousername']),
        ];
        foreach ($redundant as $index) {
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }
        }

        // Index messageid to support forgot-password dedup lookups on large tables.
        $messageidindex = new xmldb_index('messageid', XMLDB_INDEX_NOTUNIQUE, ['messageid']);
        if (!$dbman->index_exists($table, $messageidindex)) {
            $dbman->add_index($table, $messageidindex);
        }

        upgrade_plugin_savepoint(true, 2026063002, 'tool', 'mailaudit');
    }

    return true;
}
