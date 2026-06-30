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
 * Delete matching audited sent email records.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_mailaudit\local\access;
use tool_mailaudit\local\filters;
use tool_mailaudit\local\repository;

require_login();
access::require_delete();

$filters = filters::from_request();
$params = $filters->to_params();
$url = new moodle_url('/admin/tool/mailaudit/delete.php', $params);
$cancelurl = new moodle_url('/admin/tool/mailaudit/index.php', $params);
$PAGE->set_url($url);
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('softdeletematching', 'tool_mailaudit'));
$PAGE->set_heading(get_string('pluginname', 'tool_mailaudit'));

$count = repository::count_active($filters);

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    if ($count) {
        $deleted = repository::soft_delete($filters, (int)$USER->id);
        access::log('mail_deleted', null, ['filters' => $filters->to_array(), 'deletedcount' => $deleted]);
        redirect(
            $cancelurl,
            get_string('deletedcount', 'tool_mailaudit', $deleted),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    access::log('mail_delete_empty', null, ['filters' => $filters->to_array()]);
    redirect(
        $cancelurl,
        get_string('nothingtodelete', 'tool_mailaudit'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

access::log('mail_delete_confirm_viewed', null, ['filters' => $filters->to_array(), 'count' => $count]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('softdeletematching', 'tool_mailaudit'));

if (!$count) {
    echo $OUTPUT->notification(get_string('nothingtodelete', 'tool_mailaudit'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->single_button($cancelurl, get_string('backtolist', 'tool_mailaudit'));
    echo $OUTPUT->footer();
    exit;
}

$confirmurl = new moodle_url('/admin/tool/mailaudit/delete.php', $params + [
    'confirm' => 1,
    'sesskey' => sesskey(),
]);
echo $OUTPUT->confirm(get_string('confirmdelete', 'tool_mailaudit', $count), $confirmurl, $cancelurl);
echo $OUTPUT->footer();
