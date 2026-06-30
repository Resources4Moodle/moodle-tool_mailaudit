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
 * View one audited sent email.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_mailaudit\local\access;
use tool_mailaudit\local\repository;

require_login();
$id = required_param('id', PARAM_INT);
$record = repository::get($id);
access::require_view_record($record);

$context = \context_system::instance();
$course = null;
if (!empty($record->courseid)) {
    $course = $DB->get_record('course', ['id' => (int)$record->courseid], '*', IGNORE_MISSING);
    if ($course) {
        $context = \context_course::instance((int)$record->courseid);
    }
}

$url = new moodle_url('/admin/tool/mailaudit/view.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'tool_mailaudit'));
$PAGE->set_heading(get_string('pluginname', 'tool_mailaudit'));

access::log('mail_viewed', $id, ['id' => $id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($record->subject ?? get_string('subject', 'tool_mailaudit')));
$backparams = !empty($record->courseid) ? ['courseid' => (int)$record->courseid] : [];
echo html_writer::div(html_writer::link(
    new moodle_url('/admin/tool/mailaudit/index.php', $backparams),
    get_string('backtolist', 'tool_mailaudit')
), 'mb-3');

$coursename = $course ? format_string($course->fullname, true, ['context' => $context]) : get_string('site');
$origin = ($record->originfile ?? '') . (!empty($record->originline) ? ':' . $record->originline : '');

$details = new html_table();
$details->attributes['class'] = 'generaltable table-sm';
$details->data = [
    [get_string('senttime', 'tool_mailaudit'), userdate($record->timecreated)],
    [get_string('course', 'tool_mailaudit'), $coursename],
    [get_string('kind', 'tool_mailaudit'), repository::kind_label($record->kind)],
    [get_string('status', 'tool_mailaudit'), s($record->status)],
    [get_string('sender', 'tool_mailaudit'), s(trim(($record->fromname ?? '') . ' ' . ($record->fromemail ?? '')))],
    [get_string('recipient', 'tool_mailaudit'), s(trim(($record->toname ?? '') . ' ' . ($record->toemail ?? '')))],
    [get_string('component', 'tool_mailaudit'), s($record->component ?? '')],
    [get_string('messagename', 'tool_mailaudit'), s($record->messagename ?? '')],
    [get_string('origin', 'tool_mailaudit'), s($origin)],
    [get_string('messageid', 'tool_mailaudit'), s($record->messageid ?? '')],
    [get_string('moodlemessage', 'tool_mailaudit'), s(trim(($record->moodlemessagetable ?? '') . ' ' .
        ($record->moodlemessageid ?? '')))],
    [get_string('attachments', 'tool_mailaudit'), s($record->attachmentnames ?? '')],
];
if (!empty($record->deleted)) {
    $deletedlabel = userdate($record->timedeleted) . ' / user ' . (int)$record->deletedby;
    $details->data[] = [get_string('deleted', 'tool_mailaudit'), $deletedlabel];
}
echo html_writer::table($details);

if (trim($record->bodytext ?? '') !== '') {
    echo $OUTPUT->heading(get_string('textbody', 'tool_mailaudit'), 3);
    echo html_writer::tag('pre', s($record->bodytext), ['class' => 'p-3 border bg-light']);
}

if (trim($record->bodyhtml ?? '') !== '') {
    echo $OUTPUT->heading(get_string('htmlbody', 'tool_mailaudit'), 3);
    echo html_writer::div(format_text($record->bodyhtml, FORMAT_HTML, ['trusted' => false, 'noclean' => false]), 'border p-3 mb-3');
    echo $OUTPUT->heading(get_string('htmlsource', 'tool_mailaudit'), 4);
    echo html_writer::tag('pre', s($record->bodyhtml), ['class' => 'p-3 border bg-light']);
}

echo $OUTPUT->heading(get_string('metadata', 'tool_mailaudit'), 3);
$decodedmetadata = json_decode($record->metadata ?? '{}');
$prettymetadata = json_encode($decodedmetadata ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo html_writer::tag('pre', s($prettymetadata !== false ? $prettymetadata : '{}'), [
    'class' => 'p-3 border bg-light',
]);

echo $OUTPUT->footer();
