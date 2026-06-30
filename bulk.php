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
 * Bulk actions for selected (or all matching) sent-mail audit rows.
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
require_sesskey();

$filters = filters::from_request();
$params = $filters->to_params();
$returnurl = new moodle_url('/admin/tool/mailaudit/index.php', $params);
$ids = array_values(array_unique(array_filter(array_map('intval', optional_param_array('mailids', [], PARAM_INT)))));
$action = optional_param('bulkaction', '', PARAM_ALPHA);
$matching = optional_param('selectallmatching', 0, PARAM_BOOL) ? true : false;
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$urlparams = $params + ['bulkaction' => $action, 'selectallmatching' => $matching ? 1 : 0, 'sesskey' => sesskey()];
if (!$matching) {
    foreach ($ids as $id) {
        $urlparams['mailids'][] = $id;
    }
}
$url = new moodle_url('/admin/tool/mailaudit/bulk.php', $urlparams);

$PAGE->set_url($url);
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bulkselected', 'tool_mailaudit'));
$PAGE->set_heading(get_string('pluginname', 'tool_mailaudit'));

if ((!$ids && !$matching) || !in_array($action, ['delete', 'purge'], true)) {
    redirect($returnurl, get_string('nothingselected', 'tool_mailaudit'), null, \core\output\notification::NOTIFY_INFO);
}

$affectedcount = $matching
    ? ($action === 'delete' ? repository::count_active($filters) : repository::count_deleted($filters))
    : count($ids);

if ($confirm) {
    if ($action === 'delete') {
        $count = $matching
            ? repository::soft_delete($filters, (int)$USER->id)
            : repository::soft_delete_ids($ids, (int)$USER->id);
        access::log(
            'mail_deleted',
            null,
            ($matching ? ['filters' => $filters->to_array()] : ['ids' => $ids]) + ['deletedcount' => $count, 'bulk' => true]
        );
        redirect(
            $returnurl,
            get_string('deletedcount', 'tool_mailaudit', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $count = $matching ? repository::purge_matching_deleted($filters) : repository::purge_ids($ids);
    access::log(
        'mail_purged',
        null,
        ($matching ? ['filters' => $filters->to_array()] : ['ids' => $ids]) + ['deletedcount' => $count, 'bulk' => true]
    );
    redirect(
        $returnurl,
        get_string('purgedcount', 'tool_mailaudit', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkselected', 'tool_mailaudit'));

$confirmurl = new moodle_url('/admin/tool/mailaudit/bulk.php', $urlparams + ['confirm' => 1]);
$message = $action === 'delete'
    ? get_string('confirmbulkdelete', 'tool_mailaudit', $affectedcount)
    : get_string('confirmbulkpurge', 'tool_mailaudit', $affectedcount);
echo $OUTPUT->confirm($message, $confirmurl, $returnurl);
echo $OUTPUT->footer();
