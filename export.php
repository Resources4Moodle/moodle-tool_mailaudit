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
 * Export the matching audited sent email as CSV.
 *
 * Honours exactly the same access scope and filters as the browser.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use tool_mailaudit\local\access;
use tool_mailaudit\local\filters;
use tool_mailaudit\local\repository;

require_login();

$filters = filters::from_request();

access::require_view($filters->courseid);
$visibility = access::visibility_sql();

$PAGE->set_url(new moodle_url('/admin/tool/mailaudit/export.php', $filters->to_params()));
$PAGE->set_context(\context_system::instance());

access::log('list_exported', null, $filters->to_array());

[$where, $params] = repository::where_sql($filters, false, $visibility);

$export = new \csv_export_writer();
$export->set_filename('mailaudit-' . userdate(time(), '%Y%m%d-%H%M'));
$export->add_data([
    get_string('senttime', 'tool_mailaudit'),
    get_string('course', 'tool_mailaudit'),
    get_string('kind', 'tool_mailaudit'),
    get_string('status', 'tool_mailaudit'),
    get_string('sender', 'tool_mailaudit'),
    get_string('recipient', 'tool_mailaudit'),
    get_string('subject', 'tool_mailaudit'),
    get_string('size'),
    get_string('deleted', 'tool_mailaudit'),
]);

$rs = $DB->get_recordset_select(repository::TABLE, $where, $params, 'timecreated DESC, id DESC');
foreach ($rs as $record) {
    $sender = trim(($record->fromname ?? '') . ' ' . ($record->fromemail ?? ''));
    $recipient = trim(($record->toname ?? '') . ' ' . ($record->toemail ?? ''));
    $course = trim((string)($record->coursefullname ?? $record->courseshortname ?? ''));
    $export->add_data([
        userdate((int)$record->timecreated),
        $course !== '' ? $course : get_string('site'),
        repository::kind_label((string)$record->kind),
        (string)$record->status,
        $sender,
        $recipient,
        (string)($record->subject ?? ''),
        display_size((int)($record->bodybytes ?? 0)),
        !empty($record->deleted) ? get_string('yes') : get_string('no'),
    ]);
}
$rs->close();

$export->download_file();
